<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsServer;
use Illuminate\Support\Facades\Cache;

class ServerStatusController extends Controller
{
    public function getServersStatus()
    {
        $servers = OptionsServer::all();
        $statuses = [];

        foreach ($servers as $server) {
            $statuses[] = [
                'id'         => $server->server_id,
                'name'       => $server->server_name,
                'ip'         => $server->server_ip,
                'port'       => (int) $server->server_port,
                'online'     => $this->pingServer($server->server_ip, (int) $server->server_port),
                'is_default' => (bool) $server->is_default,
            ];
        }

        return response()->json($statuses, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function pingServer(string $ip, int $port): bool
    {
        return Cache::remember("server_ping_{$ip}_{$port}", 30, function () use ($ip, $port) {
            $socket = @fsockopen($ip, $port, $errno, $errstr, 2);
            if ($socket) {
                fclose($socket);
                return true;
            }
            return false;
        });
    }
}
