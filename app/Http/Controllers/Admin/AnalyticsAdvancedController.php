<?php

namespace App\Http\Controllers\Admin;

use App\Services\AnalyticsCatalog;
use App\Services\GameAnalytics;
use Illuminate\Http\Request;

/**
 * Pages d'analyse « avancées » : santé, heures de pointe, comparateur, territoires, diplomatie,
 * logistique, conflits, rétention, classements, succès. Chaque donnée est facultative : une métrique
 * absente donne un état vide explicatif, jamais une erreur.
 */
class AnalyticsAdvancedController extends AnalyticsController
{
    private function start(Request $r, string $page): array
    {
        return $this->ctx($r, $page);
    }

    // ── Santé du serveur ────────────────────────────────────────────────

    public function health(Request $r)
    {
        $c = $this->start($r, 'health');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $lb = $c['lb'];
        $L = fn (string $m) => $this->a->latestOne('server', $m, $lb);
        $ramU = $L('ram_used_mb');
        $ramM = $L('ram_max_mb');
        $ser = $this->a->series([['scope' => 'server', 'metric' => 'tps', 'ref' => ''], ['scope' => 'server', 'metric' => 'mspt', 'ref' => '', 'agg' => 'max']], $c['from'], $c['to']);
        $tps = array_values(array_filter($ser['series'][0] ?? [], fn ($v) => $v !== null));
        $mspt = array_values(array_filter($ser['series'][1] ?? [], fn ($v) => $v !== null));
        $degraded = $tps ? round(count(array_filter($tps, fn ($v) => $v < 18)) * 100 / count($tps), 1) : null;
        sort($mspt);
        $p95 = $mspt ? $mspt[(int) floor((count($mspt) - 1) * 0.95)] : null;
        $up = $L('uptime_s');
        $kpi = [
            ['tps', $L('tps'), null], ['mspt', $L('mspt'), null], ['ram', $ramU, $ramM && $ramU !== null ? round($ramU * 100 / $ramM) . ' %' : null],
            ['entities', $L('entities'), null], ['chunks_loaded', $L('chunks_loaded'), null], ['gc_pause_ms', $L('gc_pause_ms'), null],
            ['threads', $L('threads'), null], ['lag_spikes_24h', $L('lag_spikes_24h'), null],
            ['degraded_pct', $degraded, null], ['mspt_p95', $p95, null],
        ];
        $charts = [
            $this->chart(__('analytics.c.tps_mspt'), 'line', [$this->one('server', 'tps', '', self::COLORS[1]), $this->one('server', 'mspt', '', self::COLORS[2], null, ['axis' => 'right'])], ['spec' => ['dual' => true]]),
            $this->chart(__('analytics.c.ram'), 'line', [$this->one('server', 'ram_used_mb', '', self::COLORS[3], null, ['fill' => true]), $this->one('server', 'ram_max_mb', '', self::COLORS[7], null, ['dashed' => true])], ['spec' => ['unit' => ' Mo']]),
            $this->chart(GameAnalytics::metricTitle('gc_pause_ms'), 'line', [$this->one('server', 'gc_pause_ms', '', self::COLORS[5], null, ['fill' => true])], ['spec' => ['unit' => ' ms']]),
            $this->chart(__('analytics.c.world'), 'line', [$this->one('server', 'entities', '', self::COLORS[4]), $this->one('server', 'chunks_loaded', '', self::COLORS[5])]),
            $this->chart(GameAnalytics::metricTitle('lag_spikes_24h'), 'bar', [$this->one('server', 'lag_spikes_24h', '', self::COLORS[7], null, ['agg' => 'max'])]),
            $this->chart(GameAnalytics::metricTitle('threads'), 'line', [$this->one('server', 'threads', '', self::COLORS[0])]),
        ];

        return $this->render('admin.analytics.health', $c, ['kpi' => $kpi, 'charts' => $charts, 'uptime' => $up]);
    }

    // ── Heures de pointe ────────────────────────────────────────────────

