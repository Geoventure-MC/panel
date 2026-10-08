<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GameAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Geoventure Analytics » : pages d'analyse lues dans la base du jeu
 * (gf_metrics / gf_events / gf_rollup, voir App\Services\GameAnalytics).
 *
 * Toutes les pages sont fail-safe : base absente, injoignable ou tables
 * absentes => écran explicatif (admin.analytics.unavailable), jamais de 500.
 * Les KPI et tableaux sont rendus côté serveur ; les courbes sont chargées par
 * public/assets/js/analytics.js via GET /admin/analytics/data.
 */
class AnalyticsController extends Controller
{
    public const COLORS = ['#4ade80', '#a78bfa', '#fb923c', '#60a5fa', '#f472b6', '#facc15', '#34d399', '#f87171'];

    public const TIER_COLORS = [1 => '#60a5fa', 2 => '#4ade80', 3 => '#facc15', 4 => '#fb923c', 5 => '#f87171'];

    private const MISSILE_TYPES = ['missile_launched', 'missile_refused', 'missile_intercepted', 'missile_impact', 'missile_shot_down_native'];

    private const FACTION_METRICS = ['power', 'members', 'members_online', 'claims', 'bank', 'research_points', 'research_unlocked',
        'age_index', 'wars_active', 'emissions', 'rating_week', 'convoys_active', 'plots', 'playtime_hours_week'];

    public function __construct(private GameAnalytics $a)
    {
    }

    // ── Contexte commun ─────────────────────────────────────────────────

    private function ctx(Request $r, string $page): array
    {
        $period = GameAnalytics::period((string) $r->query('period', '7d'));
        [$from, $to] = $this->a->range($period);
        $last = $this->a->lastMetricTs();

        return [
            'page' => $page,
            'period' => $period,
            'periods' => array_keys(GameAnalytics::PERIODS),
            'from' => $from,
            'to' => $to,
            // Fenêtre de « dernière valeur connue » : au moins 7 jours.
            'lb' => $to - max($to - $from, 604800),
            'st' => $this->a->status(),
            'lastTs' => $last,
            'stale' => $last !== null && ($to - $last) > 900,
        ];
    }

    private function render(string $view, array $ctx, array $data = [])
    {
        if ($ctx['st']['state'] !== 'ok') {
            return view('admin.analytics.unavailable', $ctx);
        }

        return view($view, array_merge($ctx, $data));
    }

    /** Spécification d'un graphique (carte + canvas). */
    private function chart(string $title, string $type, array $series, array $o = []): array
    {
        return array_merge(['title' => $title, 'w' => 6, 'h' => 240, 'spec' => array_merge(['type' => $type, 'series' => $series], $o['spec'] ?? [])],
            array_diff_key($o, ['spec' => 1]));
    }

    /** Une série par ref, couleurs de la palette. */
    private function multi(string $scope, string $metric, array $refs, array $o = []): array
    {
        $out = [];
        foreach (array_values($refs) as $i => $ref) {
            $out[] = ['scope' => $scope, 'metric' => $metric, 'ref' => (string) $ref, 'label' => $ref === '' ? __("analytics.m.$metric") : (string) $ref,
                'color' => self::COLORS[$i % count(self::COLORS)]] + $o;
        }

        return $out;
    }

    private function one(string $scope, string $metric, string $ref = '', ?string $color = null, ?string $label = null, array $o = []): array
    {
        return ['scope' => $scope, 'metric' => $metric, 'ref' => $ref, 'label' => $label ?? __("analytics.m.$metric"), 'color' => $color ?? self::COLORS[0]] + $o;
    }

    /** Dernières valeurs d'une liste de métriques : metric => ref => v. */
    private function matrix(string $scope, array $metrics, int $lb): array
    {
        $m = [];
        foreach ($metrics as $metric) {
            $m[$metric] = array_map(fn ($x) => $x['v'], $this->a->latest($scope, $metric, $lb));
        }

        return $m;
    }

    /** Grille de pas de temps couvrant [from, to]. @return int[] */
    private function grid(int $from, int $to, int $bucket): array
    {
        $g = [];
        for ($b = intdiv($from, $bucket) * $bucket; $b <= $to; $b += $bucket) {
            $g[] = $b;
        }

        return $g;
    }

    private function inline(array $labels, array $datasets, array $o = []): array
    {
        return ['inline' => ['labels' => $labels, 'datasets' => $datasets]] + $o;
    }

    // ── Pages ───────────────────────────────────────────────────────────

