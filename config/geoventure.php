<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Launcher community endpoints (leaderboards & factions)
    |--------------------------------------------------------------------------
    |
    | The launcher's Profile panel reads /utils/leaderboards and /utils/factions.
    | This data lives in the external GeoFactions / economy MySQL database (the
    | Minecraft server), not in the panel. Point the 'game' connection
    | (config/database.php → GEO_GAME_DB_*) at it, then provide a SELECT query
    | below that returns the expected columns. Both endpoints fail safe: any
    | error, or an unconfigured DB, yields an empty array (no 5xx, no 404).
    |
    | Expected columns:
    |   leaderboard → name, coins (or playtime)   [ordered DESC, LIMIT applied]
    |   factions    → name, tag, color, members, online, power, bank
    |
    | Leave a query null to disable that endpoint (returns []).
    |
    */

    // Whether a game DB is configured at all.
    'game_db_enabled' => env('GEO_GAME_DB_DATABASE', '') !== '',

    // Cache TTL (seconds) for both endpoints.
    'cache_ttl' => (int) env('GEO_COMMUNITY_CACHE_TTL', 60),

    // Max rows returned.
    'leaderboard_limit' => (int) env('GEO_LEADERBOARD_LIMIT', 50),
    'factions_limit' => (int) env('GEO_FACTIONS_LIMIT', 50),

    // Raw SELECT queries against the 'game' connection. Override via .env when
    // the GeoFactions schema differs. Examples below assume common table names;
    // adjust to your plugin's actual schema.
    'leaderboard_query' => env(
        'GEO_LEADERBOARD_QUERY',
        null
        // e.g. 'SELECT username AS name, balance AS coins FROM economy_accounts ORDER BY balance DESC LIMIT 50'
    ),

    'factions_query' => env(
        'GEO_FACTIONS_QUERY',
        null
        // e.g. 'SELECT name, tag, color, member_count AS members, online_count AS online, power, bank FROM factions ORDER BY power DESC LIMIT 50'
    ),

];
