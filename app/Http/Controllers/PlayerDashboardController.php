<?php

namespace App\Http\Controllers;

use App\Services\PublicDashboard;

/**
 * Tableau de bord PUBLIC des joueurs (/joueurs) et profil (/joueurs/{pseudo}).
 * Aucune authentification, aucune donnée personnelle (ni IP ni e-mail) ;
 * tout vient des contrôleurs /utils/* via App\Services\PublicDashboard.
 */
class PlayerDashboardController extends Controller
{
    public const TABS = ['classement', 'pays', 'saison', 'wonder', 'collecte', 'succes'];

    public function index(PublicDashboard $d)
    {
        $tab = request()->query('tab');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'classement';
        $servers = $d->servers();
        $online = array_sum(array_map(fn ($s) => (int) ($s['players'] ?? 0), array_filter($servers, fn ($s) => ! empty($s['online']))));

        return response()->view('dashboard.index', [
            'tab' => $tab,
            'servers' => $servers,
            'online' => $online,
            'leaderboard' => $d->leaderboard(),
            'factions' => $d->factions(),
            'seasons' => $d->seasons(),
            'wonder' => $d->wonder(),
            'collect' => $d->collect(),
            'achievements' => $d->achievements(),
        ])->header('Cache-Control', 'public, max-age=30');
    }

    public function player(PublicDashboard $d, string $pseudo)
    {
        $profile = $d->player($pseudo);
        if ($profile === null) {
            return response()->view('dashboard.notfound', ['name' => PublicDashboard::validName($pseudo) ? $pseudo : ''], 404);
        }

        return response()->view('dashboard.player', ['p' => $profile]);
    }
}
