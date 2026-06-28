<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\ServerStatusController;
use App\Models\AchievementUnlock;
use Illuminate\Http\Request;

/**
 * Tableau de bord live (admin) : statut serveurs en temps réel + derniers
 * succès débloqués, rafraîchis côté client par polling JS (~20s) via
 * admin.dashboard.feed.
 */
class LiveDashboardController extends Controller
{
    private const RECENT_LIMIT = 12;

    public function index()
    {
        $serverStatuses = $this->getServerStatuses();
        $totalPlayers   = $this->sumPlayers($serverStatuses);
        $recentUnlocks  = $this->getRecentUnlocks();

        return view('admin.dashboard-live', compact(
            'serverStatuses', 'totalPlayers', 'recentUnlocks'
        ));
    }

    /**
     * GET admin.dashboard.feed — JSON consommé par le polling JS.
     */
    public function feed(Request $request)
    {
        $serverStatuses = $this->getServerStatuses();

        return response()->json([
            'servers'      => $serverStatuses,
            'totalPlayers' => $this->sumPlayers($serverStatuses),
            'unlocks'      => $this->getRecentUnlocks()->map(fn ($u) => [
                'player'    => $u->player,
                'code'      => $u->code,
                'unlocked'  => optional($u->unlocked_at ?? $u->created_at)->toIso8601String(),
                'ago'       => optional($u->unlocked_at ?? $u->created_at)->diffForHumans(),
            ])->values(),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function getServerStatuses(): array
    {
        try {
            $controller = new ServerStatusController();
            $response = $controller->getServersStatus();
            return json_decode($response->getContent(), true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function sumPlayers(array $serverStatuses): int
    {
        $total = 0;
        foreach ($serverStatuses as $s) {
            if (!empty($s['online']) && isset($s['players'])) {
                $total += (int) $s['players'];
            }
        }
        return $total;
    }

    private function getRecentUnlocks()
    {
        try {
            return AchievementUnlock::orderByDesc('unlocked_at')
                ->orderByDesc('id')
                ->limit(self::RECENT_LIMIT)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
