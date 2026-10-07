<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GET /utils/collecte — jauge et classement de l'événement de collecte
 * (autel à paliers du mod GeoMods, boss marin à heure fixe).
 *
 * Lecture seule : le plugin GeoFactions (event/CollectEventManager) tient
 * l'état dans un JSON atomique et en écrit un MIROIR dans la base du jeu
 * (tables `{prefix}collect_meta` et `{prefix}collect_ranking`). Ce contrôleur
 * lit ce miroir via la connexion externe `game` (config/geoventure.php).
 *
 * Fail-safe comme les autres endpoints /utils : base non configurée, tables
 * absentes ou toute erreur → réponse 200 `{"active":false,...}`, jamais 500.
 * ETag + Cache-Control: max-age=30 + 304 sur If-None-Match (polling-friendly).
 */
class CollectController extends Controller
{
    private const EMPTY = [
        'active' => false,
        'state' => 'idle',
        'title' => '',
        'tier' => 0,
        'total' => 0,
        'goal' => 0,
        'bossAt' => 0,
        'bossLabel' => '',
        'top' => [],
        'countries' => [],
    ];

    public function getCollecte(Request $request)
    {
        $data = $this->fetch();

        $body = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $etag = '"' . md5($body) . '"';
        $headers = [
            'Content-Type'  => 'application/json',
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=30',
        ];

        if (trim($request->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
    }

    private function fetch(): array
    {
        $cfg = config('geoventure.collect', []);
        $connection = $cfg['connection'] ?? 'game';
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($cfg['table_prefix'] ?? 'gf_'));
        $limit = max(1, min(100, (int) ($cfg['limit'] ?? 50)));

        // Connexion externe non configurée → on n'essaie même pas de se connecter.
        if (empty(config("database.connections.{$connection}.database"))) {
            return self::EMPTY;
        }

        try {
            return Cache::remember('geo_collecte', 30, function () use ($connection, $prefix, $limit) {
                $meta = DB::connection($connection)->selectOne("SELECT * FROM {$prefix}collect_meta WHERE id = 1");
                if (!$meta) {
                    return self::EMPTY;
                }
                $meta = (array) $meta;
                $rows = DB::connection($connection)->select(
                    "SELECT player_name, country, points FROM {$prefix}collect_ranking "
                    . 'WHERE event_id = ? ORDER BY points DESC LIMIT ' . $limit,
                    [(int) $meta['event_id']]
                );

                $top = [];
                $byCountry = [];
                $rank = 1;
                foreach ($rows as $row) {
                    $row = (array) $row;
                    $top[] = [
                        'rank' => $rank++,
                        'name' => (string) $row['player_name'],
                        'country' => (string) ($row['country'] ?? ''),
                        'points' => (int) $row['points'],
                    ];
                    $c = (string) ($row['country'] ?? '');
                    if ($c !== '') {
                        $byCountry[$c] = ($byCountry[$c] ?? 0) + (int) $row['points'];
                    }
                }
                arsort($byCountry);
                $countries = [];
                $i = 1;
                foreach (array_slice($byCountry, 0, 10, true) as $name => $points) {
                    $countries[] = ['rank' => $i++, 'name' => $name, 'points' => $points];
                }

                return [
                    'active' => in_array($meta['state'], ['collecting', 'boss'], true),
                    'state' => (string) $meta['state'],
                    'title' => (string) $meta['title'],
                    'tier' => (int) $meta['tier'],
                    'total' => (int) $meta['total'],
                    'goal' => (int) $meta['goal'],
                    'bossAt' => (int) $meta['boss_at'],
                    'bossLabel' => (string) $meta['boss_label'],
                    'top' => $top,
                    'countries' => $countries,
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('CollectController: ' . $e->getMessage());
            return self::EMPTY;
        }
    }
}
