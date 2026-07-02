<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saisons / leaderboards saisonniers.
 *
 * Le plugin GeoFactions signale le cycle de vie des saisons via
 * POST /utils/seasons/sync (authentifié par un jeton partagé). Le launcher
 * affiche la saison en cours + un hall of fame des saisons passées via
 * GET /utils/seasons.
 *
 * Le jeton d'ingestion est lu depuis config('geoventure.seasons.ingest_token')
 * (compatible config:cache). Vide → tous les syncs sont rejetés (fail-closed).
 * Toujours fail-safe : jamais de 500.
 */
class SeasonController extends Controller
{
    /**
     * POST /utils/seasons/sync
     * Body : { token, action, season:{external_id,name,starts_at,ends_at}, winner?:{name,faction,score,reward} }
     * 403 si jeton absent/invalide.
     */
    public function sync(Request $request)
    {
        try {
            $expected = (string) config('geoventure.seasons.ingest_token', '');
            $provided = (string) $request->input('token', '');

            // Pas de jeton configuré ou jeton invalide → refus (fail-closed).
            if ($expected === '' || !hash_equals($expected, $provided)) {
                return response()->json(['error' => 'unauthorized'], 403, [], JSON_UNESCAPED_SLASHES);
            }

            $action  = trim((string) $request->input('action', ''));
            $season  = (array) $request->input('season', []);
            $externalId = trim((string) ($season['external_id'] ?? ''));
            $name       = trim((string) ($season['name'] ?? ''));

            if ($externalId === '' || mb_strlen($externalId) > 255) {
                return response()->json(['error' => 'invalid'], 422, [], JSON_UNESCAPED_SLASHES);
            }

            if ($action === 'start') {
                Season::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'name'      => $name !== '' ? mb_substr($name, 0, 255) : $externalId,
                        'starts_at' => $this->parseDate($season['starts_at'] ?? null) ?? now(),
                        'ends_at'   => $this->parseDate($season['ends_at'] ?? null),
                        'status'    => 'active',
                    ]
                );

                return response()->json(['status' => 'ok'], 200, [], JSON_UNESCAPED_SLASHES);
            }

            if ($action === 'end') {
                $winner = (array) $request->input('winner', []);

                $attributes = [
                    'status'  => 'ended',
                    'ends_at' => $this->parseDate($season['ends_at'] ?? null) ?? now(),
                ];

                if ($name !== '') {
                    $attributes['name'] = mb_substr($name, 0, 255);
                }

                if (!empty($winner)) {
                    $attributes['winner_name']    = $this->str($winner['name'] ?? null);
                    $attributes['winner_faction'] = $this->str($winner['faction'] ?? null);
                    $attributes['winner_score']   = isset($winner['score']) ? (int) $winner['score'] : null;
                    $attributes['reward']         = $this->str($winner['reward'] ?? null);
                }

                Season::updateOrCreate(
                    ['external_id' => $externalId],
                    array_merge($attributes, [
                        'name'      => $attributes['name'] ?? ($name !== '' ? $name : $externalId),
                        'starts_at' => $this->parseDate($season['starts_at'] ?? null),
                    ])
                );

                return response()->json(['status' => 'ok'], 200, [], JSON_UNESCAPED_SLASHES);
            }

            return response()->json(['error' => 'invalid'], 422, [], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            Log::warning('SeasonController@sync: ' . $e->getMessage());
            return response()->json(['error' => 'error'], 200, [], JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * GET /utils/seasons
     * { current: {...}|null, past: [...] } — dates en epoch MILLISECONDES.
     */
    public function index()
    {
        try {
            $current = Season::where('status', 'active')
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->first();

            $past = Season::where('status', 'ended')
                ->orderByDesc('ends_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get();

            $payload = [
                'current' => $current ? [
                    'id'        => $current->external_id,
                    'name'      => $current->name,
                    'startsAt'  => $this->epochMs($current->starts_at),
                    'endsAt'    => $this->epochMs($current->ends_at),
                    'status'    => $current->status,
                    'standings' => $this->standings($current->external_id),
                ] : null,
                'past' => $past->map(fn ($s) => [
                    'id'      => $s->external_id,
                    'name'    => $s->name,
                    'endedAt' => $this->epochMs($s->ends_at),
                    'winner'  => [
                        'name'    => $s->winner_name,
                        'faction' => $s->winner_faction,
                        'score'   => $s->winner_score,
                    ],
                    'reward'  => $s->reward,
                ])->values(),
            ];
        } catch (\Throwable $e) {
            Log::warning('SeasonController@index: ' . $e->getMessage());
            $payload = ['current' => null, 'past' => []];
        }

        return response()->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Classement de la saison en cours (top factions par points), lu dans la
     * DB GeoFactions externe (config geoventure.season_standings, connexion
     * 'game'). L'id de saison ('AAAA-MM' = external_id) est passé en binding.
     * Fail-safe : DB non configurée (database vide) ou erreur → []. Cache 60s.
     */
    private function standings(string $seasonId): array
    {
        $cfg = config('geoventure.season_standings', []);
        $query = $cfg['query'] ?? null;
        if (empty($query) || $seasonId === '') {
            return [];
        }

        $connection = $cfg['connection'] ?? 'game';
        $limit = (int) ($cfg['limit'] ?? 10);
        $ttl = (int) config('geoventure.cache_ttl', 60);

        // Connexion externe non configurée (database vide) → on n'essaie même
        // pas de se connecter : on renvoie [] directement.
        if (empty(config("database.connections.{$connection}.database"))) {
            return [];
        }

        try {
            return Cache::remember("geo_season_standings_{$seasonId}", $ttl, function () use ($connection, $query, $limit, $seasonId) {
                $rows = DB::connection($connection)->select($query, [$seasonId]);

                $out = [];
                foreach (array_slice($rows, 0, $limit) as $row) {
                    $row = (array) $row;
                    $out[] = [
                        'name'   => $row['name'] ?? '?',
                        'points' => isset($row['points']) ? (int) $row['points'] : 0,
                    ];
                }
                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('SeasonController@standings: ' . $e->getMessage());
            return [];
        }
    }

    /** Convertit une date en epoch millisecondes, ou null. */
    private function epochMs($date): ?int
    {
        return $date ? $date->getTimestamp() * 1000 : null;
    }

    /** Parse une date entrante (string/timestamp) → Carbon ou null. */
    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                // Heuristique : millisecondes si très grand, sinon secondes.
                $value = (int) $value;
                return $value > 9999999999 ? Carbon::createFromTimestampMs($value) : Carbon::createFromTimestamp($value);
            }
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Tronque/nettoie une chaîne optionnelle (colonnes VARCHAR(255)). */
    private function str($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
