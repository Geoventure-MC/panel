<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OptionsServer;
use App\Models\TelemetryEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index()
    {
        $since = Carbon::now()->subDays(30)->startOfDay();

        // Lancements par jour (30 derniers jours), avec trous comblés à 0.
        $rawDaily = TelemetryEvent::where('event', 'launch')
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyLabels = [];
        $dailyData = [];
        for ($d = $since->copy(); $d <= Carbon::now(); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $dailyLabels[] = $d->format('d/m');
            $dailyData[]   = (int) ($rawDaily[$key] ?? 0);
        }

        // Répartition par serveur (mappe server_id → nom lisible).
        $serverNames = OptionsServer::pluck('server_name', 'server_id');
        $byServer = TelemetryEvent::where('event', 'launch')
            ->where('created_at', '>=', $since)
            ->selectRaw('server_id, COUNT(*) as total')
            ->groupBy('server_id')
            ->pluck('total', 'server_id');
        $serverLabels = [];
        $serverData = [];
        foreach ($byServer as $id => $total) {
            $serverLabels[] = $serverNames[$id] ?? ($id ?: 'inconnu');
            $serverData[]   = (int) $total;
        }

        // Versions du launcher.
        $byVersion = TelemetryEvent::where('created_at', '>=', $since)
            ->whereNotNull('launcher_version')
            ->selectRaw('launcher_version, COUNT(DISTINCT ip_hash) as total')
            ->groupBy('launcher_version')
            ->orderByDesc('total')
            ->pluck('total', 'launcher_version');

        // Répartition OS.
        $byOs = TelemetryEvent::where('created_at', '>=', $since)
            ->whereNotNull('os')
            ->selectRaw('os, COUNT(*) as total')
            ->groupBy('os')
            ->orderByDesc('total')
            ->pluck('total', 'os');

        $totalLaunches = array_sum($dailyData);
        $uniquePlayers = (int) TelemetryEvent::where('created_at', '>=', $since)
            ->whereNotNull('ip_hash')
            ->distinct('ip_hash')
            ->count('ip_hash');

        return view('admin.stats', [
            'dailyLabels'   => $dailyLabels,
            'dailyData'     => $dailyData,
            'serverLabels'  => $serverLabels,
            'serverData'    => $serverData,
            'versionLabels' => $byVersion->keys()->toArray(),
            'versionData'   => array_map('intval', $byVersion->values()->toArray()),
            'osLabels'      => $byOs->keys()->toArray(),
            'osData'        => array_map('intval', $byOs->values()->toArray()),
            'totalLaunches' => $totalLaunches,
            'uniquePlayers' => $uniquePlayers,
        ]);
    }
}
