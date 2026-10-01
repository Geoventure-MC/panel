<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use Illuminate\Support\Facades\Log;

/**
 * GET /utils/achievements — catalogue de succès géré côté panel.
 *
 * Le launcher suit l'état de déverrouillage localement : aucun suivi par
 * utilisateur côté panel, on n'expose que le catalogue des succès actifs.
 * Vocabulaire condition_type (contrat partagé avec le launcher) :
 * first_launch | launch_count | playtime_hours | instances_tried | manual.
 * `rarity` (ajouté 2026-09-27, rétrocompatible : un client qui l'ignore ne
 * perd rien) : common | uncommon | rare | epic | legendary, celle du plugin.
 * `secret`, `max_level` (ajoutés 2026-10-01, rétrocompatibles) : un succès secret est
 * servi masqué (« ??? ») ; `max_level` 1..5 = niveaux I à V, `points` = points par niveau.
 * Toujours fail-safe : jamais de 500, renvoie [] en cas d'erreur.
 */
class AchievementController extends Controller
{
    public function getAchievements()
    {
        try {
            $achievements = Achievement::where('active', true)
                ->orderBy('category')
                ->orderBy('id')
                ->get()
                ->map(fn ($a) => [
                    'code'            => $a->code,
                    // Un succès secret ne dévoile ni nom, ni description, ni icône.
                    'name'            => ($a->secret ?? false) ? '???' : $a->name,
                    'description'     => ($a->secret ?? false) ? '' : $a->description,
                    'icon'            => ($a->secret ?? false) ? null : $a->icon,
                    'secret'          => (bool) ($a->secret ?? false),
                    'max_level'       => (int) ($a->max_level ?? 1),
                    'points'          => $a->points,
                    'rarity'          => $a->rarity ?? 'common',
                    'category'        => $a->category,
                    'condition_type'  => $a->condition_type,
                    'condition_value' => $a->condition_value,
                ]);
        } catch (\Throwable $e) {
            Log::warning('AchievementController: ' . $e->getMessage());
            $achievements = [];
        }

        return response()->json($achievements, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
