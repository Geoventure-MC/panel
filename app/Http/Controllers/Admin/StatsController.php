<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OptionsServer;
use App\Models\ServerPlayerSample;
use App\Models\TelemetryEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Statistiques du launcher et des serveurs.
 *
 * La page ne montrait que des totaux bruts sur 30 jours : on voyait « 1 200
 * lancements » sans savoir si c'était bien ou mal, ni quand jouer. Elle
 * répond maintenant aux questions qu'on se pose réellement — est-ce que ça
 * monte ou ça descend, est-ce que les joueurs reviennent, à quelles heures
 * remplir le serveur, et est-ce que quelqu'un plante.
 *
 * Chaque bloc est indépendant et défensif : une table absente (télémétrie
 * jamais reçue, historique de statut pas encore migré) rend un bloc vide,
 * pas une erreur.
 */
class StatsController extends Controller
{
    /** Fenêtres proposées, en jours. */
    private const RANGES = [7, 30, 90];

    public function index(Request $request)
    {
        $days = (int) $request->query('days', 30);
        if (!in_array($days, self::RANGES, true)) {
            $days = 30;
        }

        $since = Carbon::now()->subDays($days)->startOfDay();
        // Période précédente de même longueur, pour dire « +12 % » plutôt
        // qu'un nombre sans point de comparaison.
        $previousSince = (clone $since)->subDays($days);

        return view('admin.stats', array_merge(
            [
                'days'   => $days,
                'ranges' => self::RANGES,
            ],
            $this->launchSeries($since, $days),
            $this->breakdowns($since),
            $this->totals($since, $previousSince),
            $this->retention($since, $previousSince),
            $this->hourlyHeatmap($since),
            $this->playerCurve($since, $days),
            $this->reliability($since),
        ));
    }

