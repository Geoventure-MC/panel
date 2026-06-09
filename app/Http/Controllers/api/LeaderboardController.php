<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GET /utils/leaderboards — feeds the launcher Profile "Classement" tab.
 *
 * Reads from a configurable external connection (default 'azuriom' → player
 * name + money) using a configurable query (config/geoventure.php). Always
 * fails safe: returns [] (200) when the DB or query is not configured, or on
 * any error, so the launcher shows a clean empty state instead of a 404/500.
 */
class LeaderboardController extends Controller
{
    public function getLeaderboards()
    {
        $players = $this->fetch();

        return response()->json($players, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function fetch(): array
    {
        $cfg = config('geoventure.leaderboard', []);
        $query = $cfg['query'] ?? null;
        if (empty($query)) {
            return [];
        }

        $connection = $cfg['connection'] ?? 'azuriom';
        $limit = (int) ($cfg['limit'] ?? 50);
        $ttl = (int) config('geoventure.cache_ttl', 60);

        try {
            return Cache::remember('geo_leaderboards', $ttl, function () use ($connection, $query, $limit) {
                $rows = DB::connection($connection)->select($query);

                $out = [];
                $rank = 1;
                foreach (array_slice($rows, 0, $limit) as $row) {
                    $row = (array) $row;
                    $entry = ['rank' => $rank++, 'name' => $row['name'] ?? '?'];
                    if (array_key_exists('coins', $row)) {
                        $entry['coins'] = (int) round((float) $row['coins']);
                    }
                    if (array_key_exists('playtime', $row)) {
                        $entry['playtime'] = (int) $row['playtime'];
                    }
                    $out[] = $entry;
                }
                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('LeaderboardController: ' . $e->getMessage());
            return [];
        }
    }
}