    public function peak(Request $r)
    {
        $c = $this->start($r, 'peak');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $span = max($c['to'] - $c['from'], 7 * 86400);
        $from = $c['to'] - $span;
        $res = $this->a->series([['scope' => 'server', 'metric' => 'players_online', 'ref' => '']], $from, $c['to'], 3600);
        $sum = [];
        $cnt = [];
        foreach ($res['labels'] as $i => $t) {
            $v = $res['series'][0][$i] ?? null;
            if ($v === null) {
                continue;
            }
            $d = GameAnalytics::carbon((int) $t);
            $k = ($d->dayOfWeekIso - 1) . '-' . $d->hour;
            $sum[$k] = ($sum[$k] ?? 0) + $v;
            $cnt[$k] = ($cnt[$k] ?? 0) + 1;
        }
        $grid = [];
        $max = 0;
        $byHour = array_fill(0, 24, [0, 0]);
        $byDay = array_fill(0, 7, [0, 0]);
        for ($d = 0; $d < 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $k = "$d-$h";
                $v = isset($cnt[$k]) ? $sum[$k] / $cnt[$k] : null;
                $grid[$d][$h] = $v;
                if ($v !== null) {
                    $max = max($max, $v);
                    $byHour[$h][0] += $v;
                    $byHour[$h][1]++;
                    $byDay[$d][0] += $v;
                    $byDay[$d][1]++;
                }
            }
        }
        $hourAvg = array_map(fn ($x) => $x[1] ? round($x[0] / $x[1], 1) : null, $byHour);
        $dayAvg = array_map(fn ($x) => $x[1] ? round($x[0] / $x[1], 1) : null, $byDay);
        $has = $max > 0;
        $best = $worst = null;
        if ($has) {
            $valid = array_filter($hourAvg, fn ($v) => $v !== null);
            $best = array_search(max($valid), $valid, true);
            // Fenêtre calme : 2 heures consécutives les moins fréquentées.
            $bw = null;
            $bv = INF;
            for ($h = 0; $h < 24; $h++) {
                $a = $hourAvg[$h];
                $b = $hourAvg[($h + 1) % 24];
                if ($a !== null && $b !== null && $a + $b < $bv) {
                    $bv = $a + $b;
                    $bw = $h;
                }
            }
            $worst = $bw;
        }
        $days = __('analytics.days');
        $charts = [
            $this->chart(__('analytics.peak_by_hour'), 'bar', [], ['spec' => $this->inline(array_map(fn ($h) => sprintf('%02d h', $h), range(0, 23)), [['label' => GameAnalytics::metricLabel('players_online'), 'data' => $hourAvg, 'color' => self::COLORS[0]]])]),
            $this->chart(__('analytics.peak_by_day'), 'bar', [], ['spec' => $this->inline($days, [['label' => GameAnalytics::metricLabel('players_online'), 'data' => $dayAvg, 'color' => self::COLORS[3]]])]),
        ];