    public function overview(Request $r)
    {
        $c = $this->ctx($r, 'overview');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $lb = $c['lb'];
        $L = fn (string $s, string $m, string $ref = '') => $this->a->latestOne($s, $m, $lb, $ref);
        $newPlayers = array_sum(array_filter($this->a->series([['scope' => 'players', 'metric' => 'new_players', 'ref' => '']], $c['from'], $c['to'], 86400)['series'][0] ?? [], fn ($v) => $v !== null));
        $ramUsed = $L('server', 'ram_used_mb');
        $ramMax = $L('server', 'ram_max_mb');
        $kpi = [
            ['online', $L('server', 'players_online'), null],
            ['peak', $L('server', 'players_peak_day'), null],
            ['tps', $L('server', 'tps'), null],
            ['mspt', $L('server', 'mspt'), 'ms'],
            ['ram', $ramUsed, $ramMax !== null && $ramMax > 0 && $ramUsed !== null ? round($ramUsed * 100 / $ramMax) . ' %' : null],
            ['new_players', $newPlayers, null],
            ['d1', $L('players', 'retention_d1'), '%'],
            ['d7', $L('players', 'retention_d7'), '%'],
        ];
        $charts = [
            $this->chart(__('analytics.c.players'), 'line', [
                $this->one('server', 'players_online', '', self::COLORS[0], null, ['fill' => true]),
                $this->one('server', 'players_peak_day', '', self::COLORS[2], null, ['agg' => 'max', 'dashed' => true]),
            ]),
            $this->chart(__('analytics.c.tps_mspt'), 'line', [
                $this->one('server', 'tps', '', self::COLORS[1]),
                $this->one('server', 'mspt', '', self::COLORS[2], null, ['axis' => 'right']),
            ], ['spec' => ['dual' => true]]),
            $this->chart(__('analytics.c.ram'), 'line', [
                $this->one('server', 'ram_used_mb', '', self::COLORS[3], null, ['fill' => true]),
                $this->one('server', 'ram_max_mb', '', self::COLORS[7], null, ['dashed' => true]),
            ]),
            $this->chart(__('analytics.c.world'), 'line', [
                $this->one('server', 'entities', '', self::COLORS[4]),
                $this->one('server', 'chunks_loaded', '', self::COLORS[5]),
            ]),
            $this->chart(__('analytics.c.new_players'), 'bar', [
                $this->one('players', 'new_players', '', self::COLORS[0]),
            ]),
            $this->chart(__('analytics.c.retention'), 'line', [
                $this->one('players', 'retention_d1', '', self::COLORS[1]),
                $this->one('players', 'retention_d7', '', self::COLORS[2]),
            ], ['spec' => ['unit' => '%']]),
        ];

        return $this->render('admin.analytics.overview', $c, [
            'kpi' => $kpi, 'charts' => $charts,
            'feed' => $this->a->events([], 15)['rows'],
        ]);
    }

    public function countries(Request $r)
    {
        $c = $this->ctx($r, 'countries');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $mx = $this->matrix('faction', self::FACTION_METRICS, $c['lb']);
        $names = [];
        foreach ($mx as $col) {
            foreach (array_keys($col) as $k) {
                $names[$k] = true;
            }
        }
        $rows = [];
        foreach (array_keys($names) as $n) {
            $row = ['name' => (string) $n];
            foreach (self::FACTION_METRICS as $m) {
                $row[$m] = $mx[$m][$n] ?? null;
            }
            $rows[] = $row;
        }
        usort($rows, fn ($x, $y) => ($y['power'] ?? -1) <=> ($x['power'] ?? -1));

        $all = array_column($rows, 'name');
        $sel = array_values(array_filter((array) $r->query('countries', []), fn ($v) => is_string($v) && in_array($v, $all, true)));
        $sel = array_slice(array_unique($sel), 0, 8);
        if (! $sel) {
            $sel = array_slice($all, 0, 5);
        }
        $defs = [['power', 'line'], ['members', 'line'], ['claims', 'line'], ['bank', 'line'], ['research_points', 'line'], ['age_index', 'line'], ['emissions', 'line'], ['rating_week', 'line']];
        $charts = [];
        foreach ($defs as [$m, $t]) {
            $charts[] = $this->chart(__("analytics.m.$m"), $t, $this->multi('faction', $m, $sel));
        }

        return $this->render('admin.analytics.countries', $c, ['rows' => $rows, 'all' => $all, 'sel' => $sel, 'charts' => $charts]);
    }

    public function country(Request $r, string $country)
    {
        $c = $this->ctx($r, 'countries');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        if (! GameAnalytics::validRef($country) || $country === '*' || $country === '') {
            abort(404);
        }
        $mx = $this->matrix('faction', self::FACTION_METRICS, $c['lb']);
        $now = [];
        foreach (self::FACTION_METRICS as $m) {
            $now[$m] = $mx[$m][$country] ?? null;
        }
        $charts = [];
        foreach ([['power', 0], ['members', 1], ['claims', 2], ['bank', 5], ['research_points', 3], ['age_index', 4], ['emissions', 7], ['members_online', 6]] as [$m, $ci]) {
            $charts[] = $this->chart(__("analytics.m.$m"), 'line', [$this->one('faction', $m, $country, self::COLORS[$ci], null, ['fill' => true])]);
        }
        $types = ['research_unlocked', 'war_declared', 'war_ended', 'age_changed', 'alliance_formed', 'faction_created', 'faction_dissolved', 'siege_started', 'siege_ended', 'treaty_signed', 'peace_bought'];
        $timeline = $this->a->events(['types' => $types, 'ref' => $country, 'from' => $c['from'], 'to' => $c['to']], 120)['rows'];

        return $this->render('admin.analytics.country', $c, ['country' => $country, 'now' => $now, 'charts' => $charts, 'timeline' => $timeline]);
    }

