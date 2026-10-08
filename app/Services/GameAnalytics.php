<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Lecture (SEULE) des tables d'analyse alimentées par le plugin GeoFactions
 * dans la base du jeu (connexion `game`) :
 *
 *   gf_metrics (id, ts, scope, ref, metric, value, extra)
 *   gf_events  (id, ts, type, ref, actor, data)
 *   gf_rollup  (même forme que gf_metrics : moyennes horaires/journalières
 *               des données anciennes)
 *
 * Règles de ce service :
 *  - FAIL-SAFE : base absente, injoignable, tables absentes ou requête en
 *    erreur donnent un résultat vide et un `status()` explicite, jamais une
 *    exception vers la vue (donc jamais de 500).
 *  - Requêtes 100 % paramétrées (query builder) ; aucune valeur utilisateur
 *    n'est concaténée dans le SQL. Les filtres utilisent (scope, metric, ref,
 *    ts) ou (type, ts) : à indexer côté plugin.
 *  - Courbes échantillonnées côté serveur : ~400 points maximum par courbe,
 *    pas d'agrégation choisi selon la durée (60 s ... 1 jour).
 *  - Cache court (30 s) sur chaque lecture ; les bornes de temps sont arrondies
 *    à 30 s pour que la clé reste stable.
 *  - Pas de PII : la colonne `actor` n'est JAMAIS lue et les clés évoquant un
 *    joueur sont retirées du JSON des événements.
 */
class GameAnalytics
{
    public const PERIODS = ['24h' => 86400, '7d' => 604800, '30d' => 2592000, '90d' => 7776000, '1y' => 31536000];

    public const SCOPES = ['server', 'faction', 'economy', 'oil', 'pollution', 'players', 'missiles', 'war', 'usage', 'achievements', 'ranking', 'logistics'];

    /** Fuseau d'affichage de toutes les dates. */
    public const TZ = 'Europe/Paris';

    /** Unités de repli (le catalogue, kind `metric`, prime). */
    public const UNITS = [
        'mspt' => 'ms', 'ram_used_mb' => 'Mo', 'ram_max_mb' => 'Mo', 'ram' => 'Mo', 'gc_pause_ms' => 'ms', 'db_latency_ms' => 'ms', 'uptime_s' => '',
        'avg_session_min' => 'min', 'playtime_hours_week' => 'h', 'playtime_hours' => 'h',
        'retention_d1' => '%', 'retention_d7' => '%', 'd1' => '%', 'd7' => '%', 'retention_w1' => '%', 'retention_w2' => '%', 'retention_w3' => '%', 'retention_w4' => '%',
        'reserve_pct' => '%', 'interception_rate_pct' => '%', 'assault_gauge_peak' => '%', 'unlocked_pct' => '%', 'inflation_pct' => '%', 'degraded_pct' => '%', 'mspt_p95' => 'ms', 'playtime_hours' => 'h', 'wealth' => 'GC',
        'bank' => 'GC', 'money_supply' => 'GC', 'avg_balance' => 'GC', 'hdv_volume_24h' => 'GC', 'trade_tax_24h' => 'GC', 'reparations_paid' => 'GC',
        'gdp' => 'GC', 'trade_balance' => 'GC', 'tax_revenue_24h' => 'GC', 'trade_volume_24h' => 'GC', 'bourse_value_sold_24h' => 'GC',
        'reserve' => 'mB', 'extracted_total' => 'mB', 'extracted_24h' => 'mB',
        'emissions' => 'pts', 'avg_level' => 'pts', 'max_level' => 'pts',
    ];

    public const MAX_POINTS = 400;

    private const STEPS = [60, 300, 900, 1800, 3600, 7200, 14400, 21600, 43200, 86400];

    private const TTL = 30;

    /** Clés retirées des JSON d'événements (aucune donnée personnelle côté panel). */
    private const PII_KEYS = ['actor', 'player', 'players', 'uuid', 'pseudo', 'username', 'ip', 'killer', 'victim', 'owner', 'user', 'leader', 'by_player'];

