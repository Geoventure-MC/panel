<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AchievementUnlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Déverrouillages de succès côté serveur.
 *
 * Le plugin GeoFactions signale chaque succès débloqué par un joueur via
 * POST /utils/achievements/unlock (authentifié par un jeton partagé). Le
 * launcher relit l'état du joueur connecté via GET /utils/achievements/progress.
 *
 * Le jeton d'ingestion est lu depuis config('geoventure.achievements.ingest_token')
 * (compatible config:cache). Toujours fail-safe : jamais de 500.
 */
class AchievementUnlockController extends Controller
{
    /**
     * POST /utils/achievements/unlock
     * Body : { player, code, token }
     * 403 si jeton absent/invalide, sinon upsert (player, code).
     */
    public function store(Request $request)
    {
        try {
            $expected = (string) config('geoventure.achievements.ingest_token', '');
            $provided = (string) $request->input('token', '');

            // Pas de jeton configuré ou jeton invalide → refus.
            if ($expected === '' || !hash_equals($expected, $provided)) {
                return response()->json(['error' => 'unauthorized'], 403, [], JSON_UNESCAPED_SLASHES);
            }

            $player = trim((string) $request->input('player', ''));
            $code   = trim((string) $request->input('code', ''));

            if ($player === '' || $code === '') {
                return response()->json(['error' => 'invalid'], 422, [], JSON_UNESCAPED_SLASHES);
            }

            // Colonnes VARCHAR(255) : refuser au lieu de jeter une QueryException.
            if (mb_strlen($player) > 255 || mb_strlen($code) > 255) {
                return response()->json(['error' => 'invalid'], 422, [], JSON_UNESCAPED_SLASHES);
            }

            AchievementUnlock::firstOrCreate(
                ['player' => $player, 'code' => $code],
                ['unlocked_at' => now()]
            );

            return response()->json(['status' => 'ok'], 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            Log::warning('AchievementUnlockController@store: ' . $e->getMessage());
            return response()->json(['error' => 'error'], 200, [], JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * GET /utils/achievements/progress?player=<name>
     * Renvoie la liste des codes de succès débloqués par ce joueur : ["code1","code2"].
     */
    public function progress(Request $request)
    {
        try {
            $player = trim((string) $request->query('player', ''));

            if ($player === '') {
                return response()->json([], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            $codes = AchievementUnlock::where('player', $player)
                ->orderBy('unlocked_at')
                ->pluck('code')
                ->values();

            return response()->json($codes, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            Log::warning('AchievementUnlockController@progress: ' . $e->getMessage());
            return response()->json([], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
}