    private function branchOf(array $e): string
    {
        $d = $e['data'];
        $b = $d['branch'] ?? $d['category'] ?? null;
        if (is_string($b) && $b !== '') {
            return strtolower($b);
        }
        $id = strtolower((string) ($d['id'] ?? ''));
        foreach (['economy', 'industry', 'science', 'military', 'culture', 'agriculture', 'commerce'] as $br) {
            if (str_starts_with($id, substr($br, 0, 4))) {
                return $br;
            }
        }

        return 'other';
    }

    public function research(Request $r)
    {
        $c = $this->ctx($r, 'research');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $mx = $this->matrix('faction', ['research_unlocked', 'research_points', 'age_index'], $c['lb']);
        $names = array_unique(array_merge(array_keys($mx['research_unlocked']), array_keys($mx['research_points']), array_keys($mx['age_index'])));
        $adv = [];
        foreach ($names as $n) {
            $adv[] = ['name' => (string) $n, 'unlocked' => $mx['research_unlocked'][$n] ?? null, 'points' => $mx['research_points'][$n] ?? null, 'age' => $mx['age_index'][$n] ?? null];
        }
        usort($adv, fn ($x, $y) => [$y['unlocked'] ?? -1, $y['points'] ?? -1, $y['age'] ?? -1] <=> [$x['unlocked'] ?? -1, $x['points'] ?? -1, $x['age'] ?? -1]);

        $ev = $this->a->eventsOf(['research_unlocked'], $c['from'], $c['to'], 3000);
        $byBranch = [];
        $costBy = [];
        $countBy = [];
        foreach ($ev as $e) {
            $b = $this->branchOf($e);
            $byBranch[$b] = ($byBranch[$b] ?? 0) + 1;
            $countBy[$e['ref']] = ($countBy[$e['ref']] ?? 0) + 1;
            $costBy[$e['ref']] = ($costBy[$e['ref']] ?? 0) + (float) ($e['data']['cost'] ?? 0);
        }
        arsort($byBranch);
        $branchLabels = array_map(fn ($b) => __("analytics.branch.$b") !== "analytics.branch.$b" ? __("analytics.branch.$b") : ucfirst($b), array_keys($byBranch));

        $top = array_slice(array_column($adv, 'name'), 0, 6);
        $ages = $adv;
        usort($ages, fn ($x, $y) => ($y['age'] ?? -1) <=> ($x['age'] ?? -1));
        $charts = [
            $this->chart(__('analytics.c.research_by_branch'), 'hbar', [], ['spec' => $this->inline($branchLabels, [['label' => __('analytics.unlocks'), 'data' => array_values($byBranch), 'color' => self::COLORS[1]]])]),
            $this->chart(__('analytics.c.age_by_country'), 'hbar', [], ['spec' => $this->inline(array_column(array_slice($ages, 0, 12), 'name'), [['label' => __('analytics.m.age_index'), 'data' => array_map(fn ($a) => $a['age'] ?? 0, array_slice($ages, 0, 12)), 'color' => self::COLORS[0]]])]),
            $this->chart(__('analytics.c.research_progress'), 'line', $this->multi('faction', 'research_unlocked', $top), ['w' => 12]),
            $this->chart(__('analytics.c.research_points_progress'), 'line', $this->multi('faction', 'research_points', $top), ['w' => 12]),
        ];

        return $this->render('admin.analytics.research', $c, [
            'adv' => $adv, 'charts' => $charts, 'costBy' => $costBy, 'countBy' => $countBy,
            'timeline' => array_slice(array_reverse($ev), 0, 100), 'branchOf' => fn ($e) => $this->branchOf($e),
        ]);
    }

