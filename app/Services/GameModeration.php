<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Lecture (SEULE) des tables de modération alimentées par le plugin GeoFactions
 * dans la base du jeu (connexion `game`) :
 *
 *   gf_sanctions (id, player_uuid, player_name, type warn|mute|kick|ban, reason, staff_name, staff_uuid,
 *                 created_at, expires_at, active, revoked_by, revoked_at, revoke_reason, ip_hash)
 *   gf_reports   (id, reporter_uuid, reporter_name, target_name, reason, world, x, y, z, context,
 *                 status open|taken|closed, handled_by, created_at, closed_at, note)
 *   gf_player_ips (player_uuid, ip_hash, first_seen, last_seen)
 *
 * Règles :
 *  - FAIL-SAFE : base absente, injoignable, tables absentes ou requête en erreur => résultat vide
 *    et `status()` explicite, jamais d'exception vers la vue (donc jamais de 500).
 *  - Requêtes 100 % paramétrées (query builder), colonnes listées explicitement.
 *  - VIE PRIVÉE : `ip_hash` n'est JAMAIS sélectionné, sauf dans la jointure interne des « comptes
 *    liés » qui ne renvoie que des UUID et un compteur ; aucune IP ni e-mail ne sort du service
 *    (le texte libre est nettoyé par scrub()).
 *  - Cache court (30 s) ; lecture seule : aucune écriture dans la base du jeu.
 */
class GameModeration
{
    public const TYPES = ['warn', 'mute', 'kick', 'ban'];

    public const STATES = ['active', 'expired', 'revoked'];

    public const REPORT_STATUSES = ['open', 'taken', 'closed'];

    public const PER_PAGE = 25;

    public const EXPORT_MAX = 10000;

    private const TTL = 30;

    private const S_COLS = ['id', 'player_uuid', 'player_name', 'type', 'reason', 'staff_name', 'staff_uuid', 'created_at', 'expires_at',
        'active', 'revoked_by', 'revoked_at', 'revoke_reason'];

    private const R_COLS = ['id', 'reporter_uuid', 'reporter_name', 'target_name', 'reason', 'world', 'x', 'y', 'z', 'status',
        'handled_by', 'created_at', 'closed_at', 'note'];

    // ── Utilitaires purs ────────────────────────────────────────────────

    public function now(): int
    {
        return intdiv(time(), self::TTL) * self::TTL;
    }

