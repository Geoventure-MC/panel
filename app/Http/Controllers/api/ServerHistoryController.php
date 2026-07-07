<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ServerPlayerSample;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /utils/servers-history?server=<id> — alimente le mini-graphe d'affluence
 * du launcher.
 *
 * Réponse : {
 *   points:    [{ t: <ms, début d'heure>, players: <moyenne> }]  (48 dernières heures)
 *   peakHours: [int, int, int]  (3 heures de pointe 0-23, sur 7 jours)
 * }
 *
 * Les échantillons sont posés par ServerStatusController à chaque ping SLP
 * frais (au plus 1/5 min). Fail-safe : toute erreur (table absente avant
 * migration, DB down…) → { points: [], peakHours: [] } en 200, jamais de 500.
 * ETag + Cache-Control max-age=300 pour rester léger côté launcher.
 */
class ServerHistoryController extends Controller
{
    public function getHistory(Request $request)
    {
        $serverKey = (string) $request->query('server', '');
        $payload = $this->build($serverKey);

        $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $etag = '"' . md5($body) . '"';
        $headers = [
            'Content-Type'  => 'application/json',
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=300',
        ];

        if (trim($request->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
    }

    /**
     * @return array{points: array, peakHours: array}
     */
    private function build(string $serverKey): array
    {
        $empty = ['points' => [], 'peakHours' => []];
        if ($serverKey === '') {
            return $empty;
        }

        try {
            return Cache::remember('server_history_' . md5($serverKey), 300, function () use ($serverKey, $empty) {
                $samples = ServerPlayerSample::where('server_key', $serverKey)
                    ->where('sampled_at', '>=', now()->subDays(7))
                    ->orderBy('sampled_at')
                    ->get(['players', 'sampled_at']);

                if ($samples->isEmpty()) {
                    return $empty;
                }

                // Agrégation en PHP (portable SQLite/MySQL) : moyenne par heure
                // pleine sur 48h pour les points, et par heure de la journée
                // (0-23) sur 7 jours pour les heures de pointe.
                $hourBuckets = [];      // "YYYY-mm-dd HH" => [sum, count] (48h)
                $dayHourBuckets = [];   // 0-23            => [sum, count] (7j)
                $cutoff48h = now()->subHours(48);

                foreach ($samples as $sample) {
                    $at = $sample->sampled_at;
                    $players = (int) $sample->players;

                    $h = (int) $at->format('G');
                    $dayHourBuckets[$h][0] = ($dayHourBuckets[$h][0] ?? 0) + $players;
                    $dayHourBuckets[$h][1] = ($dayHourBuckets[$h][1] ?? 0) + 1;

                    if ($at->greaterThanOrEqualTo($cutoff48h)) {
                        $key = $at->copy()->startOfHour()->getTimestamp();
                        $hourBuckets[$key][0] = ($hourBuckets[$key][0] ?? 0) + $players;
                        $hourBuckets[$key][1] = ($hourBuckets[$key][1] ?? 0) + 1;
                    }
                }

                ksort($hourBuckets);
                $points = [];
                foreach ($hourBuckets as $hourTs => [$sum, $count]) {
                    $points[] = [
                        't'       => $hourTs * 1000,
                        'players' => (int) round($sum / max(1, $count)),
                    ];
                }

                // Top 3 des heures (0-23) par moyenne de joueurs sur 7 jours.
                $averages = [];
                foreach ($dayHourBuckets as $hour => [$sum, $count]) {
                    $averages[$hour] = $sum / max(1, $count);
                }
                arsort($averages);
                $peakHours = array_slice(array_keys($averages), 0, 3);
                sort($peakHours);

                return ['points' => $points, 'peakHours' => array_values($peakHours)];
            });
        } catch (\Throwable $e) {
            return $empty;
        }
    }
}