    public static function period(?string $p): string
    {
        return isset(self::PERIODS[$p ?? '']) ? $p : '7d';
    }

    /** Heure courante arrondie à 30 s (clé de cache stable). */
    public function now(): int
    {
        return intdiv(time(), self::TTL) * self::TTL;
    }

    /** @return array{0:int,1:int} */
    public function range(string $period): array
    {
        $to = $this->now();

        return [$to - self::PERIODS[self::period($period)], $to];
    }

    public static function bucketFor(int $span): int
    {
        $want = (int) ceil(max(1, $span) / self::MAX_POINTS);
        foreach (self::STEPS as $s) {
            if ($s >= $want) {
                return $s;
            }
        }

        return end(self::STEPS);
    }

    public static function validRef(string $ref): bool
    {
        return $ref === '*' || (bool) preg_match('/^[\p{L}\p{N}_ .:\-\']{0,64}$/u', $ref);
    }

    public static function validName(string $s): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{0,47}$/', $s);
    }

    // ── Connexion / état ────────────────────────────────────────────────

    /** Identité de la base lue : sépare les caches si la connexion change (le cache fichier ignore CACHE_PREFIX). */
    public function namespaceKey(): string
    {
        return $this->ns();
    }

    private function ns(): string
    {
        $c = config('database.connections.game', []);

        return substr(md5(json_encode([$c['driver'] ?? '', $c['host'] ?? '', $c['port'] ?? '', $c['database'] ?? ''])), 0, 12);
    }

    private function conn(): Connection
    {
        return DB::connection('game');
    }

    private function q(string $table)
    {
        return $this->conn()->table($table);
    }

    /**
     * unconfigured | unreachable | no_tables | ok  (+ présence de chaque table).
     *
     * @return array{state:string,metrics:bool,events:bool,rollup:bool,catalog:bool}
     */
    public function status(): array
    {
        return Cache::remember('geo_analytics_status2_' . $this->ns(), self::TTL, function () {
            $st = ['state' => 'unconfigured', 'metrics' => false, 'events' => false, 'rollup' => false, 'catalog' => false];
            if (trim((string) config('database.connections.game.database', '')) === '') {
                return $st;
            }
            try {
                $this->conn()->select('select 1');
            } catch (\Throwable $e) {
                Log::info('GameAnalytics: base du jeu injoignable : ' . $e->getMessage());
                $st['state'] = 'unreachable';

                return $st;
            }
            try {
                $schema = Schema::connection('game');
                $st['metrics'] = $schema->hasTable('gf_metrics');
                $st['events'] = $schema->hasTable('gf_events');
                $st['rollup'] = $schema->hasTable('gf_rollup');
                $st['catalog'] = $schema->hasTable('gf_catalog');
            } catch (\Throwable $e) {
                Log::info('GameAnalytics: lecture du schéma impossible : ' . $e->getMessage());
                $st['state'] = 'unreachable';

                return $st;
            }
            $st['state'] = ($st['metrics'] || $st['events']) ? 'ok' : 'no_tables';

            return $st;
        });
    }

    private function usable(string $what): bool
    {
        $st = $this->status();

        return $st['state'] === 'ok' && ! empty($st[$what]);
    }

    /** Exécute $fn en cache 30 s ; toute erreur donne $default (non mis en cache). */
    private function cached(array $key, callable $fn, $default)
    {
        $k = 'geo_an_' . $this->ns() . '_' . md5(json_encode($key));
        try {
            $hit = Cache::get($k);
            if ($hit !== null) {
                return $hit;
            }
            $val = $fn();
            Cache::put($k, $val, self::TTL);

            return $val;
        } catch (\Throwable $e) {
            Log::warning('GameAnalytics: ' . $e->getMessage());

            return $default;
        }
    }

    // ── Métriques ───────────────────────────────────────────────────────

    /**
     * Courbes alignées sur une même grille de temps.
     *
     * @param  array<int,array{scope:string,metric:string,ref?:string,agg?:string}>  $specs
     * @return array{labels:int[],series:array<int,array<int,?float>>,bucket:int}
     */
    public function series(array $specs, int $from, int $to, ?int $bucket = null): array
    {
        $empty = ['labels' => [], 'series' => array_map(fn () => [], $specs), 'bucket' => $bucket ?? self::bucketFor($to - $from)];
        if (! $this->usable('metrics')) {
            return $empty;
        }

        return $this->cached(['series', $specs, $from, $to, $bucket], function () use ($specs, $from, $to, $bucket, $empty) {
            $bucket ??= self::bucketFor($to - $from);
            $st = $this->status();
            $perSpec = [];
            $all = [];
            foreach ($specs as $i => $sp) {
                $rows = $this->bucketRows('gf_metrics', $sp, $from, $to, $bucket);
                if ($st['rollup']) {
                    foreach ($this->bucketRows('gf_rollup', $sp, $from, $to, $bucket) as $b => $v) {
                        $rows[$b] ??= $v; // la donnée brute prime sur le rollup
                    }
                }
                $perSpec[$i] = $rows;
                foreach ($rows as $b => $_) {
                    $all[$b] = true;
                }
            }
            $labels = array_keys($all);
            sort($labels);
            $series = [];
            foreach ($specs as $i => $_) {
                $series[$i] = array_map(fn ($b) => $perSpec[$i][$b] ?? null, $labels);
            }

            return ['labels' => $labels, 'series' => $series, 'bucket' => $bucket];
        }, $empty);
    }

    /** @return array<int,float>  bucket => valeur */
    private function bucketRows(string $table, array $sp, int $from, int $to, int $bucket): array
    {
        $expr = $this->conn()->getDriverName() === 'sqlite' ? '(ts / ?) * ?' : '(ts DIV ?) * ?';
        $agg = ($sp['agg'] ?? 'avg') === 'max' ? 'MAX(value)' : 'AVG(value)';
        $ref = $sp['ref'] ?? '';
        $q = $this->q($table)
            ->selectRaw("$expr AS b, ref, $agg AS v", [$bucket, $bucket])
            ->where('scope', $sp['scope'])
            ->where('metric', $sp['metric'])
            ->where('ts', '>=', $from)
            ->where('ts', '<=', $to);
        if ($ref !== '*') {
            $q->where('ref', $ref);
        }
        $out = [];
        foreach ($q->groupBy('b', 'ref')->get() as $r) {
            // ref '*' : somme des pays (chaque pays déjà moyenné par pas).
            $b = (int) $r->b;
            $out[$b] = ($out[$b] ?? 0.0) + (float) $r->v;
        }

        return array_map(fn ($v) => round($v, 3), $out);
    }

    /**
     * Dernière valeur connue par ref (depuis $since).
     *
     * @return array<string,array{v:float,ts:int,extra:?string}>
     */
    public function latest(string $scope, string $metric, int $since): array
    {
        if (! $this->usable('metrics')) {
            return [];
        }

        return $this->cached(['latest', $scope, $metric, $since], function () use ($scope, $metric, $since) {
            $out = [];
            $tables = $this->status()['rollup'] ? ['gf_metrics', 'gf_rollup'] : ['gf_metrics'];
            foreach ($tables as $table) {
                $sub = $this->q($table)->select('ref')->selectRaw('MAX(ts) AS mts')
                    ->where('scope', $scope)->where('metric', $metric)->where('ts', '>=', $since)->groupBy('ref');
                $rows = $this->q($table . ' as m')
                    ->joinSub($sub, 'x', fn ($j) => $j->on('x.ref', '=', 'm.ref')->on('x.mts', '=', 'm.ts'))
                    ->where('m.scope', $scope)->where('m.metric', $metric)
                    ->get(['m.ref', 'm.value', 'm.ts', 'm.extra']);
                foreach ($rows as $r) {
                    $ts = (int) $r->ts;
                    if (! isset($out[$r->ref]) || $ts > $out[$r->ref]['ts']) {
                        $out[$r->ref] = ['v' => (float) $r->value, 'ts' => $ts, 'extra' => $r->extra];
                    }
                }
            }

            return $out;
        }, []);
    }

    /** Valeur la plus récente d'une métrique sans ref (ou null). */
    public function latestOne(string $scope, string $metric, int $since, string $ref = ''): ?float
    {
        return $this->latest($scope, $metric, $since)[$ref]['v'] ?? null;
    }

    /**
     * Dernier lot de lignes d'un scope (par métrique, ts le plus récent), avec `extra`.
     *
     * @return array<int,array{metric:string,ref:string,value:float,extra:?string,ts:int}>
     */
    public function latestBatch(string $scope, int $since): array
    {
        if (! $this->usable('metrics')) {
            return [];
        }

        return $this->cached(['batch', $scope, $since], function () use ($scope, $since) {
            $sub = $this->q('gf_metrics')->select('metric')->selectRaw('MAX(ts) AS mts')
                ->where('scope', $scope)->where('ts', '>=', $since)->groupBy('metric');
            $rows = $this->q('gf_metrics as m')
                ->joinSub($sub, 'x', fn ($j) => $j->on('x.metric', '=', 'm.metric')->on('x.mts', '=', 'm.ts'))
                ->where('m.scope', $scope)
                ->orderBy('m.metric')->orderByDesc('m.value')
                ->limit(500)
                ->get(['m.metric', 'm.ref', 'm.value', 'm.extra', 'm.ts']);

            return $rows->map(fn ($r) => ['metric' => (string) $r->metric, 'ref' => (string) $r->ref, 'value' => (float) $r->value,
                'extra' => $r->extra, 'ts' => (int) $r->ts])->all();
        }, []);
    }

    /** Refs (pays, zones, mondes) vus pour une métrique. @return string[] */
    public function refs(string $scope, string $metric, int $since): array
    {
        if (! $this->usable('metrics')) {
            return [];
        }

        return $this->cached(['refs', $scope, $metric, $since], function () use ($scope, $metric, $since) {
            return $this->q('gf_metrics')->where('scope', $scope)->where('metric', $metric)
                ->where('ts', '>=', $since)->where('ref', '!=', '')->distinct()->orderBy('ref')->limit(200)->pluck('ref')->map(fn ($r) => (string) $r)->all();
        }, []);
    }

    /** Horodatage de la dernière mesure reçue (ou null). */
    public function lastMetricTs(): ?int
    {
        if (! $this->usable('metrics')) {
            return null;
        }

        return $this->cached(['lastts'], fn () => ($v = $this->q('gf_metrics')->max('ts')) === null ? 0 : (int) $v, 0) ?: null;
    }

    // ── Événements ──────────────────────────────────────────────────────

    /**
     * @param  array{types?:string[],ref?:?string,from?:?int,to?:?int}  $f
     * @return array{rows:array<int,array>,total:int}
     */
    public function events(array $f, int $limit = 50, int $offset = 0): array
    {
        $empty = ['rows' => [], 'total' => 0];
        if (! $this->usable('events')) {
            return $empty;
        }

        return $this->cached(['events', $f, $limit, $offset], function () use ($f, $limit, $offset) {
            $q = $this->eventQuery($f);
            $total = (clone $q)->count();
            $rows = $q->orderByDesc('ts')->orderByDesc('id')->offset($offset)->limit($limit)->get(['id', 'ts', 'type', 'ref', 'data']);

            return ['rows' => $rows->map(fn ($r) => $this->mapEvent($r))->all(), 'total' => (int) $total];
        }, $empty);
    }

    private function eventQuery(array $f)
    {
        $q = $this->q('gf_events');
        if (! empty($f['types'])) {
            $q->whereIn('type', array_values($f['types']));
        }
        if (isset($f['ref']) && $f['ref'] !== null && $f['ref'] !== '') {
            $q->where('ref', $f['ref']);
        }
        if (! empty($f['from'])) {
            $q->where('ts', '>=', (int) $f['from']);
        }
        if (! empty($f['to'])) {
            $q->where('ts', '<=', (int) $f['to']);
        }

        return $q;
    }

    private function mapEvent($r): array
    {
        $data = is_string($r->data) && $r->data !== '' ? json_decode($r->data, true) : null;

        return [
            'id' => (int) $r->id,
            'ts' => (int) $r->ts,
            'type' => (string) $r->type,
            'ref' => (string) $r->ref,
            'data' => is_array($data) ? self::scrub($data) : [],
        ];
    }

    /** Retire les clés personnelles, tronque les chaînes longues, limite la profondeur. */
    public static function scrub(array $d, int $depth = 0): array
    {
        $out = [];
        foreach ($d as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), self::PII_KEYS, true)) {
                continue;
            }
            if (is_array($v)) {
                if ($depth < 3) {
                    $out[$k] = self::scrub($v, $depth + 1);
                }
            } elseif (is_string($v)) {
                $out[$k] = mb_substr($v, 0, 200);
            } elseif (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /** Événements d'un ensemble de types, anciens -> récents, plafonnés. */
    public function eventsOf(array $types, int $from, int $to, int $cap = 3000, ?string $ref = null): array
    {
        $r = $this->events(['types' => $types, 'from' => $from, 'to' => $to, 'ref' => $ref], $cap, 0);

        return array_reverse($r['rows']);
    }

    /** @return array<string,int> type => nombre */
    public function eventCounts(int $from, int $to): array
    {
        if (! $this->usable('events')) {
            return [];
        }

        return $this->cached(['counts', $from, $to], function () use ($from, $to) {
            return $this->q('gf_events')->select('type')->selectRaw('COUNT(*) AS n')
                ->where('ts', '>=', $from)->where('ts', '<=', $to)->groupBy('type')->orderByDesc('n')->limit(100)
                ->pluck('n', 'type')->map(fn ($n) => (int) $n)->all();
        }, []);
    }

    /** Types d'événements connus (observés dans la base). @return string[] */
    public function eventTypes(): array
    {
        if (! $this->usable('events')) {
            return [];
        }

        return $this->cached(['types'], fn () => $this->q('gf_events')->distinct()->orderBy('type')->limit(100)->pluck('type')->map(fn ($t) => (string) $t)->all(), []);
    }

    public function eventById(int $id): ?array
    {
        if (! $this->usable('events')) {
            return null;
        }

        return $this->cached(['event', $id], function () use ($id) {
            $r = $this->q('gf_events')->where('id', $id)->first(['id', 'ts', 'type', 'ref', 'data']);

            return $r ? $this->mapEvent($r) : null;
        }, null);
    }

    /** Événements missile_* proches d'un tir (même missile_id ou même ref). */
    public function relatedMissileEvents(array $launch): array
    {
        $mid = (string) ($launch['data']['missile_id'] ?? '');
        $rows = $this->events(['types' => ['missile_launched', 'missile_refused', 'missile_intercepted', 'missile_impact', 'missile_shot_down_native'],
            'from' => $launch['ts'] - 600, 'to' => $launch['ts'] + 7200], 400, 0)['rows'];
        $out = [];
        foreach ($rows as $e) {
            if ($e['id'] === $launch['id']) {
                continue;
            }
            $sameId = $mid !== '' && ((string) ($e['data']['missile_id'] ?? '') === $mid || $e['ref'] === $mid);
            if ($sameId) {
                $out[] = $e;
            }
        }
        usort($out, fn ($a, $b) => $a['ts'] <=> $b['ts']);

        return $out;
    }

    // ── Formatage (vues) ────────────────────────────────────────────────

    public static function carbon(?int $ts): \Carbon\Carbon
    {
        return \Carbon\Carbon::createFromTimestamp((int) $ts, self::TZ)->locale(app()->getLocale());
    }

    public static function fmtDate(?int $ts, bool $short = false): string
    {
        if (! $ts) {
            return '—';
        }

        return self::carbon($ts)->translatedFormat($short ? 'j M H:i' : 'j M Y H:i');
    }

    public static function fmtDay(?int $ts): string
    {
        return $ts ? self::carbon($ts)->translatedFormat('j M Y') : '—';
    }

    /** Nombre complet au format français (espace fine insécable) : 1 234 567,5 */
    public static function num($v, ?int $dec = null): string
    {
        if ($v === null || ! is_numeric($v)) {
            return '—';
        }
        $v = (float) $v;
        $dec ??= floor($v) == $v ? 0 : (abs($v) >= 100 ? 0 : (abs($v) >= 10 ? 1 : 2));
        $fr = app()->getLocale() === 'fr';

        return number_format($v, $dec, $fr ? ',' : '.', $fr ? "\u{202F}" : ',');
    }

    /** Nombre compact : 12,4 K · 3,2 M · 1,5 Md (fr) / K · M · B (en). Petits nombres : format complet. */
    public static function compact($v, ?int $dec = null): string
    {
        if ($v === null || ! is_numeric($v)) {
            return '—';
        }
        $v = (float) $v;
        $a = abs($v);
        $fr = app()->getLocale() === 'fr';
        foreach ([[1e9, $fr ? 'Md' : 'B'], [1e6, 'M'], [1e4, 'K']] as [$t, $suffix]) {
            if ($a >= $t) {
                $x = $v / $t;

                return self::num($x, $dec ?? (abs($x) >= 100 ? 0 : 1)) . "\u{202F}" . $suffix;
            }
        }

        return self::num($v, $dec);
    }

    /** Valeur + unité de la métrique (« 5 200 Mo », « 42 % »). */
    public static function fmtMetric(string $metric, $v, bool $compact = false): string
    {
        if ($v === null || ! is_numeric($v)) {
            return '—';
        }
        $u = AnalyticsCatalog::get()->unit($metric);
        $n = $compact ? self::compact($v) : self::num($v);

        return $u === '' ? $n : $n . ($u === '%' ? "\u{202F}%" : "\u{202F}" . $u);
    }

    public static function typeLabel(string $type): string
    {
        return AnalyticsCatalog::get()->eventType($type);
    }

    /** Nom de la métrique (sans unité). */
    public static function metricLabel(string $metric): string
    {
        return AnalyticsCatalog::get()->metric($metric);
    }

    /** « Nom (unité) » pour les en-têtes et légendes. */
    public static function metricTitle(string $metric): string
    {
        $u = AnalyticsCatalog::get()->unit($metric);
        $n = self::metricLabel($metric);

        return ($u === '' || str_contains($n, '(' . $u . ')')) ? $n : $n . ' (' . $u . ')';
    }

    /** Nom d'un pays. */
    public static function country(?string $ref): string
    {
        return AnalyticsCatalog::get()->faction($ref);
    }

    /** Nom du « sujet » d'un événement : pays, ou zone pour les événements pétroliers. */
    public static function eventRef(string $type, string $ref): string
    {
        if ($ref === '') {
            return '—';
        }

        return $type === 'oil_zone_depleted' ? AnalyticsCatalog::get()->label('oil_zone', $ref) : AnalyticsCatalog::get()->faction($ref);
    }

    /** Résumé lisible du JSON d'un événement. */
    public static function summarize(string $type, array $d, int $max = 160): string
    {
        return AnalyticsCatalog::get()->summarize($type, $d, $max);
    }

    /** Couleur stable d'un pays (catalogue sinon empreinte du nom). */
    public static function colorOf(string $ref): string
    {
        $x = AnalyticsCatalog::get()->extra('faction', $ref)['color'] ?? null;
        if (is_string($x) && preg_match('/^#[0-9a-fA-F]{6}$/', $x)) {
            return $x;
        }
        $pal = ['#4ade80', '#a78bfa', '#fb923c', '#60a5fa', '#f472b6', '#facc15', '#34d399', '#f87171', '#22d3ee', '#c084fc', '#a3e635', '#fb7185'];

        return $pal[crc32(mb_strtolower($ref)) % count($pal)];
    }
}
