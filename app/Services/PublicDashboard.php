<?php

namespace App\Services;

use App\Http\Controllers\api\AchievementController;
use App\Http\Controllers\api\CollectController;
use App\Http\Controllers\api\FactionController;
use App\Http\Controllers\api\LeaderboardController;
use App\Http\Controllers\api\SeasonController;
use App\Http\Controllers\api\ServerStatusController;
use App\Http\Controllers\api\WonderController;
use App\Models\Achievement;
use App\Models\AchievementUnlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Données du tableau de bord public (/joueurs).
 *
 * Réutilise les contrôleurs /utils/* existants EN PROCESSUS (pas de HTTP
 * interne) : on appelle la méthode du contrôleur et on décode le corps de la
 * réponse. Chaque lecture est fail-safe : toute erreur ou réponse invalide
 * donne la valeur vide (jamais d'exception vers la vue). Les contrôleurs
 * portent déjà leur cache (30-60 s) et ne lisent que des champs publics.
 */
class PublicDashboard
{
    /** Date lisible depuis un epoch (secondes ou millisecondes), ou '—'. */
    public static function fmtDate($v): string
    {
        if (! is_numeric($v) || (float) $v <= 0) {
            return '—';
        }
        $v = (float) $v;
        $c = $v > 9999999999 ? \Carbon\Carbon::createFromTimestampMs((int) $v) : \Carbon\Carbon::createFromTimestamp((int) $v);

        return $c->locale(app()->getLocale())->translatedFormat('j F Y, H:i');
    }

    /** #rrggbb strict (jamais une chaîne libre dans un attribut style). */
    public static function hex($v): ?string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null;
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]{1,32}$/', $name);
    }

    /** Appelle $class@$method et renvoie le JSON décodé, ou $fallback. */
    private function call(string $class, string $method, $fallback)
    {
        try {
            $response = app($class)->{$method}(Request::create('/', 'GET'));
            if ($response->getStatusCode() !== 200) {
                return $fallback;
            }
            $data = json_decode($response->getContent(), true);

            return is_array($data) ? $data : $fallback;
        } catch (\Throwable $e) {
            Log::warning('PublicDashboard@' . $method . ': ' . $e->getMessage());

            return $fallback;
        }
    }

    public function leaderboard(): array
    {
        return array_values(array_filter($this->call(LeaderboardController::class, 'getLeaderboards', []), 'is_array'));
    }

    public function factions(): array
    {
        $rows = array_values(array_filter($this->call(FactionController::class, 'getFactions', []), 'is_array'));
        foreach ($rows as &$f) {
            // Entier RGB du jeu -> #rrggbb (jamais une chaîne libre dans un style).
            $f['hex'] = isset($f['color']) && is_numeric($f['color']) ? sprintf('#%06x', ((int) $f['color']) & 0xFFFFFF) : null;
        }

        return $rows;
    }

    public function seasons(): array
    {
        $d = $this->call(SeasonController::class, 'index', []);

        return ['current' => is_array($d['current'] ?? null) ? $d['current'] : null, 'past' => is_array($d['past'] ?? null) ? $d['past'] : []];
    }

    public function wonder(): array
    {
        $d = $this->call(WonderController::class, 'index', []);

        return ['current' => is_array($d['current'] ?? null) ? $d['current'] : null, 'past' => is_array($d['past'] ?? null) ? $d['past'] : []];
    }

    public function collect(): array
    {
        return $this->call(CollectController::class, 'getCollecte', []) ?: [];
    }

    public function achievements(): array
    {
        return array_values(array_filter($this->call(AchievementController::class, 'getAchievements', []), 'is_array'));
    }

    /** Statuts depuis le cache de ping uniquement (aucun ping bloquant au rendu). */
    public function servers(): array
    {
        try {
            $r = app(ServerStatusController::class)->getServersStatusCached();
            $d = json_decode($r->getContent(), true);
            $rows = is_array($d) ? array_values(array_filter($d, 'is_array')) : [];
            // Disponibilité 24 h (supervision) : absente si pas encore de sondes.
            $uptime = [];
            foreach (\App\Http\Controllers\api\UptimeController::rows() as $u) {
                $uptime[$u['id'] ?? ''] = $u['uptime24h'] ?? null;
            }

            // Pas d'IP/port côté page publique.
            return array_map(function ($s) use ($uptime) {
                $s = array_diff_key($s, ['ip' => 1, 'port' => 1]);
                $up = $uptime[$s['id'] ?? ''] ?? null;
                if ($up !== null) {
                    $s['uptime24h'] = $up;
                }

                return $s;
            }, $rows);
        } catch (\Throwable $e) {
            Log::warning('PublicDashboard@servers: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Profil public : null si le joueur est inconnu de toutes les sources.
     * Sources : classement (Azuriom), rosters de factions (jeu), succès
     * débloqués (base du panel).
     */
    public function player(string $name): ?array
    {
        if (! self::validName($name)) {
            return null;
        }
        $lc = mb_strtolower($name);
        $profile = ['name' => $name, 'rank' => null, 'coins' => null, 'playtime' => null, 'country' => null, 'achievements' => [], 'points' => 0];
        $found = false;

        foreach ($this->leaderboard() as $row) {
            if (mb_strtolower((string) ($row['name'] ?? '')) === $lc) {
                $profile['name'] = (string) $row['name'];
                $profile['rank'] = $row['rank'] ?? null;
                $profile['coins'] = $row['coins'] ?? null;
                $profile['playtime'] = $row['playtime'] ?? null;
                $found = true;
                break;
            }
        }

        foreach ($this->factions() as $f) {
            foreach ((array) ($f['members_list'] ?? []) as $m) {
                if (is_string($m) && mb_strtolower($m) === $lc) {
                    $profile['country'] = ['name' => (string) ($f['name'] ?? ''), 'tag' => $f['tag'] ?? null, 'hex' => $f['hex'] ?? null];
                    $found = true;
                    break 2;
                }
            }
        }

        try {
            $codes = AchievementUnlock::whereRaw('LOWER(player) = ?', [$lc])->orderByDesc('unlocked_at')->get(['code', 'unlocked_at']);
            if ($codes->isNotEmpty()) {
                $found = true;
                $catalog = Achievement::where('active', true)->get()->keyBy('code');
                foreach ($codes as $u) {
                    $a = $catalog[$u->code] ?? null;
                    if (! $a) {
                        continue;
                    }
                    $secret = (bool) $a->secret;
                    $profile['points'] += (int) $a->points;
                    $profile['achievements'][] = [
                        'name' => $secret ? '???' : $a->name,
                        'description' => $secret ? '' : (string) $a->description,
                        'rarity' => $a->rarity ?: 'common',
                        'points' => (int) $a->points,
                        'secret' => $secret,
                        'at' => $u->unlocked_at ? $u->unlocked_at->toDateString() : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PublicDashboard@player: ' . $e->getMessage());
        }

        return $found ? $profile : null;
    }
}
