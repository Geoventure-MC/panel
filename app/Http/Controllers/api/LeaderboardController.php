<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
 *
 * Live refresh: the launcher polls this endpoint every ~30s. We deliberately
 * use plain HTTP polling + ETag/Cache-Control (no SSE/WebSocket) because the
 * panel runs on shared hosting where long-lived connections are unreliable.
 * The data is cached server-side (30s) and an ETag (md5 of the payload) lets
 * the launcher get a cheap 304 Not Modified when nothing changed.
 */
class LeaderboardController extends Controller
{
    public function getLeaderboards(Request $request)
    {
        $players = $this->fetch();

        $body = json_encode($players, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $etag = '"' . md5($body) . '"';
        $headers = [
            'Content-Type'  => 'application/json',
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=30',
        ];

        // Réponse 304 si le client a déjà la même version (économise la
        // re-sérialisation et la bande passante sur les polls répétés).
        if (trim($request->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
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
        // Live refresh : on aligne le cache données sur le poll launcher (~30s)
        // pour que le classement reste frais, sans dépasser le TTL configuré.
        $ttl = min((int) config('geoventure.cache_ttl', 60), 30);

        // Connexion externe non configurée (database vide) → on n'essaie même
        // pas de se connecter : on renvoie [] directement.
        if (empty(config("database.connections.{$connection}.database"))) {
            return [];
        }

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

class LeaderboardController extends Controller
{
    public function getLeaderboards()
    {
        return response()->json([], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