        return $this->render('admin.analytics.peak', $c, ['grid' => $grid, 'max' => $max, 'has' => $has, 'best' => $best, 'quiet' => $worst, 'days' => $days, 'charts' => $charts, 'spanDays' => (int) round($span / 86400)]);
    }

    // ── Comparateur de pays (radar) ─────────────────────────────────────

    public function compare(Request $r)
    {
        $c = $this->start($r, 'compare');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $axes = ['power', 'members', 'claims', 'bank', 'research_points', 'age_index', 'rating_week', 'playtime_hours_week'];
        $mx = $this->matrix('faction', $axes, $c['lb']);
        $all = [];
        foreach ($mx as $col) {
            foreach (array_keys($col) as $k) {
                $all[(string) $k] = true;
            }
        }
        $all = array_keys($all);
        usort($all, fn ($x, $y) => ($mx['power'][$y] ?? 0) <=> ($mx['power'][$x] ?? 0));
        $sel = array_values(array_filter((array) $r->query('countries', []), fn ($v) => is_string($v) && in_array($v, $all, true)));
        $sel = array_slice(array_unique($sel), 0, 5) ?: array_slice($all, 0, 3);
        $maxOf = [];
        foreach ($axes as $a) {
            $maxOf[$a] = $mx[$a] ? max($mx[$a]) : 0;
        }
        $ds = [];
        foreach ($sel as $cn) {
            $ds[] = ['label' => GameAnalytics::country($cn), 'color' => GameAnalytics::colorOf($cn),
                'data' => array_map(fn ($a) => $maxOf[$a] > 0 ? round(($mx[$a][$cn] ?? 0) * 100 / $maxOf[$a], 1) : 0, $axes)];
        }
        $labels = array_map(fn ($a) => GameAnalytics::metricLabel($a), $axes);
        $charts = [$this->chart(__('analytics.compare_radar'), 'radar', [], ['w' => 12, 'h' => 420, 'spec' => $this->inline($labels, $ds, ['max' => 100, 'unit' => ' %'])])];

        return $this->render('admin.analytics.compare', $c, ['all' => $all, 'sel' => $sel, 'axes' => $axes, 'mx' => $mx, 'charts' => $charts]);
    }

    // ── Territoires ─────────────────────────────────────────────────────

    public function territories(Request $r)
    {
        $c = $this->start($r, 'territories');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $dateIn = (string) $r->query('date', '');
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIn) ? $dateIn : GameAnalytics::carbon($c['to'])->format('Y-m-d');
        try {
            $end = \Carbon\Carbon::parse($day . ' 23:59:59', GameAnalytics::TZ)->getTimestamp();
        } catch (\Throwable) {
            $day = GameAnalytics::carbon($c['to'])->format('Y-m-d');
            $end = $c['to'];
        }
        $snaps = $this->a->eventsOf(['territory_snapshot'], 0, $end, 1500);
        $latest = [];
        foreach ($snaps as $e) {
            $latest[$e['ref'] . '|' . ($e['data']['world'] ?? '')] = $e; // anciens -> récents : le dernier gagne
        }
        $worlds = [];
        foreach ($latest as $e) {
            $worlds[(string) ($e['data']['world'] ?? '')] = true;
        }
        $worlds = array_keys($worlds);
        $world = (string) $r->query('world', '');
        if (! in_array($world, $worlds, true)) {
            $world = $worlds[0] ?? '';
        }
        $rects = [];
        $stats = [];
        [$x0, $x1, $z0, $z1] = [PHP_INT_MAX, PHP_INT_MIN, PHP_INT_MAX, PHP_INT_MIN];
        $budget = 20000;
        foreach ($latest as $e) {
            if ((string) ($e['data']['world'] ?? '') !== $world) {
                continue;
            }
            $cn = $e['ref'];
            $runs = [];
            foreach ((array) ($e['data']['runs'] ?? []) as $run) {
                if (is_array($run) && count($run) === 3 && is_numeric($run[0]) && is_numeric($run[1]) && is_numeric($run[2])) {
                    $runs[] = [(int) $run[0], (int) $run[1], (int) $run[2]];
                }
            }
            foreach ((array) ($e['data']['chunks'] ?? []) as $ch) {
                if (is_array($ch) && count($ch) >= 2 && is_numeric($ch[0]) && is_numeric($ch[1])) {
                    $runs[] = [(int) $ch[1], (int) $ch[0], (int) $ch[0]];
                }
            }
            $n = 0;
            foreach ($runs as [$cz, $a, $b]) {
                if ($b < $a || $budget-- <= 0) {
                    continue;
                }
                $rects[] = [$a, $cz, $b - $a + 1, $cn];
                $n += $b - $a + 1;
                [$x0, $x1, $z0, $z1] = [min($x0, $a), max($x1, $b), min($z0, $cz), max($z1, $cz)];
            }
            $stats[$cn] = ['chunks' => $n, 'ts' => $e['ts']];
        }
        uasort($stats, fn ($a, $b) => $b['chunks'] <=> $a['chunks']);
        $has = $rects !== [];

        return $this->render('admin.analytics.territories', $c, [
            'day' => $day, 'world' => $world, 'worlds' => array_map(fn ($w) => [$w, AnalyticsCatalog::get()->world($w)], $worlds),
            'rects' => $rects, 'stats' => $stats, 'has' => $has, 'box' => $has ? [$x0, $z0, $x1 - $x0 + 1, $z1 - $z0 + 1] : [0, 0, 1, 1],
        ]);
    }

    // ── Diplomatie ──────────────────────────────────────────────────────

    /** @return array{wars:array,alliances:array,treaties:array,all:array,events:array} */
    private function relations(int $to): array
    {
        $ev = $this->a->eventsOf(['war_declared', 'war_ended', 'alliance_formed', 'treaty_signed', 'peace_bought'], 0, $to, 3000);
        $key = fn (string $a, string $b) => strcmp($a, $b) <= 0 ? "$a|$b" : "$b|$a";
        $wars = [];
        $alli = [];
        $trea = [];
        $nodes = [];
        foreach ($ev as $e) {
            $d = $e['data'];
            $ref = $e['ref'];
            $other = (string) ($d['target'] ?? $d['with'] ?? $d['from'] ?? $d['loser'] ?? '');
            foreach ([$ref, $other] as $n) {
                if ($n !== '') {
                    $nodes[$n] = true;
                }
            }
            switch ($e['type']) {
                case 'war_declared':
                    if ($other !== '') {
                        $wars[$key($ref, $other)] = [$ref, $other];
                    }
                    break;
                case 'war_ended':
                    if ($other !== '') {
                        unset($wars[$key($ref, $other)]);
                    } else {
                        foreach ($wars as $k => $w) {
                            if (in_array($ref, $w, true)) {
                                unset($wars[$k]);
                                break;
                            }
                        }
                    }
                    break;
                case 'alliance_formed':
                    if ($other !== '') {
                        $alli[$key($ref, $other)] = [$ref, $other];
                    }
                    break;
                case 'treaty_signed':
                case 'peace_bought':
                    if ($other !== '') {
                        unset($wars[$key($ref, $other)]);
                        $trea[$key($ref, $other)] = [$ref, $other, $e['type']];
                    }
                    break;
            }
        }

        return ['wars' => array_values($wars), 'alliances' => array_values($alli), 'treaties' => array_values($trea), 'all' => array_keys($nodes), 'events' => array_reverse($ev)];
    }

    public function diplomacy(Request $r)
    {
        $c = $this->start($r, 'diplomacy');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $rel = $this->relations($c['to']);
        $names = array_unique(array_merge($rel['all'], array_keys($this->a->latest('faction', 'power', $c['lb']))));
        sort($names);
        $n = count($names);
        $pos = [];
        foreach (array_values($names) as $i => $nm) {
            $ang = 2 * M_PI * $i / max(1, $n) - M_PI / 2;
            $pos[$nm] = [round(300 + 210 * cos($ang), 1), round(210 + 150 * sin($ang), 1)];
        }
        $recent = array_slice(array_filter($rel['events'], fn ($e) => $e['ts'] >= $c['from']), 0, 30);

        return $this->render('admin.analytics.diplomacy', $c, ['names' => $names, 'pos' => $pos, 'rel' => $rel, 'recent' => $recent]);
    }

    // ── Logistique ──────────────────────────────────────────────────────

    public function logistics(Request $r)
    {
        $c = $this->start($r, 'logistics');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $lb = $c['lb'];
        $conv = array_map(fn ($x) => $x['v'], $this->a->latest('faction', 'convoys_active', $lb));
        $plots = array_map(fn ($x) => $x['v'], $this->a->latest('faction', 'plots', $lb));
        $deliv = [];
        foreach ($this->a->latestBatch('usage', $lb) as $row) {
            if ($row['metric'] === 'convoys_delivered_24h' && $row['ref'] !== '') {
                $deliv[$row['ref']] = $row['value'];
            }
        }
        $train = $this->a->latestOne('usage', 'train_trips_24h', $lb);
        arsort($conv);
        arsort($plots);
        arsort($deliv);
        $hb = fn (array $m, string $label, string $col) => $this->inline(array_map(fn ($k) => GameAnalytics::country((string) $k), array_keys($m)), [['label' => $label, 'data' => array_values($m), 'color' => $col]]);
        $kpi = [['convoys_active', $conv ? array_sum($conv) : null, null], ['convoys_delivered_24h', $deliv ? array_sum($deliv) : null, null],
            ['train_trips_24h', $train, null], ['plots', $plots ? array_sum($plots) : null, null]];
        $top = array_slice(array_keys($conv), 0, 6);
        $charts = [
            $this->chart(GameAnalytics::metricTitle('convoys_active'), 'hbar', [], ['spec' => $hb($conv, GameAnalytics::metricLabel('convoys_active'), self::COLORS[3])]),
            $this->chart(GameAnalytics::metricTitle('convoys_delivered_24h'), 'hbar', [], ['spec' => $hb($deliv, GameAnalytics::metricLabel('convoys_delivered_24h'), self::COLORS[0])]),
            $this->chart(GameAnalytics::metricTitle('plots'), 'hbar', [], ['spec' => $hb($plots, GameAnalytics::metricLabel('plots'), self::COLORS[5])]),
            $this->chart(__('analytics.c.convoys_time'), 'line', $this->multi('faction', 'convoys_active', $top)),
        ];

        return $this->render('admin.analytics.logistics', $c, ['kpi' => $kpi, 'charts' => $charts, 'empty' => ! $conv && ! $deliv && ! $plots && $train === null]);
    }

    // ── Conflits (guerre avancée) ───────────────────────────────────────

    public function conflicts(Request $r)
    {
        $c = $this->start($r, 'conflicts');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $cat = AnalyticsCatalog::get();
        $ev = $this->a->eventsOf(['war_declared', 'war_ended', 'siege_started', 'siege_ended', 'war_kill', 'treaty_signed', 'peace_bought'], 0, $c['to'], 4000);
        $wars = [];
        $sieges = [];
        $kills = [];
        foreach ($ev as $e) {
            $d = $e['data'];
            $oth = (string) ($d['target'] ?? $d['with'] ?? $d['loser'] ?? '');
            switch ($e['type']) {
                case 'war_declared':
                    $wars[] = ['a' => $e['ref'], 'b' => $oth, 'start' => $e['ts'], 'end' => null, 'casus' => (string) ($d['casus'] ?? ''), 'winner' => '', 'how' => ''];
                    break;
                case 'war_ended':
                    for ($i = count($wars) - 1; $i >= 0; $i--) {
                        if ($wars[$i]['end'] === null && in_array($e['ref'], [$wars[$i]['a'], $wars[$i]['b']], true)) {
                            $wars[$i]['end'] = $e['ts'];
                            $wars[$i]['winner'] = (string) ($d['winner'] ?? '');
                            $wars[$i]['how'] = (string) ($d['how'] ?? '');
                            break;
                        }
                    }
                    break;
                case 'siege_started':
                    $sieges[] = ['a' => $e['ref'], 'b' => $oth, 'start' => $e['ts'], 'end' => null, 'winner' => '', 'breaches' => null];
                    break;
                case 'siege_ended':
                    for ($i = count($sieges) - 1; $i >= 0; $i--) {
                        if ($sieges[$i]['end'] === null && in_array($e['ref'], [$sieges[$i]['a'], $sieges[$i]['b']], true)) {
                            $sieges[$i]['end'] = $e['ts'];
                            $sieges[$i]['winner'] = (string) ($d['winner'] ?? '');
                            $sieges[$i]['breaches'] = $d['breaches'] ?? null;
                            break;
                        }
                    }
                    break;
                case 'war_kill':
                    $kills[] = $e;
                    break;
            }
        }
        $inPeriod = fn ($w) => ($w['end'] ?? $c['to']) >= $c['from'];
        $wars = array_values(array_filter($wars, $inPeriod));
        $sieges = array_values(array_filter($sieges, $inPeriod));
        $done = array_filter($wars, fn ($w) => $w['end'] !== null);
        $avg = $done ? array_sum(array_map(fn ($w) => $w['end'] - $w['start'], $done)) / count($done) : null;
        $counts = $this->a->eventCounts($c['from'], $c['to']);
        $kpi = [['wars_ongoing', count(array_filter($wars, fn ($w) => $w['end'] === null)), null], ['wars_done', count($done), null],
            ['war_avg_days', $avg !== null ? round($avg / 86400, 1) : null, null], ['sieges', $counts['siege_started'] ?? 0, null],
            ['treaties', ($counts['treaty_signed'] ?? 0) + ($counts['peace_bought'] ?? 0), null]];
        $wm = $this->matrix('war', ['kills_24h', 'deaths_24h', 'kd_ratio', 'annexed_claims', 'reparations_paid'], $c['lb']);
        $nm = array_keys($wm['kills_24h'] ?? []);
        usort($nm, fn ($x, $y) => ($wm['kills_24h'][$y] ?? 0) <=> ($wm['kills_24h'][$x] ?? 0));
        $charts = [$this->chart(__('analytics.c.kd'), 'hbar', [], ['spec' => $this->inline(array_map(fn ($k) => GameAnalytics::country((string) $k), array_slice($nm, 0, 12)), [
            ['label' => GameAnalytics::metricLabel('kills_24h'), 'data' => array_map(fn ($k) => $wm['kills_24h'][$k] ?? 0, array_slice($nm, 0, 12)), 'color' => self::COLORS[0]],
            ['label' => GameAnalytics::metricLabel('deaths_24h'), 'data' => array_map(fn ($k) => $wm['deaths_24h'][$k] ?? 0, array_slice($nm, 0, 12)), 'color' => self::COLORS[7]],
        ])])];
        $label = fn ($w) => $w['end'] === null ? __('analytics.ongoing') : ($w['winner'] !== '' ? __('analytics.victory_of', ['c' => $cat->faction($w['winner'])]) . ($w['how'] !== '' ? ' (' . $cat->word($w['how']) . ')' : '') : ($w['how'] !== '' ? $cat->word($w['how']) : __('analytics.ended')));

        return $this->render('admin.analytics.conflicts', $c, ['kpi' => $kpi, 'wars' => array_reverse($wars), 'sieges' => array_reverse($sieges), 'kills' => array_slice(array_reverse($kills), 0, 20),
            'charts' => $charts, 'outcome' => $label, 'nm' => $nm, 'wm' => $wm]);
    }

    // ── Rétention (cohortes) ────────────────────────────────────────────

    public function retention(Request $r)
    {
        $c = $this->start($r, 'retention');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $lb = $c['to'] - 400 * 86400;
        $cols = ['cohort_size', 'retention_w1', 'retention_w2', 'retention_w3', 'retention_w4'];
        $mx = [];
        foreach ($cols as $m) {
            $mx[$m] = array_map(fn ($x) => $x['v'], $this->a->latest('players', $m, $lb));
        }
        $cohorts = array_values(array_filter(array_keys($mx['cohort_size']), fn ($k) => $k !== ''));
        sort($cohorts);
        $cohorts = array_slice($cohorts, -12);
        $rows = [];
        foreach ($cohorts as $k) {
            $label = $k;
            if (preg_match('/^(\d{4})-W(\d{2})$/', $k, $m)) {
                $d = \Carbon\Carbon::now(GameAnalytics::TZ)->locale(app()->getLocale())->setISODate((int) $m[1], (int) $m[2]);
                $label = __('analytics.week_of', ['n' => (int) $m[2], 'date' => $d->translatedFormat('j M')]);
            }
            $rows[] = ['label' => $label, 'size' => $mx['cohort_size'][$k], 'w' => array_map(fn ($i) => $mx["retention_w$i"][$k] ?? null, [1, 2, 3, 4])];
        }
        $avg = [];
        foreach ([0, 1, 2, 3] as $i) {
            $v = array_filter(array_column($rows, 'w'), fn ($w) => $w[$i] !== null);
            $avg[] = $v ? round(array_sum(array_map(fn ($w) => $w[$i], $v)) / count($v), 1) : null;
        }
        $L = fn (string $m) => $this->a->latestOne('players', $m, $c['lb']);
        $kpi = [['d1', $L('retention_d1'), null], ['d7', $L('retention_d7'), null], ['retention_w1', $avg[0], null], ['retention_w4', $avg[3], null]];
        $charts = [
            $this->chart(__('analytics.c.cohort_avg'), 'bar', [], ['spec' => $this->inline(['W1', 'W2', 'W3', 'W4'], [['label' => __('analytics.c.cohort_avg'), 'data' => $avg, 'color' => self::COLORS[1]]], ['unit' => ' %', 'min' => 0, 'max' => 100])]),
            $this->chart(__('analytics.c.retention'), 'line', [$this->one('players', 'retention_d1', '', self::COLORS[1]), $this->one('players', 'retention_d7', '', self::COLORS[2])], ['spec' => ['unit' => ' %']]),
        ];

        return $this->render('admin.analytics.retention', $c, ['rows' => $rows, 'kpi' => $kpi, 'charts' => $charts]);
    }

    // ── Classements joueurs ─────────────────────────────────────────────

    public function rankings(Request $r)
    {
        $c = $this->start($r, 'rankings');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $by = [];
        foreach ($this->a->latestBatch('ranking', $c['to'] - 30 * 86400) as $row) {
            $by[$row['metric']][] = $row;
        }
        $boards = [];
        foreach ($by as $m => $rows) {
            usort($rows, fn ($x, $y) => $y['value'] <=> $x['value']);
            $rows = array_slice($rows, 0, 10);
            $boards[] = ['metric' => $m, 'title' => GameAnalytics::metricLabel($m), 'rows' => array_map(fn ($x) => [
                'name' => AnalyticsCatalog::isUuid($x['ref']) ? __('analytics.player_n', ['n' => AnalyticsCatalog::humanize($x['ref'])]) : $x['ref'],
                'value' => GameAnalytics::fmtMetric($m, $x['value'], true)], $rows)];
        }
        usort($boards, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $this->render('admin.analytics.rankings', $c, ['boards' => $boards]);
    }

    // ── Succès ──────────────────────────────────────────────────────────

    public function achievements(Request $r)
    {
        $c = $this->start($r, 'achievements');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $cat = AnalyticsCatalog::get();
        $cnt = $this->a->latest('achievements', 'unlocked', $c['to'] - 30 * 86400);
        $pct = array_map(fn ($x) => $x['v'], $this->a->latest('achievements', 'unlocked_pct', $c['to'] - 30 * 86400));
        $rows = [];
        foreach ($cnt + array_map(fn ($p) => ['v' => null, 'extra' => null], array_diff_key($pct, $cnt)) as $code => $x) {
            $name = $cat->find('achievement', (string) $code) ?? (is_string($x['extra'] ?? null) && trim($x['extra']) !== '' ? $x['extra'] : AnalyticsCatalog::humanize((string) $code));
            $ex = $cat->extra('achievement', (string) $code);
            $rows[] = ['name' => $name, 'n' => $x['v'], 'pct' => $pct[$code] ?? null, 'rarity' => isset($ex['rarity']) ? $cat->rarity((string) $ex['rarity']) : null,
                'rarityKey' => (string) ($ex['rarity'] ?? ''), 'points' => $ex['points'] ?? null];
        }
        if (! $rows) { // repli : événements achievement_unlocked
            $tally = [];
            foreach ($this->a->eventsOf(['achievement_unlocked'], $c['from'], $c['to'], 3000) as $e) {
                $code = (string) ($e['data']['code'] ?? $e['ref']);
                $tally[$code] = ($tally[$code] ?? 0) + 1;
            }
            foreach ($tally as $code => $n) {
                $rows[] = ['name' => $cat->label('achievement', $code), 'n' => $n, 'pct' => null, 'rarity' => null, 'rarityKey' => '', 'points' => null];
            }
        }
        usort($rows, fn ($x, $y) => [$y['pct'] ?? -1, $y['n'] ?? -1] <=> [$x['pct'] ?? -1, $x['n'] ?? -1]);
        $byR = [];
        foreach ($rows as $x) {
            if ($x['rarity'] !== null) {
                $byR[$x['rarity']] = ($byR[$x['rarity']] ?? 0) + ($x['n'] ?? 0);
            }
        }
        $charts = [$this->chart(__('analytics.c.unlock_rate'), 'hbar', [], ['w' => 8, 'spec' => $this->inline(array_column($rows, 'name'), [['label' => GameAnalytics::metricLabel('unlocked_pct'), 'data' => array_column($rows, 'pct'), 'color' => self::COLORS[5]]], ['unit' => ' %', 'max' => 100])])];
        if ($byR) {
            $charts[] = $this->chart(__('analytics.c.by_rarity'), 'doughnut', [], ['w' => 4, 'spec' => $this->inline(array_keys($byR), [['label' => __('analytics.c.by_rarity'), 'data' => array_values($byR), 'colors' => array_slice(self::COLORS, 0, count($byR))]])]);
        }

        return $this->render('admin.analytics.achievements', $c, ['rows' => $rows, 'charts' => $charts]);
    }
}
