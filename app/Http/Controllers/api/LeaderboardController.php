<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GET /utils/leaderboards — feeds the launcher Profile "Classement" tab.
 *
 * Reads from the optional external 'game' DB (config/database.php) using a
 * configurable query (config/geoventure.php). Always fails safe: returns an
 * empty array (200) when the DB or query is not configured, or on any error,
 * so the launcher shows a clean empty state instead of a 404/500.
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
        if (!config('geoventure.game_db_enabled')) {
            return [];
        }

        $query = config('geoventure.leaderboard_query');
        if (empty($query)) {
            return [];
        }

        $ttl = (int) config('geoventure.cache_ttl', 60);
        $limit = (int) config('geoventure.leaderboard_limit', 50);

        try {
            return Cache::remember('geo_leaderboards', $ttl, function () use ($query, $limit) {
                $rows = DB::connection('game')->select($query);

                $out = [];
                $rank = 1;
                foreach (array_slice($rows, 0, $limit) as $row) {
                    $row = (array) $row;
                    $entry = ['rank' => $rank++, 'name' => $row['name'] ?? '?'];
                    if (array_key_exists('coins', $row)) {
                        $entry['coins'] = (int) $row['coins'];
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
