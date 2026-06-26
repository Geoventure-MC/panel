<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GET /utils/factions — feeds the launcher Profile "Factions" tab.
 *
 * Reads from a configurable external connection (default 'game' → the
 * GeoFactions plugin's MySQL) using a configurable query (config/geoventure.php).
 * Always fails safe: returns [] (200) when unconfigured or on error.
 *
 * Like /utils/leaderboards, this supports cheap polling: ETag + Cache-Control
 * let the launcher get a 304 Not Modified when nothing changed. Plain polling
 * (no SSE/WebSocket) is intentional for shared-hosting robustness.
 */
class FactionController extends Controller
{
    public function getFactions(Request $request)
    {
        $factions = $this->fetch();

        $body = json_encode($factions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $etag = '"' . md5($body) . '"';
        $headers = [
            'Content-Type'  => 'application/json',
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=30',
        ];

        if (trim($request->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
    }

    private function fetch(): array
    {
        $cfg = config('geoventure.factions', []);
        $query = $cfg['query'] ?? null;
        if (empty($query)) {
            return [];
        }

        $connection = $cfg['connection'] ?? 'game';
        $limit = (int) ($cfg['limit'] ?? 50);
        $ttl = (int) config('geoventure.cache_ttl', 60);

        // Connexion externe non configurée (database vide) → on n'essaie même
        // pas de se connecter : on renvoie [] directement.
        if (empty(config("database.connections.{$connection}.database"))) {
            return [];
        }

        try {
            return Cache::remember('geo_factions', $ttl, function () use ($cfg, $connection, $query, $limit) {
                $rows = DB::connection($connection)->select($query);

                $rosters = $this->fetchRosters($cfg, $connection);

                $out = [];
                foreach (array_slice($rows, 0, $limit) as $row) {
                    $row = (array) $row;
                    $name = $row['name'] ?? '?';
                    $entry = [
                        'name'    => $name,
                        'tag'     => $row['tag'] ?? null,
                        'color'   => isset($row['color']) ? (int) $row['color'] : null,
                        'members' => isset($row['members']) ? (int) $row['members'] : null,
                        'online'  => isset($row['online']) ? (int) $row['online'] : null,
                        'power'   => isset($row['power']) ? (int) $row['power'] : null,
                        'bank'    => isset($row['bank']) ? (int) $row['bank'] : null,
                    ];
                    if (isset($rosters[$name])) {
                        $entry['members_list'] = $rosters[$name];
                    }
                    $out[] = $entry;
                }
                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('FactionController: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Pseudos par faction (members_list) : gf_members ne stocke que des UUID,
     * les pseudos sont résolus via la DB Azuriom (users.game_id). Le matching
     * UUID se fait sans tirets et en minuscules pour tolérer les deux formats.
     * Entièrement optionnel : toute erreur / config absente → pas de champ.
     */
    private function fetchRosters(array $cfg, string $connection): array
    {
        $membersQuery = $cfg['members_query'] ?? null;
        $namesQuery = $cfg['names_query'] ?? null;
        $namesConnection = $cfg['names_connection'] ?? 'azuriom';

        if (empty($membersQuery) || empty($namesQuery)
            || empty(config("database.connections.{$namesConnection}.database"))) {
            return [];
        }

        try {
            $names = [];
            foreach (DB::connection($namesConnection)->select($namesQuery) as $row) {
                $row = (array) $row;
                if (!empty($row['game_id']) && !empty($row['name'])) {
                    $key = strtolower(str_replace('-', '', (string) $row['game_id']));
                    $names[$key] = (string) $row['name'];
                }
            }
            if (!$names) {
                return [];
            }

            $rosters = [];
            foreach (DB::connection($connection)->select($membersQuery) as $row) {
                $row = (array) $row;
                if (empty($row['faction']) || empty($row['uuid'])) {
                    continue;
                }
                $key = strtolower(str_replace('-', '', (string) $row['uuid']));
                if (isset($names[$key])) {
                    $rosters[(string) $row['faction']][] = $names[$key];
                }
            }
            return $rosters;
        } catch (\Throwable $e) {
            Log::warning('FactionController rosters: ' . $e->getMessage());
            return [];
        }

class FactionController extends Controller
{
    public function getFactions()
    {
        return response()->json([], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
