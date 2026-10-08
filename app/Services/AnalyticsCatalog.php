<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue de NOMS : traduit les identifiants techniques du plugin (recherches,
 * zones de pétrole, ogives, armes, mondes, métriques…) en libellés lisibles.
 *
 * Source : table `gf_catalog(kind, id, label, extra JSON NULL, updated_at)` de la
 * base du jeu (lecture seule). Règles :
 *  - cache 5 min (tout le catalogue en une requête) ;
 *  - FAIL-SAFE : table absente, base injoignable ou erreur = catalogue vide, jamais d'exception ;
 *  - repli lisible quand l'identifiant est inconnu : `ballistix:nuclear_bomb` -> « Nuclear bomb »,
 *    UUID -> « #1a2b3c4d » (jamais l'identifiant brut).
 */
class AnalyticsCatalog
{
    public const KINDS = ['research', 'branch', 'age', 'oil_zone', 'faction', 'warhead', 'carrier', 'weapon', 'crate', 'item', 'world',
        'warzone', 'achievement', 'event_type', 'metric'];

    private const TTL = 300;

    /** @var array<string,array<string,array{label:string,extra:array}>>|null */
    private static ?array $memo = null;

    private static ?string $memoNs = null;

    public function __construct(private GameAnalytics $a)
    {
    }

    public static function get(): self
    {
        return app(self::class);
    }

    /** Oublie le cache (tests). */
    public static function flush(): void
    {
        self::$memo = null;
    }

    // ── Lecture ─────────────────────────────────────────────────────────

    /** @return array<string,array<string,array{label:string,extra:array}>> */
    public function all(): array
    {
        $ns = $this->a->namespaceKey();
        if (self::$memo !== null && self::$memoNs === $ns) {
            return self::$memo;
        }
        self::$memoNs = $ns;
        $out = [];
        try {
            if ($this->a->status()['state'] === 'ok') {
                $out = Cache::remember('geo_an_catalog_' . $ns, self::TTL, fn () => $this->load());
            }
        } catch (\Throwable $e) {
            Log::info('AnalyticsCatalog: ' . $e->getMessage());
        }

        return self::$memo = is_array($out) ? $out : [];
    }

    /** Vrai si la table gf_catalog existe et contient quelque chose. */
    public function available(): bool
    {
        return $this->all() !== [];
    }

    private function load(): array
    {
        $conn = DB::connection('game');
        if (! Schema::connection('game')->hasTable('gf_catalog')) {
            return [];
        }
        $out = [];
        foreach ($conn->table('gf_catalog')->limit(20000)->get(['kind', 'id', 'label', 'extra']) as $r) {
            $x = is_string($r->extra) && $r->extra !== '' ? json_decode($r->extra, true) : null;
            if (! is_array($x)) {
                $x = is_string($r->extra) && $r->extra !== '' ? ['raw' => $r->extra] : [];
            }
            $out[(string) $r->kind][(string) $r->id] = ['label' => (string) $r->label, 'extra' => $x];
        }

        return $out;
    }

    /** Libellé du catalogue (ou null si inconnu). */
    public function find(string $kind, string $id): ?string
    {
        $l = $this->all()[$kind][$id]['label'] ?? null;

        return is_string($l) && trim($l) !== '' ? $l : null;
    }

    /** @return array<string,mixed> */
    public function extra(string $kind, string $id): array
    {
        return $this->all()[$kind][$id]['extra'] ?? [];
    }

    /**
     * Nom lisible : catalogue, sinon $fallback (déjà lisible, ex. `data.name`), sinon repli propre.
     */
    public function label(string $kind, string $id, ?string $fallback = null): string
    {
        if ($id === '') {
            return $fallback !== null && $fallback !== '' ? $fallback : '—';
        }

        return $this->find($kind, $id) ?? (($fallback !== null && trim($fallback) !== '') ? $fallback : self::humanize($id));
    }

    /** Essaie plusieurs catalogues (ex. une arme peut être un `weapon` ou un `item`). */
    public function labelAny(array $kinds, string $id, ?string $fallback = null): string
    {
        foreach ($kinds as $k) {
            if (($l = $this->find($k, $id)) !== null) {
                return $l;
            }
        }
        if ($id === '') {
            return $fallback !== null && $fallback !== '' ? $fallback : '—';
        }

        return ($fallback !== null && trim($fallback) !== '') ? $fallback : self::humanize($id);
    }

    // ── Noms métier ─────────────────────────────────────────────────────

    /** Nom d'un pays (ref = nom ou, éventuellement, identifiant résolu par le catalogue). */
    public function faction(?string $ref): string
    {
        $ref = (string) $ref;
        if ($ref === '') {
            return '—';
        }
        if (($l = $this->find('faction', $ref)) !== null) {
            return $l;
        }

        return self::isUuid($ref) ? self::humanize($ref) : $ref;
    }

    public function metric(string $m): string
    {
        if (($l = $this->find('metric', $m)) !== null) {
            return $l;
        }
        $k = 'analytics.m.' . $m;
        $t = __($k);

        return $t === $k ? self::humanize($m) : $t;
    }

    /** Unité d'une métrique (catalogue puis table locale) ; '' si aucune. */
    public function unit(string $m): string
    {
        $x = $this->extra('metric', $m);
        $u = $x['unit'] ?? $x['raw'] ?? null;
        if (is_string($u)) {
            return trim($u);
        }

        return GameAnalytics::UNITS[$m] ?? '';
    }

    public function eventType(string $type): string
    {
        if (($l = $this->find('event_type', $type)) !== null) {
            return $l;
        }
        $k = 'analytics.e.' . $type;
        $t = __($k);

        return $t === $k ? self::humanize($type) : $t;
    }

