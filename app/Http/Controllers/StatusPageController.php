<?php

namespace App\Http\Controllers;

use App\Http\Controllers\api\ServerStatusController;
use Illuminate\Support\Facades\Log;

/**
 * Page de statut publique (/status) — partageable (Discord, etc.), sans auth.
 * N'utilise QUE les statuts en cache (aucun ping SLP bloquant) ; le JS de la
 * page se rafraîchit via l'endpoint public /utils/servers-status.
 */
class StatusPageController extends Controller
{
    public function index(ServerStatusController $statusController)
    {
        try {
            $statuses = $statusController->getServersStatusCached();
        } catch (\Throwable $e) {
            Log::warning('StatusPageController@index: ' . $e->getMessage());
            $statuses = [];
        }

        return view('status', [
            'statuses' => $statuses,
            'history'  => $this->history($statuses),
        ]);
    }

    /**
     * Uptime par jour (30 jours) + latence moyenne 24 h par serveur, depuis
     * server_status_history. Fail-safe : table absente / DB down → [].
     *
     * @return array<string, array{days: array<string, float>, latency: ?int}>
     */
    private function history($statuses): array
    {
        $out = [];

        try {
            foreach ($statuses as $status) {
                $ip = $status['ip'] ?? null;
                $port = $status['port'] ?? null;
                if ($ip === null || $port === null) {
                    continue;
                }

                $rows = \Illuminate\Support\Facades\DB::table('server_status_history')
                    ->selectRaw('DATE(created_at) as day, AVG(online) as up, COUNT(*) as total')
                    ->where('server_ip', $ip)
                    ->where('server_port', $port)
                    ->where('created_at', '>=', now()->subDays(30)->startOfDay())
                    ->groupBy('day')
                    ->orderBy('day')
                    ->pluck('up', 'day')
                    ->map(fn ($v) => round(((float) $v) * 100, 1))
                    ->all();

                $latency = \Illuminate\Support\Facades\DB::table('server_status_history')
                    ->where('server_ip', $ip)
                    ->where('server_port', $port)
                    ->where('online', true)
                    ->whereNotNull('latency')
                    ->where('created_at', '>=', now()->subDay())
                    ->avg('latency');

                $out[$ip.':'.$port] = [
                    'days'    => $rows,
                    'latency' => $latency !== null ? (int) round($latency) : null,
                ];
            }
        } catch (\Throwable $e) {
            // migration pas encore lancée : la page reste fonctionnelle sans historique
        }

        return $out;
    }
}
