<?php

namespace App\Http\Controllers;

use App\Models\Season;

/**
 * Page admin en lecture seule des saisons (alimentées par le plugin via
 * POST /utils/seasons/sync). Affiche nom, statut, dates et vainqueur.
 */
class AdminSeasonController extends Controller
{
    public function index()
    {
        $seasons = Season::orderByDesc('status') // 'ended' avant 'active' alphabétiquement → on réordonne ci-dessous
            ->get()
            ->sortByDesc(fn ($s) => [$s->status === 'active' ? 1 : 0, optional($s->starts_at)->timestamp ?? 0])
            ->values();

        return view('admin.seasons', compact('seasons'));
    }
}