    public function branch(string $b): string
    {
        $b = trim($b);
        if ($b === '') {
            return __('analytics.branch.other');
        }
        $k = 'analytics.branch.' . strtolower($b);
        $l = $this->find('branch', $b) ?? $this->find('branch', strtoupper($b)) ?? $this->find('branch', strtolower($b));

        return $l ?? (__($k) !== $k ? __($k) : self::humanize($b));
    }

    public function world(string $w): string
    {
        if (($l = $this->find('world', $w)) !== null) {
            return $l;
        }
        $k = 'analytics.worlds.' . strtolower($w);
        $t = __($k);

        return $t === $k ? self::humanize($w) : $t;
    }

    /** Valeur « textuelle » générique : mot traduit (analytics.v.*) sinon repli propre. */
    public function word(string $v): string
    {
        $k = 'analytics.v.' . strtolower($v);
        $t = __($k);

        return $t === $k ? self::humanize($v) : $t;
    }

    public function rarity(string $v): string
    {
        $k = 'analytics.rarity.' . strtolower($v);
        $t = __($k);

        return $t === $k ? self::humanize($v) : $t;
    }

    // ── Événements ──────────────────────────────────────────────────────

    /** Clés techniques jamais affichées (identifiants internes). */
    private const HIDDEN_KEYS = ['missile_id', 'uuid', 'ref', 'zone_id', 'wid', 'war_id', 'siege_id'];

    private const FACTION_KEYS = ['target', 'with', 'from', 'winner', 'loser', 'target_faction', 'defense_faction', 'damage_zone_faction', 'attacker', 'defender',
        'country', 'faction', 'a', 'b', 'owner_country', 'from_faction', 'to_faction'];

    /**
     * Paires [libellé, valeur] lisibles pour le JSON d'un événement.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public function describe(string $type, array $d): array
    {
        $out = [];
        $hasName = isset($d['name']) && is_string($d['name']) && $d['name'] !== '';
        foreach ($d as $k => $v) {
            $k = (string) $k;
            if (in_array($k, self::HIDDEN_KEYS, true) || ($k === 'id' && ($hasName || $type !== 'research_unlocked'))) {
                continue;
            }
            if (is_array($v)) {
                if (isset($v['x'], $v['z'])) {
                    $w = isset($v['world']) && is_string($v['world']) ? $this->world($v['world']) . ' ' : '';
                    $out[] = [$this->key($k), $w . GameAnalytics::num($v['x']) . ' / ' . GameAnalytics::num($v['z'])];
                }

                continue;
            }
            $out[] = [$this->key($k), $this->value($type, $k, $v)];
        }

        return $out;
    }

    private function key(string $k): string
    {
        $t = __('analytics.k.' . $k);

        return $t === 'analytics.k.' . $k ? self::humanize($k) : $t;
    }

    private function value(string $type, string $k, $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? __('analytics.yes') : __('analytics.no');
        }
        if (is_numeric($v) && ! is_string($v)) {
            return str_ends_with($k, '_s') || $k === 'flight_s' ? $this->duration((float) $v) : GameAnalytics::num($v);
        }
        $v = (string) $v;
        if (is_numeric($v)) {
            return str_ends_with($k, '_s') ? $this->duration((float) $v) : GameAnalytics::num((float) $v);
        }
        if (in_array($k, self::FACTION_KEYS, true)) {
            return $this->faction($v);
        }

        return match ($k) {
            'name', 'label', 'title' => $v,
            'id' => $this->label('research', $v),
            'branch', 'category' => $this->branch($v),
            'warhead', 'blast' => $this->label('warhead', $v),
            'carrier' => $this->label('carrier', $v),
            'weapon' => $this->label('weapon', $v),
            'world', 'dimension' => $this->world($v),
            'zone', 'oil_zone' => $this->label('oil_zone', $v),
            'boss', 'item', 'crate', 'warzone', 'achievement', 'code' => $this->labelAny([$k === 'code' ? 'achievement' : $k, 'item'], $v),
            default => $type === 'age_changed' && in_array($k, ['from', 'to'], true) ? $this->label('age', $v) : $this->word($v),
        };
    }

    private function duration(float $s): string
    {
        if ($s >= 3600) {
            return GameAnalytics::num(round($s / 3600, 1)) . ' h';
        }
        if ($s >= 120) {
            return GameAnalytics::num(round($s / 60)) . ' min';
        }

        return GameAnalytics::num(round($s)) . ' s';
    }

    /** « Libellé : valeur · … » en une ligne (tableaux, CSV). */
    public function summarize(string $type, array $d, int $max = 160): string
    {
        $parts = [];
        foreach ($this->describe($type, $d) as [$k, $v]) {
            $parts[] = $k . ' : ' . $v;
        }

        return mb_strimwidth(implode(' · ', $parts), 0, $max, '…');
    }

    // ── Repli lisible ───────────────────────────────────────────────────

    public static function isUuid(string $s): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i', $s);
    }

    /**
     * `ballistix:nuclear_bomb` -> « Nuclear bomb » ; UUID -> « #1a2b3c4d » ; `ZoneNord` -> « Zone nord ».
     */
    public static function humanize(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '—';
        }
        if (self::isUuid($id)) {
            return '#' . strtolower(substr(str_replace('-', '', $id), 0, 8));
        }
        if (str_contains($id, ':')) {
            $id = substr($id, (int) strrpos($id, ':') + 1);
        }
        $id = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $id);
        $s = trim(preg_replace('/[\s_\-.]+/', ' ', $id));
        if ($s === '') {
            return '—';
        }

        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_strtolower(mb_substr($s, 1));
    }
}