    public function oil(Request $r)
    {
        $c = $this->ctx($r, 'oil');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $mx = $this->matrix('oil', ['reserve', 'reserve_pct', 'extracted_total', 'wells', 'extracted_24h'], $c['lb']);
        $zones = array_values(array_unique(array_filter(array_merge(array_keys($mx['reserve_pct']), array_keys($mx['reserve']), array_keys($mx['wells'])), fn ($z) => $z !== '')));
        $rows = [];
        foreach ($zones as $z) {
            $rows[] = ['zone' => (string) $z, 'reserve' => $mx['reserve'][$z] ?? null, 'pct' => $mx['reserve_pct'][$z] ?? null, 'total' => $mx['extracted_total'][$z] ?? null, 'wells' => $mx['wells'][$z] ?? null];
        }
        usort($rows, fn ($x, $y) => ($x['pct'] ?? 101) <=> ($y['pct'] ?? 101));
        $depletedEvents = $this->a->events(['types' => ['oil_zone_depleted'], 'from' => 0], 50)['rows'];
        $depleted = array_values(array_unique(array_merge(
            array_column(array_filter($rows, fn ($x) => $x['pct'] !== null && $x['pct'] <= 0.5), 'zone'),
            array_column($depletedEvents, 'ref'),
        )));
        $sel = array_slice($zones, 0, 8);
        $charts = [
            $this->chart(__('analytics.c.oil_pct'), 'line', $this->multi('oil', 'reserve_pct', $sel), ['spec' => ['unit' => '%', 'min' => 0, 'max' => 100]]),
            $this->chart(__('analytics.c.oil_reserve'), 'line', $this->multi('oil', 'reserve', $sel)),
            $this->chart(__('analytics.c.oil_extracted24'), 'line', [$this->one('oil', 'extracted_24h', '', self::COLORS[2], null, ['fill' => true])]),
            $this->chart(__('analytics.c.oil_wells'), 'line', $this->multi('oil', 'wells', $sel)),
        ];

        return $this->render('admin.analytics.oil', $c, [
            'rows' => $rows, 'depleted' => $depleted, 'charts' => $charts, 'extracted24' => $mx['extracted_24h'][''] ?? null,
            'totalExtracted' => array_sum($mx['extracted_total']), 'wellsTotal' => array_sum($mx['wells']),
        ]);
    }

    public function economy(Request $r)
    {
        $c = $this->ctx($r, 'economy');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $metrics = ['money_supply', 'avg_balance', 'hdv_listings', 'hdv_volume_24h', 'bourse_index', 'trade_tax_24h'];
        $mx = $this->matrix('economy', $metrics, $c['lb']);
        $kpi = [];
        $charts = [];
        foreach ($metrics as $i => $m) {
            $kpi[] = [$m, $mx[$m][''] ?? null, null];
            $charts[] = $this->chart(__("analytics.m.$m"), 'line', [$this->one('economy', $m, '', self::COLORS[$i % 8], null, ['fill' => true])]);
        }

        return $this->render('admin.analytics.economy', $c, ['kpi' => $kpi, 'charts' => $charts]);
    }

    public function ecology(Request $r)
    {
        $c = $this->ctx($r, 'ecology');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $mx = $this->matrix('pollution', ['avg_level', 'max_level', 'chunks_over_hazard'], $c['lb']);
        $worlds = array_values(array_filter(array_unique(array_merge(array_keys($mx['avg_level']), array_keys($mx['max_level']))), fn ($w) => $w !== ''));
        $rows = [];
        foreach ($worlds as $w) {
            $rows[] = ['world' => (string) $w, 'avg' => $mx['avg_level'][$w] ?? null, 'max' => $mx['max_level'][$w] ?? null, 'hazard' => $mx['chunks_over_hazard'][$w] ?? null];
        }
        $em = array_map(fn ($x) => $x['v'], $this->a->latest('faction', 'emissions', $c['lb']));
        arsort($em);
        $topEm = array_slice(array_keys($em), 0, 8);
        $meltdowns = $this->a->events(['types' => ['meltdown'], 'from' => $c['from'], 'to' => $c['to']], 30);
        $charts = [
            $this->chart(__('analytics.c.pollution_avg'), 'line', $this->multi('pollution', 'avg_level', $worlds)),
            $this->chart(__('analytics.c.pollution_max'), 'line', $this->multi('pollution', 'max_level', $worlds, ['agg' => 'max'])),
            $this->chart(__('analytics.c.hazard_chunks'), 'line', $this->multi('pollution', 'chunks_over_hazard', $worlds)),
            $this->chart(__('analytics.c.emissions_now'), 'hbar', [], ['spec' => $this->inline(array_map('strval', array_slice(array_keys($em), 0, 12)), [['label' => __('analytics.m.emissions'), 'data' => array_slice(array_values($em), 0, 12), 'color' => self::COLORS[7]]])]),
            $this->chart(__('analytics.c.emissions_curve'), 'line', $this->multi('faction', 'emissions', $topEm), ['w' => 12]),
        ];

        return $this->render('admin.analytics.ecology', $c, ['rows' => $rows, 'charts' => $charts, 'meltdowns' => $meltdowns['rows'], 'meltdownTotal' => $meltdowns['total']]);
    }

