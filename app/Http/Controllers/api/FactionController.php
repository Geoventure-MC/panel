<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GET /utils/factions — feeds the launcher Profile "Factions" tab.
 *
 * Reads from a configurable external connection (default 'game' → the
 * GeoFactions plugin's MySQL) using a configurable query (config/geoventure.php).
 * Always fails safe: returns [] (200) when unconfigured or on error.
 */
class FactionController extends Controller
{
    public function getFactions()
    {
        $factions = $this->fetch();

        return response()->json($factions, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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

        try {
            return Cache::remember('geo_factions', $ttl, function () use ($connection, $query, $limit) {
                $rows = DB::connection($connection)->select($query);

                $out = [];
                foreach (array_slice($rows, 0, $limit) as $row) {
                    $row = (array) $row;
                    $out[] = [
                        'name'    => $row['name'] ?? '?',
                        'tag'     => $row['tag'] ?? null,
                        'color'   => isset($row['color']) ? (int) $row['color'] : null,
                        'members' => isset($row['members']) ? (int) $row['members'] : null,
                        'online'  => isset($row['online']) ? (int) $row['online'] : null,
                        'power'   => isset($row['power']) ? (int) $row['power'] : null,
                        'bank'    => isset($row['bank']) ? (int) $row['bank'] : null,
                    ];
                }
                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('FactionController: ' . $e->getMessage());
            return [];
        }
    }
}