    /** Export CSV des lancements par jour — pour un tableur ou un rapport. */
    public function export(Request $request): StreamedResponse
    {
        $days = (int) $request->query('days', 30);
        if (!in_array($days, self::RANGES, true)) {
            $days = 30;
        }

        $since = Carbon::now()->subDays($days)->startOfDay();
        $series = $this->launchSeries($since, $days);

        $filename = 'geoventure-stats-'.$days.'j-'.Carbon::now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($series) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'lancements']);
            foreach ($series['dailyLabels'] as $i => $label) {
                fputcsv($out, [$label, $series['dailyData'][$i] ?? 0]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Blocs ────────────────────────────────────────────────────────────

    /** Lancements par jour, trous comblés à 0 pour une courbe continue. */
    private function launchSeries(Carbon $since, int $days): array
    {
        $raw = TelemetryEvent::where('event', 'launch')
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        for ($d = $since->copy(); $d <= Carbon::now(); $d->addDay()) {
            $labels[] = $d->format('d/m');
            $data[] = (int) ($raw[$d->format('Y-m-d')] ?? 0);
        }

        return ['dailyLabels' => $labels, 'dailyData' => $data];
    }

    /** Répartitions serveur / version / OS. */
    private function breakdowns(Carbon $since): array
    {
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
            $serverData[] = (int) $total;
        }

        $byVersion = TelemetryEvent::where('created_at', '>=', $since)
            ->whereNotNull('launcher_version')
            ->selectRaw('launcher_version, COUNT(DISTINCT ip_hash) as total')
            ->groupBy('launcher_version')
            ->orderByDesc('total')
            ->pluck('total', 'launcher_version');

        $byOs = TelemetryEvent::where('created_at', '>=', $since)
            ->whereNotNull('os')
            ->selectRaw('os, COUNT(*) as total')
            ->groupBy('os')
            ->orderByDesc('total')
            ->pluck('total', 'os');

        return [
            'serverLabels'  => $serverLabels,
            'serverData'    => $serverData,
            'versionLabels' => $byVersion->keys()->toArray(),
            'versionData'   => array_map('intval', $byVersion->values()->toArray()),
            'osLabels'      => $byOs->keys()->toArray(),
            'osData'        => array_map('intval', $byOs->values()->toArray()),
            // Part de la version la plus déployée : dit en un chiffre si les
            // joueurs ont bien pris la dernière mise à jour.
            'topVersion'    => $byVersion->keys()->first(),
            'topVersionShare' => $byVersion->sum() > 0
                ? (int) round(($byVersion->first() ?? 0) * 100 / $byVersion->sum())
                : 0,
        ];
    }

    /** Totaux de la période + évolution par rapport à la précédente. */
    private function totals(Carbon $since, Carbon $previousSince): array
    {
        $launches = TelemetryEvent::where('event', 'launch')
            ->where('created_at', '>=', $since)->count();
        $launchesBefore = TelemetryEvent::where('event', 'launch')
            ->whereBetween('created_at', [$previousSince, $since])->count();

        $players = (int) TelemetryEvent::where('created_at', '>=', $since)
            ->whereNotNull('ip_hash')->distinct('ip_hash')->count('ip_hash');
        $playersBefore = (int) TelemetryEvent::whereBetween('created_at', [$previousSince, $since])
            ->whereNotNull('ip_hash')->distinct('ip_hash')->count('ip_hash');

        return [
            'totalLaunches'   => $launches,
            'launchesTrend'   => $this->trend($launches, $launchesBefore),
            'uniquePlayers'   => $players,
            'playersTrend'    => $this->trend($players, $playersBefore),
            // Combien de fois un même joueur lance le launcher sur la période.
            'launchesPerUser' => $players > 0 ? round($launches / $players, 1) : 0.0,
        ];
    }

    /**
     * Nouveaux vs revenants : un joueur est « nouveau » s'il n'apparaît nulle
     * part avant le début de la période. C'est la mesure qui distingue une
     * communauté qui grandit d'une communauté qui se renouvelle.
     */
    private function retention(Carbon $since, Carbon $previousSince): array
    {
        try {
            $current = TelemetryEvent::where('created_at', '>=', $since)
                ->whereNotNull('ip_hash')->distinct()->pluck('ip_hash');

            if ($current->isEmpty()) {
                return ['newPlayers' => 0, 'returningPlayers' => 0, 'retentionRate' => 0];
            }

            $known = TelemetryEvent::where('created_at', '<', $since)
                ->whereNotNull('ip_hash')
                ->whereIn('ip_hash', $current)
                ->distinct()->pluck('ip_hash');

            $returning = $known->count();
            $new = $current->count() - $returning;

            // Taux de retour : parmi ceux vus sur la période précédente,
            // combien sont revenus sur celle-ci.
            $previous = TelemetryEvent::whereBetween('created_at', [$previousSince, $since])
                ->whereNotNull('ip_hash')->distinct()->pluck('ip_hash');

            $comeback = $previous->isEmpty() ? 0
                : (int) round($previous->intersect($current)->count() * 100 / $previous->count());

            return [
                'newPlayers'       => max(0, $new),
                'returningPlayers' => $returning,
                'retentionRate'    => $comeback,
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['newPlayers' => 0, 'returningPlayers' => 0, 'retentionRate' => 0];
        }
    }

    /**
     * Activité par jour de semaine × heure. C'est la donnée qui sert vraiment
     * à décider quand lancer un événement ou une maintenance.
     */
    private function hourlyHeatmap(Carbon $since): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        $max = 0;

        try {
            $rows = TelemetryEvent::where('event', 'launch')
                ->where('created_at', '>=', $since)
                ->get(['created_at']);

            foreach ($rows as $row) {
                // Calculé en PHP et non en SQL : DAYOFWEEK/strftime divergent
                // entre MySQL et SQLite, et la page doit marcher sur les deux.
                $d = $row->created_at->dayOfWeekIso - 1; // 0 = lundi
                $h = (int) $row->created_at->format('G');
                $grid[$d][$h]++;
                $max = max($max, $grid[$d][$h]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return ['heatmap' => $grid, 'heatmapMax' => $max];
    }

    /** Joueurs connectés : moyenne et pic par jour, depuis les relevés SLP. */
    private function playerCurve(Carbon $since, int $days): array
    {
        $labels = [];
        $avg = [];
        $peak = [];

        try {
            if (!Schema::hasTable('server_player_samples')) {
                return ['playerLabels' => [], 'playerAvg' => [], 'playerPeak' => [], 'peakPlayers' => 0];
            }

            $rows = ServerPlayerSample::where('sampled_at', '>=', $since)
                ->selectRaw('DATE(sampled_at) as day, AVG(players) as moyenne, MAX(players) as pic')
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            for ($d = $since->copy(); $d <= Carbon::now(); $d->addDay()) {
                $key = $d->format('Y-m-d');
                $labels[] = $d->format('d/m');
                $avg[] = isset($rows[$key]) ? round((float) $rows[$key]->moyenne, 1) : 0;
                $peak[] = isset($rows[$key]) ? (int) $rows[$key]->pic : 0;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'playerLabels' => $labels,
            'playerAvg'    => $avg,
            'playerPeak'   => $peak,
            'peakPlayers'  => $peak === [] ? 0 : max($peak),
        ];
    }

    /**
     * Fiabilité : disponibilité des serveurs et taux d'échec du launcher.
     * Deux chiffres qui disent, sans ouvrir un log, si la semaine s'est
     * bien passée.
     */
    private function reliability(Carbon $since): array
    {
        $uptime = null;
        $latency = null;

        try {
            if (Schema::hasTable('server_status_history')) {
                $row = DB::table('server_status_history')
                    ->where('created_at', '>=', $since)
                    ->selectRaw('COUNT(*) as total, SUM(online) as up, AVG(latency) as latence')
                    ->first();

                if ($row !== null && (int) $row->total > 0) {
                    $uptime = round((float) $row->up * 100 / (int) $row->total, 1);
                    $latency = $row->latence === null ? null : (int) round((float) $row->latence);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $byEvent = TelemetryEvent::where('created_at', '>=', $since)
            ->selectRaw('event, COUNT(*) as total')
            ->groupBy('event')
            ->orderByDesc('total')
            ->pluck('total', 'event');

        $launches = (int) ($byEvent['launch'] ?? 0);
        $errors = (int) ($byEvent['crash'] ?? 0) + (int) ($byEvent['launch_error'] ?? 0);

        return [
            'uptime'     => $uptime,
            'latency'    => $latency,
            'eventLabels' => $byEvent->keys()->toArray(),
            'eventData'  => array_map('intval', $byEvent->values()->toArray()),
            'errorRate'  => $launches > 0 ? round($errors * 100 / $launches, 1) : 0.0,
            'errorCount' => $errors,
        ];
    }

    /** Évolution en pourcentage, ou null quand la comparaison n'a pas de sens. */
    private function trend(int $current, int $previous): ?int
    {
        if ($previous <= 0) {
            return null;
        }

        return (int) round(($current - $previous) * 100 / $previous);
    }
}
