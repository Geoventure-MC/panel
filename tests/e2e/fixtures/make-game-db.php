<?php
// Fixture E2E : base SQLite jetable imitant la base du jeu alimentée par le plugin (contrat gf_metrics / gf_events / gf_rollup).
// Usage : php make-game-db.php <fichier.sqlite> <full|empty>
//   full  : tables + données factices (relatives à l'heure courante)
//   empty : base valide SANS les tables d'analyse (écran explicatif « no_tables »)
[$_, $path, $mode] = $argv + [null, null, 'full'];
if (! $path) { fwrite(STDERR, "chemin requis\n"); exit(1); }
@unlink($path);
$db = new PDO('sqlite:' . $path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if ($mode !== 'full') { $db->exec('CREATE TABLE gf_factions (id INTEGER PRIMARY KEY, name TEXT)'); exit(0); }

$db->exec("CREATE TABLE gf_metrics (id INTEGER PRIMARY KEY AUTOINCREMENT, ts BIGINT NOT NULL, scope VARCHAR(16) NOT NULL, ref VARCHAR(64) NOT NULL DEFAULT '', metric VARCHAR(48) NOT NULL, value DOUBLE NOT NULL, extra VARCHAR(255))");
$db->exec('CREATE INDEX idx_metric ON gf_metrics (scope, metric, ref, ts)');
$db->exec("CREATE TABLE gf_rollup (id INTEGER PRIMARY KEY AUTOINCREMENT, ts BIGINT NOT NULL, scope VARCHAR(16) NOT NULL, ref VARCHAR(64) NOT NULL DEFAULT '', metric VARCHAR(48) NOT NULL, value DOUBLE NOT NULL, extra VARCHAR(255))");
$db->exec("CREATE TABLE gf_events (id INTEGER PRIMARY KEY AUTOINCREMENT, ts BIGINT NOT NULL, type VARCHAR(32) NOT NULL, ref VARCHAR(64) NOT NULL, actor VARCHAR(64), data TEXT)");
$db->exec('CREATE INDEX idx_type_ts ON gf_events (type, ts)');

$now = time();
$m = $db->prepare('INSERT INTO gf_metrics (ts, scope, ref, metric, value, extra) VALUES (?,?,?,?,?,?)');
$r = $db->prepare('INSERT INTO gf_rollup (ts, scope, ref, metric, value, extra) VALUES (?,?,?,?,?,?)');
$e = $db->prepare('INSERT INTO gf_events (ts, type, ref, actor, data) VALUES (?,?,?,?,?)');
mt_srand(42);
$db->beginTransaction();
$countries = ['Aurelia' => 1.0, 'Borealis' => 0.8, 'Cendra' => 0.6, 'Dorne' => 0.4];
$step = 1800;
$n = 7 * 48;
for ($i = $n; $i >= 0; $i--) {
    $ts = $now - $i * $step;
    $k = ($n - $i) / $n; // 0 -> 1
    $day = sin(($ts % 86400) / 86400 * 2 * M_PI);
    foreach ([
        ['server', '', 'players_online', max(0, 12 + 9 * $day + mt_rand(-2, 2))], ['server', '', 'players_peak_day', 25 + mt_rand(0, 6)],
        ['server', '', 'tps', 19.2 + mt_rand(0, 8) / 10], ['server', '', 'mspt', 22 + mt_rand(0, 20)], ['server', '', 'entities', 3000 + mt_rand(0, 500)],
        ['server', '', 'chunks_loaded', 1800 + mt_rand(0, 200)], ['server', '', 'ram_used_mb', 5200 + mt_rand(0, 900)], ['server', '', 'ram_max_mb', 8192],
        ['economy', '', 'money_supply', 1.0e6 + $k * 4e5], ['economy', '', 'avg_balance', 800 + $k * 150], ['economy', '', 'hdv_listings', 120 + mt_rand(0, 30)],
        ['economy', '', 'hdv_volume_24h', 40000 + mt_rand(0, 9000)], ['economy', '', 'bourse_index', 1000 + $k * 80 + mt_rand(0, 20)], ['economy', '', 'trade_tax_24h', 2000 + mt_rand(0, 500)],
        ['oil', 'Zone Nord', 'reserve_pct', 100 - $k * 35], ['oil', 'Zone Nord', 'reserve', 500000 * (1 - $k * 0.35)], ['oil', 'Zone Nord', 'extracted_total', 100000 * $k], ['oil', 'Zone Nord', 'wells', 6],
        ['oil', 'Zone Sud', 'reserve_pct', 100 - $k * 100], ['oil', 'Zone Sud', 'reserve', 200000 * (1 - $k)], ['oil', 'Zone Sud', 'extracted_total', 200000 * $k], ['oil', 'Zone Sud', 'wells', 2],
        ['oil', '', 'extracted_24h', 18000 + mt_rand(0, 2000)],
        ['pollution', 'overworld', 'avg_level', 800 + $k * 400], ['pollution', 'overworld', 'max_level', 4000 + $k * 2000], ['pollution', 'overworld', 'chunks_over_hazard', (int) (3 + $k * 9)],
        ['pollution', 'nether', 'avg_level', 200 + $k * 50], ['pollution', 'nether', 'max_level', 900], ['pollution', 'nether', 'chunks_over_hazard', 0],
    ] as [$sc, $ref, $met, $v]) { $m->execute([$ts, $sc, $ref, $met, round($v, 2), null]); }
    $ci = 0;
    foreach ($countries as $c => $w) {
        $ci++;
        foreach ([
            'members' => 10 + 30 * $w + $k * 6, 'members_online' => max(0, 3 * $w + 3 * $day + 2), 'power' => 200 * $w + $k * 80 * $w, 'claims' => 30 * $w + $k * 10,
            'bank' => 50000 * $w * (1 + $k), 'research_points' => 400 * $w + $k * 300 * $w, 'research_unlocked' => floor(8 * $w + $k * 6 * $w), 'age_index' => floor(1 + 3 * $w * $k),
            'wars_active' => $c === 'Aurelia' || $c === 'Dorne' ? 1 : 0, 'rating_week' => 10 + 6 * $w, 'emissions' => 1000 * $w + $k * 800 * $w,
            'convoys_active' => $ci, 'plots' => 2 * $ci, 'playtime_hours_week' => 120 * $w,
        ] as $met => $v) { $m->execute([$ts, 'faction', $c, $met, round($v, 2), null]); }
        // Compteurs missiles / guerre : poussés ~ toutes les heures.
        if ($i % 2 === 0) {
            foreach ([
                ['missiles', 'launched_total', 40 * $w * $k], ['missiles', 'launched_24h', round(6 * $w)], ['missiles', 'tier1_24h', round(3 * $w)], ['missiles', 'tier2_24h', round(2 * $w)],
                ['missiles', 'tier3_24h', round($w)], ['missiles', 'tier4_24h', $c === 'Aurelia' ? 1 : 0], ['missiles', 'tier5_24h', 0], ['missiles', 'intercepted_24h', round(4 * (1.2 - $w))],
                ['missiles', 'hit_24h', round(2 * $w)], ['missiles', 'refused_24h', 1], ['missiles', 'interception_rate_pct', round(60 + 10 * (1 - $w))], ['missiles', 'blocks_destroyed_24h', round(900 * $w)],
                ['missiles', 'defense_radars', $ci], ['missiles', 'defense_interceptors', $ci + 1], ['missiles', 'defense_ciws', $ci - 1 > 0 ? $ci - 1 : 0],
                ['war', 'kills_24h', round(20 * $w)], ['war', 'deaths_24h', round(15 * (1.2 - $w) + 2)], ['war', 'kd_ratio', round((20 * $w) / (15 * (1.2 - $w) + 2), 2)],
                ['war', 'sieges_active', $c === 'Aurelia' ? 1 : 0], ['war', 'assault_gauge_peak', round(40 * $w)], ['war', 'annexed_claims', $ci], ['war', 'reparations_paid', 500 * $ci],
            ] as [$sc, $met, $v]) { $m->execute([$ts, $sc, $c, $met, $v, null]); }
        }
    }
    if ($i % 48 === 0) {
        foreach ([['dau', 30 + mt_rand(0, 10)], ['new_players', 3 + mt_rand(0, 4)], ['retention_d1', 55 + mt_rand(0, 10)], ['retention_d7', 30 + mt_rand(0, 8)], ['avg_session_min', 70 + mt_rand(0, 25)]] as [$met, $v]) {
            $m->execute([$ts, 'players', '', $met, $v, null]);
        }
    }
}
// Rollup : données anciennes (journalières) pour tester la fusion metrics + rollup.
for ($d = 60; $d > 8; $d--) {
    $ts = $now - $d * 86400;
    $r->execute([$ts, 'server', '', 'players_online', 8 + ($d % 5), null]);
    $r->execute([$ts, 'players', '', 'dau', 20 + ($d % 7), null]);
}
// Usage : dernier lot (extra = identifiant).
$usage = [
    ['weapon_kills', '', 410, 'superbwarfare:ak_47'], ['weapon_kills', '', 305, 'superbwarfare:m_870'], ['weapon_kills', '', 190, 'minecraft:bow'], ['weapon_kills', '', 88, 'megasarms:iron_sword'],
    ['vehicle_uses', 'tractor', 120, null], ['vehicle_uses', 'boat', 45, null], ['train_trips', '', 33, null], ['convoy_completed', '', 17, null],
    ['crate_opened', 'common', 500, null], ['crate_opened', 'rare', 120, null], ['crate_opened', 'legendary', 4, null],
    ['bourse_orders', '', 260, null], ['wheel_spins', '', 150, null], ['tombola_tickets', '', 900, null], ['arcade_matches', '', 64, null], ['boss_kills', 'lich', 7, null],
    ['disaster_triggered', 'volcano', 2, null], ['nuclear_meltdowns', '', 1, null],
];
foreach ([$now - 3600, $now - 1800] as $ts) {
    foreach ($usage as [$met, $ref, $v, $ex]) { $m->execute([$ts, 'usage', $ref, $met, $v - ($ts < $now - 2000 ? 5 : 0), $ex]); }
}

// Événements.
$ev = function (int $ago, string $type, string $ref, array $data, ?string $actor = null) use ($e, $now) { $e->execute([$now - $ago, $type, $ref, $actor, json_encode($data)]); };
$ev(600000, 'faction_created', 'Dorne', ['leader' => 'SECRETPLAYER'], 'SecretPlayer');
$ev(500000, 'research_unlocked', 'Aurelia', ['id' => 'economy_1', 'name' => 'Marché libre', 'cost' => 300, 'branch' => 'economy']);
$ev(400000, 'research_unlocked', 'Aurelia', ['id' => 'industry_2', 'name' => 'Hauts-fourneaux', 'cost' => 600]);
$ev(300000, 'research_unlocked', 'Borealis', ['id' => 'military_1', 'name' => 'Armée de métier', 'cost' => 450, 'branch' => 'military']);
$ev(200000, 'age_changed', 'Aurelia', ['from' => 'ancient', 'to' => 'industrial']);
$ev(100000, 'war_declared', 'Aurelia', ['target' => 'Dorne']);
$ev(90000, 'alliance_formed', 'Borealis', ['with' => 'Cendra']);
$ev(80000, 'oil_zone_depleted', 'Zone Sud', ['zone' => 'Zone Sud']);
$ev(70000, 'meltdown', 'Cendra', ['x' => 120, 'z' => -40]);
$ev(60000, 'siege_started', 'Aurelia', ['target' => 'Dorne']);
$ev(50000, 'boss_killed', 'Borealis', ['boss' => 'lich']);
$ev(3000, 'siege_ended', 'Aurelia', ['winner' => 'Aurelia', 'breaches' => 12, 'duration_s' => 1800]);
$ev(2500, 'treaty_signed', 'Dorne', ['with' => 'Aurelia']);
$ev(2000, 'peace_bought', 'Dorne', ['from' => 'Aurelia']);
$ev(1500, 'war_ended', 'Aurelia', ['winner' => 'Aurelia', 'how' => 'treaty']);
$ev(900, 'player_first_join', '', ['n' => 1], 'NewPlayer');
// Missiles (dernières 24 h).
$warheads = ['ballistix:explosive', 'ballistix:nuclear', 'ballistix:cluster', 'ballistix:explosive', 'ballistix:emp'];
$locs = [['Aurelia', 'Dorne', [-400, -300], [800, 600]], ['Borealis', 'Cendra', [1200, -900], [-300, 1000]], ['Aurelia', 'Cendra', [-380, -320], [-250, 1100]], ['Dorne', 'Aurelia', [820, 590], [-390, -310]]];
for ($i = 0; $i < 12; $i++) {
    [$from, $to, $o, $t] = $locs[$i % 4];
    $ago = 80000 - $i * 6000;
    $tier = 1 + $i % 5;
    $mid = 'msl-' . $i;
    $ev($ago, 'missile_launched', $from, [
        'missile_id' => $mid, 'tier' => $tier, 'carrier' => 'ballistix:missiletier' . min(3, $tier), 'warhead' => $warheads[$i % 5], 'flight' => ['SILO', 'VLS', 'ROCKET_LAUNCHER'][$i % 3], 'airburst' => $i % 2 === 0,
        'from' => ['x' => $o[0], 'z' => $o[1], 'world' => 'world'], 'target' => ['x' => $t[0], 'z' => $t[1], 'world' => 'world'], 'target_faction' => $to,
        'distance' => round(hypot($o[0] - $t[0], $o[1] - $t[1])), 'eta_s' => 40 + $i,
    ]);
    if ($i % 3 === 0) { $ev($ago + 40, 'missile_intercepted', $to, ['missile_id' => $mid, 'by' => 'samturret', 'defense_faction' => $to, 'at_pct' => 40]); }
    elseif ($i % 3 === 1) { $ev($ago + 50, 'missile_impact', $from, ['missile_id' => $mid, 'tier' => $tier, 'warhead' => $warheads[$i % 5], 'blocks_destroyed' => 100 * $tier, 'kills' => $i % 4, 'damage_zone_faction' => $to, 'claims_hit' => 2]); }
}
$ev(70000, 'missile_refused', 'Cendra', ['reason' => 'not_at_war']);
$ev(20000, 'missile_shot_down_native', 'Borealis', ['missile_id' => 'native-1']);
$db->commit();
