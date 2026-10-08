<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsServer;
use App\Models\ServerCheck;
use App\Services\ServerMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /utils/uptime — disponibilité publique minimale des serveurs.
 * { servers: [{ id, name, online, uptime24h, uptime7d }] } (pourcentages ou
 * null sans donnée). Aucune IP/port. Fail-safe : erreur → { servers: [] }.
 * ETag + Cache-Control max-age=30 (304 sur If-None-Match).
 */
class UptimeController extends Controller
{
    /** @return array<int, array<string, mixed>> */
    public static function rows(): array
    {
        try {
            return Cache::remember('utils_uptime_rows', 30, function () {
                $uptime = app(ServerMonitor::class)->uptimeAll();
                $rows = [];
                foreach (OptionsServer::all() as $server) {
                    $key = ServerMonitor::serverKey($server);
                    $last = ServerCheck::where('server_key', $key)->latest('created_at')->first();
                    // Une sonde de plus de 10 min n'est plus un état fiable.
                    $online = $last && $last->created_at->gt(now()->subMinutes(10)) ? (bool) $last->online : false;
                    $rows[] = [
                        'id'        => $key,
                        'name'      => $server->server_name,
                        'online'    => $online,
                        'uptime24h' => $uptime[$key]['h24'] ?? null,
                        'uptime7d'  => $uptime[$key]['d7'] ?? null,
                    ];
                }

                return $rows;
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function index(Request $request)
    {
        $body = json_encode(['servers' => self::rows()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $etag = '"' . md5($body) . '"';
        $headers = ['Content-Type' => 'application/json', 'ETag' => $etag, 'Cache-Control' => 'public, max-age=30'];

        if (trim($request->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
    }
}
