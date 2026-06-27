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
                    'name'            => $a->name,
                    'description'     => $a->description,
                    'icon'            => $a->icon,
                    'points'          => $a->points,
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