    public function players(Request $r)
    {
        $c = $this->ctx($r, 'players');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $metrics = ['dau', 'new_players', 'retention_d1', 'retention_d7', 'avg_session_min'];
        $mx = $this->matrix('players', $metrics, $c['lb']);
        $kpi = [];
        foreach ($metrics as $m) {
            $kpi[] = [$m, $mx[$m][''] ?? null, in_array($m, ['retention_d1', 'retention_d7']) ? '%' : ($m === 'avg_session_min' ? 'min' : null)];
        }
        $charts = [
            $this->chart(__('analytics.m.dau'), 'line', [$this->one('players', 'dau', '', self::COLORS[0], null, ['fill' => true])]),
            $this->chart(__('analytics.m.new_players'), 'bar', [$this->one('players', 'new_players', '', self::COLORS[3])]),
            $this->chart(__('analytics.c.retention'), 'line', [$this->one('players', 'retention_d1', '', self::COLORS[1]), $this->one('players', 'retention_d7', '', self::COLORS[2])], ['spec' => ['unit' => '%']]),
            $this->chart(__('analytics.m.avg_session_min'), 'line', [$this->one('players', 'avg_session_min', '', self::COLORS[5], null, ['fill' => true])], ['spec' => ['unit' => ' min']]),
        ];
        $pt = array_map(fn ($x) => $x['v'], $this->a->latest('faction', 'playtime_hours_week', $c['lb']));
        arsort($pt);
        $charts[] = $this->chart(__('analytics.c.playtime_week'), 'hbar', [], ['w' => 12, 'spec' => $this->inline(array_map('strval', array_slice(array_keys($pt), 0, 12)), [['label' => __('analytics.m.playtime_hours_week'), 'data' => array_slice(array_values($pt), 0, 12), 'color' => self::COLORS[0]]])]);

        return $this->render('admin.analytics.players', $c, ['kpi' => $kpi, 'charts' => $charts]);
    }

    // ── Événements ──────────────────────────────────────────────────────

    private function eventFilters(Request $r, array $c): array
    {
        $type = (string) $r->query('type', '');
        $ref = (string) $r->query('country', '');

        return [
            'types' => GameAnalytics::validName($type) ? [$type] : [],
            'ref' => ($ref !== '' && $ref !== '*' && GameAnalytics::validRef($ref)) ? $ref : null,
            'from' => $c['from'], 'to' => $c['to'],
        ];
    }

    public function events(Request $r)
    {
        $c = $this->ctx($r, 'events');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $f = $this->eventFilters($r, $c);
        $per = 50;
        $page = max(1, min(2000, (int) $r->query('page', 1)));
        $res = $this->a->events($f, $per, ($page - 1) * $per);
        $countries = array_keys($this->a->latest('faction', 'power', $c['lb']));
        sort($countries);

        return $this->render('admin.analytics.events', $c, [
            'rows' => $res['rows'], 'total' => $res['total'], 'pg' => $page, 'per' => $per,
            'types' => $this->a->eventTypes(), 'countries' => $countries,
            'fType' => $f['types'][0] ?? '', 'fRef' => $f['ref'] ?? '',
        ]);
    }

