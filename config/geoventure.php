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
        // Roster par faction (faction + uuid) : alimente members_list, utilisé
        // par le launcher pour le badge « votre faction ». gf_members ne stocke
        // que des UUID → les pseudos sont résolus via la DB Azuriom
        // (names_query, matching game_id sans tirets, insensible à la casse).
        // Mettre members_query à null (env vide) pour désactiver.
        'members_query' => env(
            'GEO_FACTIONS_MEMBERS_QUERY',
            'SELECT f.name AS faction, m.player_id AS uuid '
            . 'FROM gf_members m JOIN gf_factions f ON f.id = m.faction_id'
        ),
        'names_connection' => env('GEO_FACTIONS_NAMES_CONNECTION', 'azuriom'),
        'names_query' => env(
            'GEO_FACTIONS_NAMES_QUERY',
            'SELECT name, game_id FROM users WHERE game_id IS NOT NULL LIMIT 5000'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Season standings (classement de la saison en cours)
    |--------------------------------------------------------------------------
    |
    | GET /utils/seasons enrichit la saison en cours avec un top des factions
    | (points de saison), lus dans la DB GeoFactions externe (connexion 'game') :
    | table gf_season_points (season_id 'AAAA-MM', faction_id, points) jointe à
    | gf_factions pour le nom. L'id de saison est passé en BINDING (?), jamais
    | concaténé. Fail-safe : DB non configurée ou erreur → standings [] (200).
    |
    */

    'season_standings' => [
        // Which DB connection to query (default: the GeoFactions DB).
        'connection' => env('GEO_SEASON_STANDINGS_CONNECTION', 'game'),
        'limit' => (int) env('GEO_SEASON_STANDINGS_LIMIT', 10),
        // Must return: name, points (ordered DESC). The current season id
        // ('AAAA-MM' = external_id) is bound to the single ? placeholder.
        'query' => env(
            'GEO_SEASON_STANDINGS_QUERY',
            'SELECT f.name, p.points FROM gf_season_points p '
            . 'JOIN gf_factions f ON f.id = p.faction_id '
            . 'WHERE p.season_id = ? ORDER BY p.points DESC LIMIT 10'
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

    /*
    |--------------------------------------------------------------------------
    | Scheduled events ingestion
    |--------------------------------------------------------------------------
    |
    | Jeton partagé attendu sur POST /utils/scheduled-events/claim (le plugin
    | GeoFactions réclame les événements arrivés à échéance et les déclenche).
    | Lu via config() pour rester compatible avec `php artisan config:cache`.
    | Vide → tous les claims sont rejetés (403, fail-closed).
    |
    */

    'events' => [
        'ingest_token' => env('EVENTS_INGEST_TOKEN', ''),
    ],

];