    public static function validName(string $s): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_.\-]{1,36}$/', $s);
    }

    public static function isUuid(string $s): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i', $s);
    }

    /** UUID sous la forme 8-4-4-4-12 en minuscules (la base peut les stocker avec ou sans tirets). */
    public static function dashUuid(string $u): string
    {
        $h = strtolower(str_replace('-', '', $u));

        return strlen($h) === 32 ? substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20) : strtolower($u);
    }

    /** « #1a2b3c4d » : repli d'affichage quand aucun pseudo n'est connu. */
    public static function shortUuid(string $u): string
    {
        return '#' . substr(strtolower(str_replace('-', '', $u)), 0, 8);
    }

    /**
     * Texte libre venant du jeu (raison, contexte de chat, note) : sans code couleur
     * Minecraft ni caractère de contrôle (les chevrons restent du TEXTE, « <Pseudo> » des lignes de chat,
     * échappé à l'affichage), adresses IP et e-mails masquées, longueur bornée.
     * Le résultat reste à échapper à l'affichage ({{ }}).
     */
    public static function scrub(?string $s, int $max = 2000): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        $s = (string) preg_replace('/[§&][0-9a-fk-or]/iu', '', $s);
        $s = (string) preg_replace('/[^\P{C}\n\t]+/u', '', $s);
        $s = (string) preg_replace('/[\w.+\-]+@[\w\-]+(\.[\w\-]+)+/u', '[e-mail]', $s);
        $s = (string) preg_replace('/\b\d{1,3}(\.\d{1,3}){3}(:\d{1,5})?\b/', '[ip]', $s);
        $s = (string) preg_replace('/\b(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{1,4}\b/i', '[ip]', $s);
        $s = trim($s);

        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '…' : $s;
    }

    /** Échappe % _ et ! pour un LIKE ... ESCAPE '!' (portable MySQL / SQLite). */
    public static function like(string $s): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr(trim($s), 0, 64)) . '%';
    }

    /** État effectif d'une sanction : active | expired | revoked. */
    public static function stateOf(array $r, int $now): string
    {
        if (! empty($r['revoked_at'])) {
            return 'revoked';
        }
        $exp = $r['expires_at'] ?? null;
        if (! empty($r['active']) && ($exp === null || (int) $exp > $now)) {
            return 'active';
        }

        return 'expired';
    }

    // ── Connexion / état ────────────────────────────────────────────────

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
     * @return array{state:string,sanctions:bool,reports:bool,ips:bool}
     */
    public function status(): array
    {
        return Cache::remember('geo_moderation_status_' . $this->ns(), self::TTL, function () {
            $st = ['state' => 'unconfigured', 'sanctions' => false, 'reports' => false, 'ips' => false];
            if (trim((string) config('database.connections.game.database', '')) === '') {
                return $st;
            }
            try {
                $this->conn()->select('select 1');
            } catch (\Throwable $e) {
                Log::info('GameModeration: base du jeu injoignable : ' . $e->getMessage());
                $st['state'] = 'unreachable';

                return $st;
            }
            try {
                $schema = Schema::connection('game');
                $st['sanctions'] = $schema->hasTable('gf_sanctions');
                $st['reports'] = $schema->hasTable('gf_reports');
                $st['ips'] = $schema->hasTable('gf_player_ips');
            } catch (\Throwable $e) {
                Log::info('GameModeration: lecture du schéma impossible : ' . $e->getMessage());
                $st['state'] = 'unreachable';

                return $st;
            }
            $st['state'] = ($st['sanctions'] || $st['reports']) ? 'ok' : 'no_tables';

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
        $k = 'geo_mod_' . $this->ns() . '_' . md5(json_encode($key));
        try {
            $hit = Cache::get($k);
            if ($hit !== null) {
                return $hit;
            }
            $val = $fn();
            Cache::put($k, $val, self::TTL);

            return $val;
        } catch (\Throwable $e) {
            Log::warning('GameModeration: ' . $e->getMessage());

            return $default;
        }
    }

    // ── Sanctions ───────────────────────────────────────────────────────

    /** Filtres normalisés (liste blanche) à partir de valeurs brutes. */
    public static function sanctionFilters(array $in): array
    {
        $type = (string) ($in['type'] ?? '');
        $state = (string) ($in['state'] ?? '');
        $ts = function ($v, bool $end) {
            if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            try {
                $d = \Carbon\Carbon::parse($v, GameAnalytics::TZ);
            } catch (\Throwable) {
                return null;
            }

            return ($end ? $d->endOfDay() : $d->startOfDay())->timestamp;
        };

        return [
            'type' => in_array($type, self::TYPES, true) ? $type : '',
            'state' => in_array($state, self::STATES, true) ? $state : '',
            'player' => mb_substr(trim((string) ($in['player'] ?? '')), 0, 64),
            'staff' => mb_substr(trim((string) ($in['staff'] ?? '')), 0, 64),
            'from' => $ts($in['from'] ?? null, false),
            'to' => $ts($in['to'] ?? null, true),
        ];
    }

    private function applySanctionFilters($q, array $f, int $now)
    {
        if ($f['type'] !== '') {
            $q->where('type', $f['type']);
        }
        if ($f['player'] !== '') {
            if (self::isUuid($f['player'])) {
                $q->whereIn('player_uuid', array_values(array_unique([self::dashUuid($f['player']), strtolower(str_replace('-', '', $f['player']))])));
            } else {
                $q->whereRaw('lower(player_name) like ? escape \'!\'', [mb_strtolower(self::like($f['player']))]);
            }
        }
        if ($f['staff'] !== '') {
            $q->whereRaw('lower(staff_name) like ? escape \'!\'', [mb_strtolower(self::like($f['staff']))]);
        }
        if ($f['from'] !== null) {
            $q->where('created_at', '>=', $f['from']);
        }
        if ($f['to'] !== null) {
            $q->where('created_at', '<=', $f['to']);
        }
        switch ($f['state']) {
            case 'active':
                $q->whereNull('revoked_at')->where('active', 1)->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now));
                break;
            case 'revoked':
                $q->whereNotNull('revoked_at');
                break;
            case 'expired':
                $q->whereNull('revoked_at')->where(fn ($w) => $w->whereNull('active')->orWhere('active', 0)->orWhere('expires_at', '<=', $now));
                break;
        }

        return $q;
    }

    private function mapSanction(object $o, int $now): array
    {
        $r = (array) $o;
        $r['id'] = (int) $r['id'];
        $r['created_at'] = (int) $r['created_at'];
        $r['expires_at'] = isset($r['expires_at']) && $r['expires_at'] !== '' ? (int) $r['expires_at'] : null;
        $r['revoked_at'] = ! empty($r['revoked_at']) ? (int) $r['revoked_at'] : null;
        $r['active'] = (int) ($r['active'] ?? 0);
        $r['state'] = self::stateOf($r, $now);
        $r['type'] = strtolower(self::scrub((string) ($r['type'] ?? ''), 16));
        $r['type_label'] = in_array($r['type'], self::TYPES, true) ? __('moderation.type.' . $r['type']) : $r['type'];
        foreach (['reason', 'revoke_reason'] as $k) {
            $r[$k] = self::scrub($r[$k] ?? '', 500);
        }
        foreach (['player_name', 'staff_name', 'revoked_by'] as $k) {
            $r[$k] = self::scrub($r[$k] ?? '', 64);
        }
        $r['player_label'] = $r['player_name'] !== '' ? $r['player_name'] : self::shortUuid((string) ($r['player_uuid'] ?? ''));

        return $r;
    }

    /** @return array{rows:array<int,array>,total:int} */
    public function sanctions(array $f, int $limit = self::PER_PAGE, int $offset = 0): array
    {
        $empty = ['rows' => [], 'total' => 0];
        if (! $this->usable('sanctions')) {
            return $empty;
        }
        $now = $this->now();
        $limit = max(1, min($limit, self::EXPORT_MAX));

        return $this->cached(['sanctions', $f, $limit, $offset], function () use ($f, $limit, $offset, $now) {
            $total = $this->applySanctionFilters($this->q('gf_sanctions'), $f, $now)->count();
            $rows = $this->applySanctionFilters($this->q('gf_sanctions'), $f, $now)->select(self::S_COLS)
                ->orderByDesc('created_at')->orderByDesc('id')->offset(max(0, $offset))->limit($limit)->get()
                ->map(fn ($o) => $this->mapSanction($o, $now))->all();

            return ['rows' => $rows, 'total' => $total];
        }, $empty);
    }

    public function sanction(int $id): ?array
    {
        if (! $this->usable('sanctions') || $id < 1) {
            return null;
        }
        $now = $this->now();

        return $this->cached(['sanction', $id], function () use ($id, $now) {
            $o = $this->q('gf_sanctions')->select(self::S_COLS)->where('id', $id)->first();

            return $o ? $this->mapSanction($o, $now) : false;
        }, false) ?: null;
    }

    // ── Signalements ────────────────────────────────────────────────────

    public static function reportFilters(array $in): array
    {
        $status = (string) ($in['status'] ?? '');
        $sf = self::sanctionFilters($in);

        return [
            'status' => in_array($status, self::REPORT_STATUSES, true) ? $status : '',
            'target' => mb_substr(trim((string) ($in['target'] ?? '')), 0, 64),
            'from' => $sf['from'],
            'to' => $sf['to'],
        ];
    }

    private function applyReportFilters($q, array $f)
    {
        if ($f['status'] !== '') {
            $q->where('status', $f['status']);
        }
        if ($f['target'] !== '') {
            $q->whereRaw('lower(target_name) like ? escape \'!\'', [mb_strtolower(self::like($f['target']))]);
        }
        if ($f['from'] !== null) {
            $q->where('created_at', '>=', $f['from']);
        }
        if ($f['to'] !== null) {
            $q->where('created_at', '<=', $f['to']);
        }

        return $q;
    }

    private function mapReport(object $o, bool $withContext = false, ?string $context = null): array
    {
        $r = (array) $o;
        $r['id'] = (int) $r['id'];
        $r['created_at'] = (int) $r['created_at'];
        $r['closed_at'] = ! empty($r['closed_at']) ? (int) $r['closed_at'] : null;
        $r['status'] = in_array($r['status'] ?? '', self::REPORT_STATUSES, true) ? $r['status'] : 'open';
        $r['reason'] = self::scrub($r['reason'] ?? '', 500);
        $r['note'] = self::scrub($r['note'] ?? '', 1000);
        foreach (['reporter_name', 'target_name', 'handled_by', 'world'] as $k) {
            $r[$k] = self::scrub($r[$k] ?? '', 64);
        }
        foreach (['x', 'y', 'z'] as $k) {
            $r[$k] = isset($r[$k]) && is_numeric($r[$k]) ? (int) round((float) $r[$k]) : null;
        }
        $r['reporter_label'] = $r['reporter_name'] !== '' ? $r['reporter_name'] : self::shortUuid((string) ($r['reporter_uuid'] ?? ''));
        $r['context'] = $withContext ? self::scrub($context, 4000) : null;
        unset($r['reporter_uuid']);

        return $r;
    }

    /** @return array{rows:array<int,array>,total:int} */
    public function reports(array $f, int $limit = self::PER_PAGE, int $offset = 0): array
    {
        $empty = ['rows' => [], 'total' => 0];
        if (! $this->usable('reports')) {
            return $empty;
        }

        return $this->cached(['reports', $f, $limit, $offset], function () use ($f, $limit, $offset) {
            $total = $this->applyReportFilters($this->q('gf_reports'), $f)->count();
            $rows = $this->applyReportFilters($this->q('gf_reports'), $f)->select(self::R_COLS)
                ->orderByRaw("case status when 'open' then 0 when 'taken' then 1 else 2 end")
                ->orderByDesc('created_at')->orderByDesc('id')->offset(max(0, $offset))->limit(max(1, min($limit, 200)))->get()
                ->map(fn ($o) => $this->mapReport($o))->all();

            return ['rows' => $rows, 'total' => $total];
        }, $empty);
    }

    public function report(int $id): ?array
    {
        if (! $this->usable('reports') || $id < 1) {
            return null;
        }

        return $this->cached(['report', $id], function () use ($id) {
            $o = $this->q('gf_reports')->select([...self::R_COLS, 'context'])->where('id', $id)->first();

            return $o ? $this->mapReport($o, true, $o->context) : false;
        }, false) ?: null;
    }

    // ── Fiche joueur ────────────────────────────────────────────────────

    /**
     * Fiche d'un joueur (par pseudo exact insensible à la casse, ou UUID).
     *
     * @return array{found:bool,name:string,uuid:?string,sanctions:array,counts:array,active:array,reports_in:array,reports_out:array,reports_in_total:int,reports_out_total:int}
     */
    public function player(string $key): array
    {
        $empty = ['found' => false, 'name' => $key, 'uuid' => null, 'sanctions' => [], 'counts' => [], 'active' => [],
            'reports_in' => [], 'reports_out' => [], 'reports_in_total' => 0, 'reports_out_total' => 0];
        if (($key === '' || ! self::validName($key)) || ! $this->usable('sanctions') && ! $this->usable('reports')) {
            return $empty;
        }
        $now = $this->now();

        return $this->cached(['player', mb_strtolower($key)], function () use ($key, $now, $empty) {
            $isUuid = self::isUuid($key);
            $uuids = $isUuid ? array_values(array_unique([self::dashUuid($key), strtolower(str_replace('-', '', $key))])) : [];
            $lname = mb_strtolower($key);
            $out = $empty;
            $out['sanctions'] = [];

            if ($this->usable('sanctions')) {
                $q = $this->q('gf_sanctions')->select(self::S_COLS);
                $isUuid ? $q->whereIn('player_uuid', $uuids) : $q->whereRaw('lower(player_name) = ?', [$lname]);
                $out['sanctions'] = $q->orderByDesc('created_at')->orderByDesc('id')->limit(300)->get()->map(fn ($o) => $this->mapSanction($o, $now))->all();
                if ($out['sanctions']) {
                    $out['uuid'] = (string) ($out['sanctions'][0]['player_uuid'] ?? '') ?: null;
                    $out['name'] = $out['sanctions'][0]['player_name'] !== '' ? $out['sanctions'][0]['player_name'] : $key;
                    $out['found'] = true;
                }
            }
            $counts = array_fill_keys(self::TYPES, 0);
            foreach ($out['sanctions'] as $s) {
                if (isset($counts[$s['type']])) {
                    $counts[$s['type']]++;
                }
                if ($s['state'] === 'active') {
                    $out['active'][] = $s;
                }
            }
            $out['counts'] = $counts;

            if ($this->usable('reports')) {
                $names = array_values(array_unique(array_filter([$lname, mb_strtolower($out['name'])])));
                $in = $this->q('gf_reports')->select(self::R_COLS)->whereRaw('lower(target_name) in (' . implode(',', array_fill(0, count($names), '?')) . ')', $names);
                $out['reports_in_total'] = (clone $in)->count();
                $out['reports_in'] = $in->orderByDesc('created_at')->limit(50)->get()->map(fn ($o) => $this->mapReport($o))->all();

                $outq = $this->q('gf_reports')->select(self::R_COLS)->where(function ($w) use ($names, $out) {
                    $w->whereRaw('lower(reporter_name) in (' . implode(',', array_fill(0, count($names), '?')) . ')', $names);
                    if ($out['uuid']) {
                        $w->orWhere('reporter_uuid', $out['uuid']);
                    }
                    if ($this->isUuid($out['uuid'] ?? '')) {
                        $w->orWhere('reporter_uuid', strtolower(str_replace('-', '', $out['uuid'])));
                    }
                });
                $out['reports_out_total'] = (clone $outq)->count();
                $out['reports_out'] = $outq->orderByDesc('created_at')->limit(50)->get()->map(fn ($o) => $this->mapReport($o))->all();
                if (! $out['found'] && ($out['reports_in'] || $out['reports_out'])) {
                    $out['found'] = true;
                    $first = $out['reports_in'][0] ?? null;
                    $out['name'] = $first ? $first['target_name'] : $key;
                }
            }

            return $out;
        }, $empty);
    }

    /**
     * Comptes liés par adresse (INDICE, pas une preuve) : pseudos (ou UUID raccourcis) qui ont partagé
     * au moins une adresse avec ce joueur. Ni IP ni hash en sortie.
     *
     * @return array<int,array{label:string,uuid:string,shared:int,banned:bool}>
     */
    public function linkedAccounts(?string $uuid): array
    {
        if (! $uuid || ! $this->usable('ips')) {
            return [];
        }
        $mine = array_values(array_unique([self::dashUuid($uuid), strtolower(str_replace('-', '', $uuid))]));

        return $this->cached(['linked', $mine], function () use ($mine) {
            $rows = $this->conn()->table('gf_player_ips as a')
                ->join('gf_player_ips as b', 'a.ip_hash', '=', 'b.ip_hash')
                ->whereIn('a.player_uuid', $mine)->whereNotIn('b.player_uuid', $mine)
                ->selectRaw('b.player_uuid as uuid, count(distinct a.ip_hash) as shared')
                ->groupBy('b.player_uuid')->orderByDesc('shared')->limit(25)->get();
            if ($rows->isEmpty()) {
                return [];
            }
            $uuids = $rows->pluck('uuid')->map(fn ($u) => (string) $u)->all();
            $names = $this->namesFor($uuids);
            $banned = [];
            if ($this->usable('sanctions')) {
                $now = $this->now();
                $banned = $this->q('gf_sanctions')->whereIn('player_uuid', $uuids)->where('type', 'ban')->whereNull('revoked_at')->where('active', 1)
                    ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now))->pluck('player_uuid')->map(fn ($u) => (string) $u)->all();
            }

            return $rows->map(fn ($r) => [
                'uuid' => (string) $r->uuid,
                'label' => $names[(string) $r->uuid] ?? self::shortUuid((string) $r->uuid),
                'shared' => (int) $r->shared,
                'banned' => in_array((string) $r->uuid, $banned, true),
            ])->all();
        }, []);
    }

    /** uuid => dernier pseudo connu (sanctions, puis signalements émis). @param string[] $uuids @return array<string,string> */
    private function namesFor(array $uuids): array
    {
        $names = [];
        if ($this->usable('sanctions')) {
            foreach ($this->q('gf_sanctions')->whereIn('player_uuid', $uuids)->orderBy('created_at')->get(['player_uuid', 'player_name']) as $r) {
                if ((string) $r->player_name !== '') {
                    $names[(string) $r->player_uuid] = self::scrub((string) $r->player_name, 64);
                }
            }
        }
        if ($this->usable('reports')) {
            foreach ($this->q('gf_reports')->whereIn('reporter_uuid', $uuids)->orderBy('created_at')->get(['reporter_uuid', 'reporter_name']) as $r) {
                $u = (string) $r->reporter_uuid;
                if (! isset($names[$u]) && (string) $r->reporter_name !== '') {
                    $names[$u] = self::scrub((string) $r->reporter_name, 64);
                }
            }
        }

        return $names;
    }

    // ── Statistiques ────────────────────────────────────────────────────

    /**
     * @return array{active_bans:int,active_mutes:int,s24:int,s7:int,s30:int,reports_open:int,reports_taken:int,reports_30:int,avg_handle_s:?int,
     *               by_day:array<string,int>,by_type:array<string,int>,by_staff:array<int,array{name:string,n:int}>,top_players:array<int,array{label:string,name:string,n:int,last:int}>}
     */
    public function stats(): array
    {
        $empty = ['active_bans' => 0, 'active_mutes' => 0, 's24' => 0, 's7' => 0, 's30' => 0, 'reports_open' => 0, 'reports_taken' => 0, 'reports_30' => 0,
            'avg_handle_s' => null, 'by_day' => [], 'by_type' => array_fill_keys(self::TYPES, 0), 'by_staff' => [], 'top_players' => []];
        if ($this->status()['state'] !== 'ok') {
            return $empty;
        }
        $now = $this->now();

        return $this->cached(['stats'], function () use ($empty, $now) {
            $o = $empty;
            $d30 = $now - 30 * 86400;
            if ($this->usable('sanctions')) {
                $act = fn () => $this->q('gf_sanctions')->whereNull('revoked_at')->where('active', 1)->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now));
                $o['active_bans'] = $act()->where('type', 'ban')->count();
                $o['active_mutes'] = $act()->where('type', 'mute')->count();
                $o['s24'] = $this->q('gf_sanctions')->where('created_at', '>=', $now - 86400)->count();
                $o['s7'] = $this->q('gf_sanctions')->where('created_at', '>=', $now - 7 * 86400)->count();
                $o['s30'] = $this->q('gf_sanctions')->where('created_at', '>=', $d30)->count();
                $days = [];
                for ($i = 29; $i >= 0; $i--) {
                    $days[\Carbon\Carbon::createFromTimestamp($now - $i * 86400, GameAnalytics::TZ)->format('Y-m-d')] = 0;
                }
                foreach ($this->q('gf_sanctions')->where('created_at', '>=', $d30)->orderBy('created_at')->limit(50000)->pluck('created_at') as $ts) {
                    $k = \Carbon\Carbon::createFromTimestamp((int) $ts, GameAnalytics::TZ)->format('Y-m-d');
                    if (isset($days[$k])) {
                        $days[$k]++;
                    }
                }
                $o['by_day'] = $days;
                foreach ($this->q('gf_sanctions')->where('created_at', '>=', $d30)->selectRaw('type, count(*) as n')->groupBy('type')->get() as $r) {
                    if (isset($o['by_type'][$r->type])) {
                        $o['by_type'][$r->type] = (int) $r->n;
                    }
                }
                $o['by_staff'] = $this->q('gf_sanctions')->where('created_at', '>=', $d30)->whereNotNull('staff_name')->where('staff_name', '!=', '')
                    ->selectRaw('staff_name as name, count(*) as n')->groupBy('staff_name')->orderByDesc('n')->limit(10)->get()
                    ->map(fn ($r) => ['name' => self::scrub((string) $r->name, 64), 'n' => (int) $r->n])->all();
                $o['top_players'] = $this->q('gf_sanctions')->selectRaw('player_uuid, max(player_name) as name, count(*) as n, max(created_at) as last')
                    ->groupBy('player_uuid')->orderByDesc('n')->orderByDesc('last')->limit(10)->get()
                    ->map(function ($r) {
                        $n = self::scrub((string) $r->name, 64);

                        return ['label' => $n !== '' ? $n : self::shortUuid((string) $r->player_uuid), 'name' => $n, 'n' => (int) $r->n, 'last' => (int) $r->last];
                    })->all();
            }
            if ($this->usable('reports')) {
                $o['reports_open'] = $this->q('gf_reports')->where('status', 'open')->count();
                $o['reports_taken'] = $this->q('gf_reports')->where('status', 'taken')->count();
                $o['reports_30'] = $this->q('gf_reports')->where('created_at', '>=', $d30)->count();
                $avg = $this->q('gf_reports')->where('status', 'closed')->whereNotNull('closed_at')->where('closed_at', '>=', $d30)
                    ->whereColumn('closed_at', '>=', 'created_at')->selectRaw('avg(closed_at - created_at) as a')->value('a');
                $o['avg_handle_s'] = $avg !== null ? (int) round((float) $avg) : null;
            }

            return $o;
        }, $empty);
    }

    /** Dernières sanctions (page d'accueil et MCP). @return array<int,array> */
    public function recentSanctions(int $n = 8): array
    {
        return $this->sanctions(self::sanctionFilters([]), $n, 0)['rows'];
    }

    public function openReports(int $n = 8): array
    {
        return $this->reports(['status' => 'open', 'target' => '', 'from' => null, 'to' => null], $n, 0)['rows'];
    }

    /** Durée lisible : 3 j 4 h / 2 h 10 min / 45 s. */
    public static function duration(?int $s): string
    {
        if ($s === null) {
            return '—';
        }
        $s = max(0, $s);
        if ($s >= 86400) {
            return intdiv($s, 86400) . ' j ' . intdiv($s % 86400, 3600) . ' h';
        }
        if ($s >= 3600) {
            return intdiv($s, 3600) . ' h ' . intdiv($s % 3600, 60) . ' min';
        }

        return $s >= 60 ? intdiv($s, 60) . ' min' : $s . ' s';
    }
}
