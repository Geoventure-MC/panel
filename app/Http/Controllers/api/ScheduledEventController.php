<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Planificateur d'événements serveur.
 *
 * L'admin programme des événements (airdrop, invasion, bonus XP…) depuis le
 * panel ; le plugin GeoFactions les récupère via POST /utils/scheduled-events/claim
 * (jeton partagé, réclamation atomique → chaque événement n'est déclenché
 * qu'une seule fois) ; le launcher affiche les prochains événements via
 * GET /utils/scheduled-events.
 *
 * Jeton lu depuis config('geoventure.events.ingest_token') (compatible
 * config:cache). Vide → tous les claims rejetés (fail-closed). Jamais de 500.
 */
class ScheduledEventController extends Controller
{
    /**
     * GET /utils/scheduled-events
     * Prochains événements en attente (max 10, le plus proche d'abord).
     * [{ id, type, title, description, scheduledAt (epoch ms), recurring }]
     */
    public function index()
    {
        try {
            $events = ScheduledEvent::where('status', 'pending')
                ->where('scheduled_at', '>=', now()->subMinutes(5))
                ->orderBy('scheduled_at')
                ->limit(10)
                ->get()
                ->map(fn ($e) => [
                    'id'          => $e->id,
                    'type'        => $e->type,
                    'title'       => $e->title,
                    'description' => $e->description,
                    'scheduledAt' => $e->scheduled_at ? $e->scheduled_at->getTimestamp() * 1000 : null,
                    'recurring'   => $e->recurring,
                ])->values();
        } catch (\Throwable $e) {
            Log::warning('ScheduledEventController@index: ' . $e->getMessage());
            $events = [];
        }

        return response()->json($events, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * POST /utils/scheduled-events/claim
     * Body : { token }. Réclame atomiquement les événements arrivés à échéance
     * (marqués 'fired', ou re-planifiés si récurrents) et les renvoie — le
     * plugin les déclenche donc exactement une fois chacun.
     */
    public function claim(Request $request)
    {
        try {
            $expected = (string) config('geoventure.events.ingest_token', '');
            $provided = (string) $request->input('token', '');

            if ($expected === '' || !hash_equals($expected, $provided)) {
                return response()->json(['error' => 'unauthorized'], 403, [], JSON_UNESCAPED_SLASHES);
            }

            $claimed = DB::transaction(function () {
                $due = ScheduledEvent::where('status', 'pending')
                    ->where('scheduled_at', '<=', now())
                    ->orderBy('scheduled_at')
                    ->limit(20)
                    ->lockForUpdate()
                    ->get();

                foreach ($due as $event) {
                    if ($event->recurring === 'daily' || $event->recurring === 'weekly') {
                        // Récurrent : re-planifie la prochaine occurrence future.
                        $next = $event->scheduled_at->copy();
                        $step = $event->recurring === 'daily' ? 1 : 7;
                        while ($next->isPast()) {
                            $next->addDays($step);
                        }
                        $event->update(['scheduled_at' => $next, 'fired_at' => now()]);
                    } else {
                        $event->update(['status' => 'fired', 'fired_at' => now()]);
                    }
                }

                return $due;
            });

            return response()->json([
                'events' => $claimed->map(fn ($e) => [
                    'id'          => $e->id,
                    'type'        => $e->type,
                    'title'       => $e->title,
                    'description' => $e->description,
                ])->values(),
            ], 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            Log::warning('ScheduledEventController@claim: ' . $e->getMessage());
            return response()->json(['events' => []], 200, [], JSON_UNESCAPED_SLASHES);
        }
    }
}
