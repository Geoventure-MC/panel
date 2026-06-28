<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Launcher community endpoints (leaderboards & factions)
    |--------------------------------------------------------------------------
    |
    | The launcher's Profile panel reads GET /utils/leaderboards and
    | GET /utils/factions. This data lives in external MySQL databases, NOT in
    | the panel's own (SQLite) DB:
    |
    |   - leaderboards → the Azuriom DB ('azuriom' connection): player name + money.
    |   - factions     → the GeoFactions plugin DB ('game' connection): gf_* tables.
    |
    | Configure the connections in config/database.php (GEO_AZ_DB_* / GEO_GAME_DB_*),
    | then optionally override the queries below. Both endpoints fail safe: any
    | error or unreachable DB yields [] (200) so the launcher shows a clean empty
    | state instead of a 404/500. Set a query to null to disable that endpoint.
    |
    */

    'cache_ttl' => (int) env('GEO_COMMUNITY_CACHE_TTL', 60),

    'leaderboard' => [
        // Which DB connection to query (default: the Azuriom DB).
        'connection' => env('GEO_LEADERBOARD_CONNECTION', 'azuriom'),
        'limit' => (int) env('GEO_LEADERBOARD_LIMIT', 50),
        // Must return: name, and one of coins / playtime (ordered DESC).
        'query' => env(
            'GEO_LEADERBOARD_QUERY',
            'SELECT name, money AS coins FROM users WHERE banned = 0 ORDER BY money DESC LIMIT 50'
        ),
    ],

    'factions' => [
        // Which DB connection to query (default: the GeoFactions DB).
        'connection' => env('GEO_FACTIONS_CONNECTION', 'game'),
        'limit' => (int) env('GEO_FACTIONS_LIMIT', 50),
        // Must return: name, tag, color, members, online, power, bank.
        // 'online' is runtime-only (not in the DB) → reported as null.
        'query' => env(
            'GEO_FACTIONS_QUERY',
            'SELECT f.name, f.tag, f.color, '
            . '(SELECT COUNT(*) FROM gf_members m WHERE m.faction_id = f.id) AS members, '
            . 'f.bank AS bank, f.research_points AS power '
            . 'FROM gf_factions f ORDER BY f.bank DESC LIMIT 50'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Achievements ingestion
    |--------------------------------------------------------------------------
    |
    | Jeton partagé attendu sur POST /utils/achievements/unlock (signalé par le
    | plugin GeoFactions). Lu via config() pour rester compatible avec
    | `php artisan config:cache` (env() hors fichiers de config renvoie null une
    | fois la config mise en cache). Vide → tous les déverrouillages sont rejetés
    | (fail-closed).
    |
    */

    'achievements' => [
        'ingest_token' => env('ACHIEVEMENTS_INGEST_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seasons ingestion
    |--------------------------------------------------------------------------
    |
    | Jeton partagé attendu sur POST /utils/seasons/sync (signalé par le plugin
    | GeoFactions au début/fin de chaque saison). Lu via config() pour rester
    | compatible avec `php artisan config:cache`. Vide → tous les syncs sont
    | rejetés (403, fail-closed).
    |
    */

    'seasons' => [
        'ingest_token' => env('SEASONS_INGEST_TOKEN', ''),
    ],

];