    public function export(Request $r)
    {
        $c = $this->ctx($r, 'events');
        if ($c['st']['state'] !== 'ok') {
            return redirect()->route('admin.analytics.events');
        }
        $f = $this->eventFilters($r, $c);
        $rows = $this->a->events($f, 10000, 0)['rows'];
        $safe = fn ($s) => (is_string($s) && $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) ? "'" . $s : $s;

        return response()->streamDownload(function () use ($rows, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['date', 'timestamp', 'type', 'ref', 'data']);
            foreach ($rows as $e) {
                fputcsv($out, [date('c', $e['ts']), $e['ts'], $safe($e['type']), $safe($e['ref']), $safe(json_encode($e['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))]);
            }
            fclose($out);
        }, 'geoventure-evenements-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Guerre & missiles ───────────────────────────────────────────────

    /** @return ?array{x:float,z:float,world:string} */
    private function point($p): ?array
    {
        if (! is_array($p) || ! is_numeric($p['x'] ?? null) || ! is_numeric($p['z'] ?? null)) {
            return null;
        }

        return ['x' => (float) $p['x'], 'z' => (float) $p['z'], 'world' => (string) ($p['world'] ?? '')];
    }

    private function missileRow(array $e): array
    {
        $d = $e['data'];

        return [
            'id' => $e['id'], 'ts' => $e['ts'], 'ref' => $e['ref'], 'tier' => (int) ($d['tier'] ?? 0),
            'carrier' => (string) ($d['carrier'] ?? ''), 'warhead' => (string) ($d['warhead'] ?? ''), 'flight' => (string) ($d['flight'] ?? ''),
            'airburst' => (bool) ($d['airburst'] ?? false), 'from' => $this->point($d['from'] ?? null), 'target' => $this->point($d['target'] ?? null),
            'target_faction' => (string) ($d['target_faction'] ?? ''), 'distance' => is_numeric($d['distance'] ?? null) ? (float) $d['distance'] : null,
            'eta_s' => is_numeric($d['eta_s'] ?? null) ? (float) $d['eta_s'] : null,
        ];
    }

    public function war(Request $r)
    {
        $c = $this->ctx($r, 'war');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        [$from, $to, $lb] = [$c['from'], $c['to'], $c['lb']];
        $bucket = GameAnalytics::bucketFor($to - $from);
        $grid = $this->grid($from, $to, $bucket);
        $idx = array_flip($grid);
        $zero = fn () => array_fill(0, count($grid), 0);

        $launched = $this->a->eventsOf(['missile_launched'], $from, $to, 2500);
        $refused = $this->a->eventsOf(['missile_refused'], $from, $to, 2500);
        $inter = $this->a->eventsOf(['missile_intercepted'], $from, $to, 2500);
        $impact = $this->a->eventsOf(['missile_impact'], $from, $to, 2500);
        $native = $this->a->eventsOf(['missile_shot_down_native'], $from, $to, 2500);
        $counts = $this->a->eventCounts($from, $to);

        $tiers = [1 => $zero(), 2 => $zero(), 3 => $zero(), 4 => $zero(), 5 => $zero()];
        $nL = $zero();
        $nI = $zero();
        $blocks = $zero();
        $warheads = [];
        $perC = [];
        $bump = function (string $name, string $k) use (&$perC) {
            if ($name !== '') {
                $perC[$name][$k] = ($perC[$name][$k] ?? 0) + 1;
            }
        };
        foreach ($launched as $e) {
            $i = $idx[intdiv($e['ts'], $bucket) * $bucket] ?? null;
            $t = (int) ($e['data']['tier'] ?? 0);
            if ($i !== null) {
                $nL[$i]++;
                if (isset($tiers[$t])) {
                    $tiers[$t][$i]++;
                }
            }
            $w = (string) ($e['data']['warhead'] ?? '?');
            $warheads[$w] = ($warheads[$w] ?? 0) + 1;
            $bump($e['ref'], 'launched');
            $bump((string) ($e['data']['target_faction'] ?? ''), 'targeted');
        }
        $by = [];
        foreach ($inter as $e) {
            $i = $idx[intdiv($e['ts'], $bucket) * $bucket] ?? null;
            if ($i !== null) {
                $nI[$i]++;
            }
            $bump((string) ($e['data']['defense_faction'] ?? $e['ref']), 'intercepted');
            $b = (string) ($e['data']['by'] ?? '?');
            $by[$b] = ($by[$b] ?? 0) + 1;
        }
        $blocksTotal = 0;
        $killsTotal = 0;
        foreach ($impact as $e) {
            $i = $idx[intdiv($e['ts'], $bucket) * $bucket] ?? null;
            $bd = (int) ($e['data']['blocks_destroyed'] ?? 0);
            $blocksTotal += $bd;
            $killsTotal += (int) ($e['data']['kills'] ?? 0);
            if ($i !== null) {
                $blocks[$i] += $bd;
            }
            $bump((string) ($e['data']['damage_zone_faction'] ?? ''), 'hit');
        }
        $reasons = [];
        foreach ($refused as $e) {
            $bump($e['ref'], 'refused');
            $rs = (string) ($e['data']['reason'] ?? '?');
            $reasons[$rs] = ($reasons[$rs] ?? 0) + 1;
        }
        arsort($warheads);
        arsort($reasons);
        arsort($by);

        $rate = [];
        foreach ($grid as $i => $_) {
            $rate[] = $nL[$i] > 0 ? round($nI[$i] * 100 / $nL[$i], 1) : null;
        }
        $rows = [];
        foreach ($perC as $n => $v) {
            $l = $v['launched'] ?? 0;
            $t = $v['targeted'] ?? 0;
            $i = $v['intercepted'] ?? 0;
            $rows[] = ['name' => (string) $n, 'launched' => $l, 'refused' => $v['refused'] ?? 0, 'targeted' => $t, 'intercepted' => $i, 'hit' => $v['hit'] ?? 0,
                'rate' => $t > 0 ? round(min(100, $i * 100 / $t), 1) : null];
        }
        $offense = $rows;
        usort($offense, fn ($x, $y) => $y['launched'] <=> $x['launched']);
        $defense = array_values(array_filter($rows, fn ($x) => $x['targeted'] > 0 || $x['intercepted'] > 0));
        usort($defense, fn ($x, $y) => [$y['rate'] ?? -1, $y['intercepted']] <=> [$x['rate'] ?? -1, $x['intercepted']]);

        // Compteurs glissants 24 h poussés par le plugin (scope missiles / war), par pays.
        $mm = $this->matrix('missiles', ['launched_total', 'launched_24h', 'tier1_24h', 'tier2_24h', 'tier3_24h', 'tier4_24h', 'tier5_24h', 'intercepted_24h', 'hit_24h', 'refused_24h',
            'interception_rate_pct', 'blocks_destroyed_24h', 'defense_radars', 'defense_interceptors', 'defense_ciws'], $lb);
        $wm = $this->matrix('war', ['kills_24h', 'deaths_24h', 'kd_ratio', 'sieges_active', 'assault_gauge_peak', 'annexed_claims', 'reparations_paid'], $lb);
        $mNames = [];
        foreach ($mm as $col) {
            foreach (array_keys($col) as $k) {
                $mNames[$k] = true;
            }
        }
        $wNames = [];
        foreach ($wm as $col) {
            foreach (array_keys($col) as $k) {
                $wNames[$k] = true;
            }
        }
        $metricRows = [];
        foreach (array_keys($mNames) as $n) {
            $row = ['name' => (string) $n];
            foreach ($mm as $m => $col) {
                $row[$m] = $col[$n] ?? null;
            }
            $metricRows[] = $row;
        }
        usort($metricRows, fn ($x, $y) => ($y['launched_24h'] ?? 0) <=> ($x['launched_24h'] ?? 0));
        $kdRows = [];
        foreach (array_keys($wNames) as $n) {
            $row = ['name' => (string) $n];
            foreach ($wm as $m => $col) {
                $row[$m] = $col[$n] ?? null;
            }
            $kdRows[] = $row;
        }
        usort($kdRows, fn ($x, $y) => ($y['kd_ratio'] ?? $y['kills_24h'] ?? -1) <=> ($x['kd_ratio'] ?? $x['kills_24h'] ?? -1));

        $tierDs = [];
        foreach ($tiers as $t => $data) {
            $tierDs[] = ['label' => __('analytics.tier', ['n' => $t]), 'data' => $data, 'color' => self::TIER_COLORS[$t]];
        }
        $top = array_slice($offense, 0, 10);
        $charts = [
            $this->chart(__('analytics.c.missiles_tiers'), 'bar', [], ['w' => 12, 'spec' => $this->inline($grid, $tierDs, ['stacked' => true, 'ts' => true])]),
            $this->chart(__('analytics.c.interception_rate'), 'line', [], ['spec' => $this->inline($grid, [['label' => '%', 'data' => $rate, 'color' => self::COLORS[1]]], ['ts' => true, 'unit' => '%', 'min' => 0, 'max' => 100])]),
            $this->chart(__('analytics.c.blocks_destroyed'), 'bar', [], ['spec' => $this->inline($grid, [['label' => __('analytics.blocks'), 'data' => $blocks, 'color' => self::COLORS[7]]], ['ts' => true])]),
            $this->chart(__('analytics.c.warheads'), 'hbar', [], ['spec' => $this->inline(array_keys(array_slice($warheads, 0, 10, true)), [['label' => __('analytics.launches'), 'data' => array_values(array_slice($warheads, 0, 10, true)), 'color' => self::COLORS[2]]])]),
            $this->chart(__('analytics.c.launched_vs_intercepted'), 'hbar', [], ['spec' => $this->inline(array_column($top, 'name'), [
                ['label' => __('analytics.launched'), 'data' => array_column($top, 'launched'), 'color' => self::COLORS[2]],
                ['label' => __('analytics.intercepted'), 'data' => array_column($top, 'intercepted'), 'color' => self::COLORS[1]],
            ])]),
            $this->chart(__('analytics.c.kd'), 'hbar', [], ['spec' => $this->inline(array_column(array_slice($kdRows, 0, 12), 'name'), [
                ['label' => __('analytics.m.kills_24h'), 'data' => array_map(fn ($x) => $x['kills_24h'] ?? 0, array_slice($kdRows, 0, 12)), 'color' => self::COLORS[0]],
                ['label' => __('analytics.m.deaths_24h'), 'data' => array_map(fn ($x) => $x['deaths_24h'] ?? 0, array_slice($kdRows, 0, 12)), 'color' => self::COLORS[7]],
            ])]),
        ];

        $recent = array_map(fn ($e) => $this->missileRow($e), array_slice(array_reverse($launched), 0, 40));
        $worlds = [];
        foreach ($launched as $e) {
            foreach ([$e['data']['from']['world'] ?? null, $e['data']['target']['world'] ?? null] as $w) {
                if (is_string($w) && $w !== '') {
                    $worlds[$w] = true;
                }
            }
        }
        $fmtTypes = ['siege_started', 'siege_ended', 'treaty_signed', 'peace_bought', 'war_declared', 'war_ended'];
        $warEvents = $this->a->events(['types' => $fmtTypes, 'from' => $from, 'to' => $to], 40)['rows'];
        $nLaunched = $counts['missile_launched'] ?? 0;
        $nInter = $counts['missile_intercepted'] ?? 0;

        return $this->render('admin.analytics.war', $c, [
            'charts' => $charts, 'offense' => array_slice($offense, 0, 12), 'defense' => array_slice($defense, 0, 12),
            'metricRows' => $metricRows, 'kdRows' => $kdRows, 'recent' => $recent, 'worlds' => array_keys($worlds), 'warEvents' => $warEvents,
            'reasons' => $reasons, 'by' => $by, 'counts' => $counts,
            'kpi' => [
                ['launched', $nLaunched], ['refused', $counts['missile_refused'] ?? 0], ['intercepted', $nInter],
                ['rate', $nLaunched > 0 ? round(min(100, $nInter * 100 / $nLaunched), 1) : null, '%'],
                ['impacts', $counts['missile_impact'] ?? 0], ['blocks', $blocksTotal], ['native', $counts['missile_shot_down_native'] ?? 0],
                ['sieges', $counts['siege_started'] ?? 0], ['treaties', ($counts['treaty_signed'] ?? 0) + ($counts['peace_bought'] ?? 0)],
            ],
            'capped' => count($launched) >= 2500,
        ]);
    }

    // ── Usage ───────────────────────────────────────────────────────────

    private function family(string $metric): string
    {
        $map = ['weapons' => '/^(weapon|gun|arme)/', 'transport' => '/^(vehicle|train|convoy|boat|ship|tractor)/', 'crates' => '/^(crate|lootbox|caisse|box)/',
            'bourse' => '/^(bourse|stock|market)/', 'wheel' => '/^(wheel|roue|tombola|raffle|ticket)/', 'arcade' => '/^arcade/', 'boss' => '/^boss/',
            'disasters' => '/^(disaster|catastrophe|meltdown|nuclear|earthquake|volcano|meteor)/'];
        foreach ($map as $fam => $re) {
            if (preg_match($re, $metric)) {
                return $fam;
            }
        }

        return 'other';
    }

    public function usage(Request $r)
    {
        $c = $this->ctx($r, 'usage');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $fams = [];
        foreach ($this->a->latestBatch('usage', $c['lb']) as $row) {
            $fams[$this->family($row['metric'])][] = $row;
        }
        $order = ['weapons', 'transport', 'crates', 'bourse', 'wheel', 'arcade', 'boss', 'disasters', 'other'];
        $groups = [];
        foreach ($order as $f) {
            if (empty($fams[$f])) {
                continue;
            }
            $rows = $fams[$f];
            usort($rows, fn ($x, $y) => $y['value'] <=> $x['value']);
            $top = array_slice($rows, 0, 10);
            $label = fn ($x) => (string) ($x['extra'] !== null && $x['extra'] !== '' ? $x['extra'] : ($x['ref'] !== '' ? $x['ref'] : $x['metric']));
            $groups[] = [
                'key' => $f, 'rows' => array_slice($rows, 0, 40),
                'chart' => $this->chart(__("analytics.fam.$f"), 'hbar', [], ['w' => 12, 'h' => max(160, 34 * count($top) + 40), 'spec' => $this->inline(array_map($label, $top), [['label' => __("analytics.fam.$f"), 'data' => array_column($top, 'value'), 'color' => self::COLORS[array_search($f, $order) % 8]]])]),
            ];
        }

        return $this->render('admin.analytics.usage', $c, ['groups' => $groups]);
    }

    // ── Endpoint JSON interne ───────────────────────────────────────────

    public function data(Request $r): JsonResponse
    {
        $period = GameAnalytics::period((string) $r->query('period', '7d'));
        [$from, $to] = $this->a->range($period);
        $st = $this->a->status();
        $base = ['ok' => $st['state'] === 'ok', 'state' => $st['state'], 'period' => $period, 'from' => $from, 'to' => $to];
        $json = fn (array $d) => response()->json(array_merge($base, $d), 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($st['state'] !== 'ok') {
            return $json(['labels' => [], 'series' => []]);
        }
        switch ((string) $r->query('kind', 'series')) {
            case 'missiles':
                $rows = array_map(fn ($e) => $this->missileRow($e), $this->a->eventsOf(['missile_launched'], $from, $to, 1500));

                return $json(['missiles' => $rows]);
            case 'missile':
                $e = $this->a->eventById((int) $r->query('id'));
                if (! $e || $e['type'] !== 'missile_launched') {
                    return response()->json(['ok' => false, 'error' => 'not_found'], 404);
                }

                return $json(['missile' => $this->missileRow($e), 'raw' => $e['data'], 'related' => $this->a->relatedMissileEvents($e)]);
            case 'series':
            default:
                $specs = [];
                foreach (array_slice((array) $r->query('series', []), 0, 24) as $s) {
                    if (! is_string($s)) {
                        continue;
                    }
                    $p = explode('|', $s, 4);
                    if (count($p) < 3 || ! in_array($p[0], GameAnalytics::SCOPES, true) || ! GameAnalytics::validName($p[1]) || ! GameAnalytics::validRef($p[2])) {
                        continue;
                    }
                    $specs[] = ['scope' => $p[0], 'metric' => $p[1], 'ref' => $p[2], 'agg' => ($p[3] ?? '') === 'max' ? 'max' : 'avg'];
                }
                if (! $specs) {
                    return $json(['labels' => [], 'series' => []]);
                }
                $res = $this->a->series($specs, $from, $to);

                return $json(['labels' => $res['labels'], 'series' => $res['series'], 'bucket' => $res['bucket']]);
        }
    }
}
