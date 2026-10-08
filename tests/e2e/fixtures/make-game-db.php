<?php
// Fixture E2E : base SQLite jetable imitant la base du jeu alimentée par le plugin (contrat gf_metrics / gf_events / gf_rollup).
// Usage : php make-game-db.php <fichier.sqlite> <full|nocat|empty>
//   full  : tables + données factices (relatives à l'heure courante) + catalogue de noms gf_catalog
//   nocat : idem SANS la table gf_catalog (repli lisible : noms déduits des identifiants)
//   empty : base valide SANS les tables d'analyse (écran explicatif « no_tables »)
[$_, $path, $mode] = $argv + [null, null, 'full'];
if (! $path) { fwrite(STDERR, "chemin requis\n"); exit(1); }
@unlink($path);
$db = new PDO('sqlite:' . $path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (! in_array($mode, ['full', 'nocat'], true)) { $db->exec('CREATE TABLE gf_factions (id INTEGER PRIMARY KEY, name TEXT)'); exit(0); }

$db->exec("CREATE TABLE gf_metrics (id INTEGER PRIMARY KEY AUTOINCREMENT, ts BIGINT NOT NULL, scope VARCHAR(16) NOT NULL, ref VARCHAR(64) NOT NULL DEFAULT '', metric VARCHAR(48) NOT NULL, value DOUBLE NOT NULL, extra VARCHAR(255))");
$db->exec('CREATE INDEX idx_metric ON gf_metrics (scope, metric, ref, ts)');
$db->exec("CREATE TABLE gf_rollup (id INTEGER PRIMARY KEY AUTOINCREMENT, ts BIGINT NOT NULL, scope VARCHAR(16) NOT NULL, ref VARCHAR(64) NOT NULL DEFAULT '', metric VARCHAR(48) NOT NULL, value DOUBLE NOT NULL, extra VARCHAR(255))");
$db->exec("CREATE TABLE gf_events (id INTEGER PRIMARY KEY AUTOINCREMENT, ts BIGINT NOT NULL, type VARCHAR(32) NOT NULL, ref VARCHAR(64) NOT NULL, actor VARCHAR(64), data TEXT)");
$db->exec('CREATE INDEX idx_type_ts ON gf_events (type, ts)');
if ($mode === 'full') {
    $db->exec('CREATE TABLE gf_catalog (kind VARCHAR(24) NOT NULL, id VARCHAR(96) NOT NULL, label VARCHAR(128) NOT NULL, extra TEXT, updated_at BIGINT NOT NULL DEFAULT 0, PRIMARY KEY (kind, id))');
}

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
        ['oil', 'zone_nord', 'reserve_pct', 100 - $k * 35, 'Golfe Nord'], ['oil', 'zone_nord', 'reserve', 500000 * (1 - $k * 0.35), 'Golfe Nord'], ['oil', 'zone_nord', 'extracted_total', 100000 * $k, 'Golfe Nord'], ['oil', 'zone_nord', 'wells', 6, 'Golfe Nord'],
        ['oil', 'zone_sud', 'reserve_pct', 100 - $k * 100, 'Désert du Sud'], ['oil', 'zone_sud', 'reserve', 200000 * (1 - $k), 'Désert du Sud'], ['oil', 'zone_sud', 'extracted_total', 200000 * $k, 'Désert du Sud'], ['oil', 'zone_sud', 'wells', 2, 'Désert du Sud'],
        ['oil', 'zone_est', 'reserve_pct', 70 - $k * 4, 'Plateau Est'], ['oil', 'zone_est', 'reserve', 350000 * (0.7 - $k * 0.04), 'Plateau Est'], ['oil', 'zone_est', 'extracted_total', 20000 * $k, 'Plateau Est'], ['oil', 'zone_est', 'wells', 3, 'Plateau Est'],
        ['server', '', 'gc_pause_ms', 8 + mt_rand(0, 25)], ['server', '', 'uptime_s', ($n - $i) * $step + 86400], ['server', '', 'threads', 64 + mt_rand(0, 6)], ['server', '', 'lag_spikes_24h', mt_rand(0, 4)],
        ['economy', '', 'gdp', 2.4e6 + $k * 3e5], ['economy', '', 'gini', 0.42 + $k * 0.03], ['economy', '', 'tax_revenue_24h', 9000 + mt_rand(0, 1500)],
        ['economy', '', 'trade_volume_24h', 120000 + mt_rand(0, 15000)], ['economy', '', 'inflation_pct', 1.2 + $k],
        ['oil', '', 'extracted_24h', 18000 + mt_rand(0, 2000)],
        ['pollution', 'world', 'avg_level', 800 + $k * 400], ['pollution', 'world', 'max_level', 4000 + $k * 2000], ['pollution', 'world', 'chunks_over_hazard', (int) (3 + $k * 9)],
        ['pollution', 'world_nether', 'avg_level', 200 + $k * 50], ['pollution', 'world_nether', 'max_level', 900], ['pollution', 'world_nether', 'chunks_over_hazard', 0],
    ] as $row) { [$sc, $ref, $met, $v] = $row; $m->execute([$ts, $sc, $ref, $met, round($v, 2), $row[4] ?? null]); }
    $ci = 0;
    foreach ($countries as $c => $w) {
        $ci++;
        foreach ([
            'members' => 10 + 30 * $w + $k * 6, 'members_online' => max(0, 3 * $w + 3 * $day + 2), 'power' => 200 * $w + $k * 80 * $w, 'claims' => 30 * $w + $k * 10,
            'bank' => 50000 * $w * (1 + $k), 'research_points' => 400 * $w + $k * 300 * $w, 'research_unlocked' => floor(8 * $w + $k * 6 * $w), 'age_index' => floor(1 + 3 * $w * $k),
            'wars_active' => $c === 'Aurelia' || $c === 'Dorne' ? 1 : 0, 'rating_week' => 10 + 6 * $w, 'emissions' => 1000 * $w + $k * 800 * $w,
            'convoys_active' => $ci, 'plots' => 2 * $ci, 'playtime_hours_week' => 120 * $w,
            'gdp' => 6e5 * $w * (1 + $k / 5), 'trade_balance' => ($ci % 2 ? 1 : -1) * 4000 * $w, 'tax_revenue_24h' => 2500 * $w,
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
// Cohortes de rétention hebdomadaires.
foreach ([['2026-W37', 120, [48, 33, 25, 21]], ['2026-W38', 96, [52, 36, 27, null]], ['2026-W39', 84, [45, 30, null, null]], ['2026-W40', 70, [50, null, null, null]]] as [$coh, $size, $ret]) {
    $m->execute([$now - 3600, 'players', $coh, 'cohort_size', $size, null]);
    foreach ($ret as $wi => $rv) { if ($rv !== null) { $m->execute([$now - 3600, 'players', $coh, 'retention_w' . ($wi + 1), $rv, null]); } }
}
// Succès : taux de déblocage.
foreach ([['first_steps', 88, 'Premiers pas'], ['builder_i', 61, 'Bâtisseur'], ['world_tour', 12, 'Tour du monde'], ['nuclear_winner', 2, 'Hiver nucléaire']] as [$code, $pc, $nm]) {
    $m->execute([$now - 3600, 'achievements', $code, 'unlocked', round($pc * 6.1), $nm]);
    $m->execute([$now - 3600, 'achievements', $code, 'unlocked_pct', $pc, $nm]);
}
// Classements joueurs (pseudos sans underscore ; un UUID non résolu pour tester le repli).
foreach (['playtime_hours' => [412, 380, 351, 300, 210], 'kills' => [980, 760, 640, 410, 120], 'wealth' => [820000, 640000, 410000, 220000, 90000]] as $cat => $vals) {
    foreach (['Alyx', 'Brenn', 'Cora', 'Dax', '3f2b8c1e-5d4a-4b7e-9c1f-0a2b3c4d5e6f'] as $i => $pl) { $m->execute([$now - 3600, 'ranking', $pl, $cat, $vals[$i], null]); }
}
// Rollup : données anciennes (journalières) pour tester la fusion metrics + rollup.
for ($d = 60; $d > 8; $d--) {
    $ts = $now - $d * 86400;
    $r->execute([$ts, 'server', '', 'players_online', 8 + ($d % 5), null]);
    $r->execute([$ts, 'players', '', 'dau', 20 + ($d % 7), null]);
}
// Usage : dernier lot (extra = identifiant).
$usage = [
    ['weapon_kills_24h', '', 410, 'superbwarfare:ak_47'], ['weapon_kills_24h', '', 305, 'superbwarfare:m_870'], ['weapon_kills_24h', '', 190, 'minecraft:bow'], ['weapon_kills_24h', '', 88, 'megasarms:iron_sword'],
    ['convoys_delivered_24h', 'Aurelia', 12, null], ['convoys_delivered_24h', 'Borealis', 5, null], ['train_trips_24h', '', 33, null],
    ['crates_opened_24h', '', 500, 'common'], ['crates_opened_24h', '', 120, 'rare'], ['crates_opened_24h', '', 4, 'legendary'],
    ['bourse_orders_24h', '', 160, 'buy'], ['bourse_orders_24h', '', 100, 'sell'], ['bourse_shares_sold_24h', '', 840, null], ['bourse_value_sold_24h', '', 51000, null],
    ['wheel_spins_24h', '', 90, 'rare'], ['wheel_spins_24h', '', 60, 'common'], ['tombola_draws_24h', '', 900, null], ['arcade_matches_24h', '', 64, null], ['arcade_players_24h', '', 210, null],
    ['bosses_killed_24h', '', 7, 'lich'], ['disasters_started_24h', '', 2, 'volcano'], ['nuclear_meltdowns_24h', '', 1, null], ['oil_spills_24h', '', 3, null],
];
foreach ([$now - 3600, $now - 1800] as $ts) {
    foreach ($usage as [$met, $ref, $v, $ex]) { $m->execute([$ts, 'usage', $ref, $met, $v - ($ts < $now - 2000 ? 5 : 0), $ex]); }
}

// Événements.
$ev = function (int $ago, string $type, string $ref, array $data, ?string $actor = null) use ($e, $now) { $e->execute([$now - $ago, $type, $ref, $actor, json_encode($data)]); };
$ev(600000, 'faction_created', 'Dorne', ['leader' => 'SECRETPLAYER'], 'SecretPlayer');
$ev(520000, 'research_unlocked', 'Aurelia', ['id' => 'economy_1', 'name' => 'Marché libre', 'cost' => 300, 'branch' => 'economy']);
$ev(510000, 'research_unlocked', 'Aurelia', ['id' => 'military_1', 'name' => 'Armée de métier', 'cost' => 450]);
$ev(400000, 'research_unlocked', 'Aurelia', ['id' => 'industry_2', 'name' => 'Hauts-fourneaux', 'cost' => 600]);
$ev(300000, 'research_unlocked', 'Borealis', ['id' => 'military_1', 'name' => 'Armée de métier', 'cost' => 450, 'branch' => 'military']);
$ev(200000, 'age_changed', 'Aurelia', ['from' => 'ancient', 'to' => 'industrial']);
$ev(150000, 'alliance_formed', 'Aurelia', ['with' => 'Cendra']);
$ev(700000, 'war_declared', 'Borealis', ['target' => 'Cendra', 'casus' => 'border']);
$ev(450000, 'war_ended', 'Borealis', ['winner' => 'Cendra', 'how' => 'score']);
$ev(30000, 'war_declared', 'Cendra', ['target' => 'Dorne', 'casus' => 'embargo']);
$ev(100000, 'war_declared', 'Aurelia', ['target' => 'Dorne', 'casus' => 'border']);
$ev(1400, 'war_kill', 'Aurelia', ['target' => 'Dorne', 'kills_a' => 31, 'kills_b' => 17]);
$ev(90000, 'alliance_formed', 'Borealis', ['with' => 'Cendra']);
$ev(80000, 'oil_zone_depleted', 'zone_sud', ['zone' => 'zone_sud']);
$ev(70000, 'meltdown', 'Cendra', ['x' => 120, 'z' => -40]);
$ev(60000, 'siege_started', 'Aurelia', ['target' => 'Dorne']);
$ev(50000, 'boss_killed', 'Borealis', ['boss' => 'lich']);
// Territoires : instantanés de claims par pays (runs [cz, cx0, cx1]) à deux dates.
$terr = ['Aurelia' => [-12, -8, 0, 0, 6], 'Borealis' => [10, 4, 4, 0, 6], 'Cendra' => [-2, 14, 12, 1, 6], 'Dorne' => [-10, 22, 20, 2, 5]];
foreach ([864000 => 0, 3600 => 2] as $ago => $grow) {
    foreach ($terr as $cn => [$cx0, $cz0, $cx1, $dz, $rows]) {
        $runs = [];
        for ($r = 0; $r < $rows + $grow; $r++) { $runs[] = [$cz0 + $r, $cx0, $cx0 + 3 + ($r % 3) + ($cn === 'Aurelia' ? $grow : 0)]; }
        $ev($ago, 'territory_snapshot', $cn, ['world' => 'world', 'runs' => $runs]);
    }
}
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
// Catalogue de noms (mode full uniquement).
if ($mode === 'full') {
    $c = $db->prepare('INSERT INTO gf_catalog (kind, id, label, extra, updated_at) VALUES (?,?,?,?,?)');
    $cat = [
        ['research', 'economy_1', 'Marché libre', ['branch' => 'ECONOMY', 'age' => 'ancient', 'cost' => 300]],
        ['research', 'economy_2', 'Banque centrale', ['branch' => 'ECONOMY', 'age' => 'industrial', 'cost' => 800]],
        ['research', 'industry_1', 'Forge royale', ['branch' => 'INDUSTRY', 'age' => 'ancient', 'cost' => 250]],
        ['research', 'industry_2', 'Hauts-fourneaux', ['branch' => 'INDUSTRY', 'age' => 'industrial', 'cost' => 600]],
        ['research', 'military_1', 'Armée de métier', ['branch' => 'MILITARY', 'age' => 'ancient', 'cost' => 450]],
        ['research', 'military_2', 'Artillerie lourde', ['branch' => 'MILITARY', 'age' => 'industrial', 'cost' => 900]],
        ['research', 'science_1', 'Alchimie', ['branch' => 'SCIENCE', 'age' => 'ancient', 'cost' => 200]],
        ['branch', 'ECONOMY', 'Économie', []], ['branch', 'INDUSTRY', 'Industrie', []], ['branch', 'MILITARY', 'Armée', []], ['branch', 'SCIENCE', 'Sciences', []],
        ['age', 'ancient', 'Antiquité', ['index' => 0]], ['age', 'industrial', 'Ère industrielle', ['index' => 1]],
        ['oil_zone', 'zone_nord', 'Golfe Nord', ['world' => 'world', 'capacity' => 500000]], ['oil_zone', 'zone_sud', 'Désert du Sud', ['world' => 'world', 'capacity' => 200000]],
        ['oil_zone', 'zone_est', 'Plateau Est', ['world' => 'world']],
        ['world', 'world', 'Terre (1:750)', []], ['world', 'world_nether', 'Nether', []],
        ['faction', 'Aurelia', 'Aurelia', ['color' => '#22d3ee']],
        ['warhead', 'ballistix:explosive', 'Charge explosive', []], ['warhead', 'ballistix:nuclear', 'Bombe nucléaire', []], ['warhead', 'ballistix:cluster', 'Ogive à fragmentation', []], ['warhead', 'ballistix:emp', 'Impulsion électromagnétique', []],
        ['carrier', 'ballistix:missiletier1', 'Missile de palier 1', []], ['carrier', 'ballistix:missiletier2', 'Missile de palier 2', []], ['carrier', 'ballistix:missiletier3', 'Missile de palier 3', []],
        ['weapon', 'superbwarfare:ak_47', 'AK-47', []], ['weapon', 'superbwarfare:m_870', 'Fusil à pompe M870', []], ['weapon', 'minecraft:bow', 'Arc', []], ['weapon', 'megasarms:iron_sword', 'Épée en fer', []],
        ['crate', 'common', 'Caisse commune', []], ['crate', 'rare', 'Caisse rare', []], ['crate', 'legendary', 'Caisse légendaire', []],
        ['item', 'lich', 'Liche', []], ['item', 'samturret', 'Tourelle SAM', []],
        ['achievement', 'first_steps', 'Premiers pas', ['rarity' => 'common', 'points' => 10]], ['achievement', 'builder_i', 'Bâtisseur', ['rarity' => 'uncommon', 'points' => 25]],
        ['achievement', 'world_tour', 'Tour du monde', ['rarity' => 'epic', 'points' => 80]], ['achievement', 'nuclear_winner', 'Hiver nucléaire', ['rarity' => 'legendary', 'points' => 200]],
        ['event_type', 'war_kill', 'Bilan de guerre', []], ['event_type', 'territory_snapshot', 'Instantané de territoire', []],
        ['metric', 'players_online', 'Joueurs connectés', ['unit' => '']], ['metric', 'ram_used_mb', 'Mémoire utilisée', ['unit' => 'Mo']], ['metric', 'gdp', 'Produit intérieur brut', ['unit' => 'GC']],
        ['metric', 'weapon_kills_24h', 'Victimes par arme (24 h)', ['unit' => '']], ['metric', 'crates_opened_24h', 'Caisses ouvertes (24 h)', ['unit' => '']],
    ];
    foreach ($cat as [$k, $id, $lab, $x]) { $c->execute([$k, $id, $lab, $x ? json_encode($x, JSON_UNESCAPED_UNICODE) : null, $now]); }
}
$db->commit();
