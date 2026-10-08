<?php

namespace App\Services;

use App\Http\Controllers\api\ServerStatusController;
use App\Models\AuditLog;
use App\Models\OptionsGeneral;
use App\Models\OptionsMonitoring;
use App\Models\OptionsServer;
use App\Models\ServerCheck;
use App\Models\ServerIncident;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Supervision du serveur de jeu : sondes SLP historisées (server_checks),
 * détection d'incidents (offline / latency / empty) avec anti-spam, alertes
 * Discord, journal d'audit et calcul de disponibilité.
 *
 * Tout est fail-safe : aucune méthode publique ne lève d'exception.
 */
class ServerMonitor
{
    public const RETENTION_DAYS = 30;
    /** Tolérance (s) sur la couverture de la fenêtre d'observation (sonde ~1/min). */
    private const COVERAGE_SLACK = 90;

    public function __construct(private ServerStatusController $status) {}

    public static function serverKey(OptionsServer $s): string
    {
        return (string) ($s->instance_slug ?: $s->server_id);
    }

    /**
     * Une passe : sonde chaque serveur, enregistre, évalue les incidents, purge.
     *
     * @return array<int, array<string, mixed>> résumé par serveur
     */
    public function run(): array
    {
        $settings = OptionsMonitoring::current();
        $summary = [];
        if (! $settings->enabled) {
            return $summary;
        }

        try {
            $servers = OptionsServer::all();
        } catch (\Throwable $e) {
            Log::warning('ServerMonitor: lecture des serveurs impossible: ' . $e->getMessage());

            return $summary;
        }

        foreach ($servers as $server) {
            try {
                $key = self::serverKey($server);
                $ping = $this->status->probe((string) $server->server_ip, (int) $server->server_port);
                $check = ServerCheck::create([
                    'server_key'  => $key,
                    'online'      => (bool) $ping['online'],
                    'players'     => $ping['players'],
                    'max_players' => $ping['max_players'],
                    'latency'     => $ping['latency'],
                    'version'     => $ping['version'] !== null ? mb_substr((string) $ping['version'], 0, 191) : null,
                    'created_at'  => now(),
                ]);
                $this->evaluate($key, (string) $server->server_name, $check, $settings);
                $summary[] = ['server' => $key, 'online' => $check->online, 'players' => $check->players, 'latency' => $check->latency];
            } catch (\Throwable $e) {
                Log::warning('ServerMonitor: ' . $e->getMessage());
            }
        }

        $this->purge();

        return $summary;
    }

    /** Purge > 30 jours (une fois par heure au plus). */
    public function purge(): void
    {
        try {
            Cache::remember('server_monitor_purge', 3600, function () {
                ServerCheck::where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
                ServerIncident::whereNotNull('resolved_at')->where('resolved_at', '<', now()->subDays(180))->delete();

                return true;
            });
        } catch (\Throwable $e) {
            // silencieux
        }
    }

    // ------------------------------------------------------------------
    // Détection d'incidents
    // ------------------------------------------------------------------

    private function evaluate(string $key, string $name, ServerCheck $latest, OptionsMonitoring $s): void
    {
        $latencyBad = fn (ServerCheck $c) => $c->online && $c->latency !== null && $c->latency > $s->latency_threshold_ms;
        $isEmpty = fn (ServerCheck $c) => $c->online && $c->players !== null && (int) $c->players === 0;

        // --- hors ligne ---
        $this->handle($key, $name, 'offline', ! $latest->online,
            fn () => $this->windowMatches($key, $s->offline_after_minutes, fn ($c) => ! $c->online),
            null, $s, true);

        // --- latence anormale ---
        $this->handle($key, $name, 'latency', $latencyBad($latest),
            fn () => $this->windowMatches($key, $s->latency_after_minutes, $latencyBad),
            sprintf('> %d ms', $s->latency_threshold_ms), $s, true);

        // --- 0 joueur depuis longtemps (info, pas de rappel ni de message de résolution) ---
        if ($s->empty_after_minutes > 0) {
            $this->handle($key, $name, 'empty', $isEmpty($latest),
                fn () => $this->windowMatches($key, $s->empty_after_minutes, $isEmpty),
                null, $s, false);
        } else {
            $this->closeIfOpen($key, $name, 'empty', $s, false);
        }
    }

    /**
     * @param bool $nowBad    la dernière sonde remplit la condition d'incident
     * @param callable $window renvoie la date de début si la fenêtre entière remplit la condition
     */
    private function handle(string $key, string $name, string $type, bool $nowBad, callable $window, ?string $detail, OptionsMonitoring $s, bool $loud): void
    {
        $open = ServerIncident::where('server_key', $key)->where('type', $type)->whereNull('resolved_at')->latest('id')->first();

        if ($open) {
            if (! $nowBad) {
                $this->closeIfOpen($key, $name, $type, $s, $loud);

                return;
            }
            // Rappel espacé (jamais pour les incidents « info »).
            if ($loud && $open->last_notified_at && $open->last_notified_at->diffInMinutes(now()) >= $s->reminder_minutes) {
                $open->last_notified_at = now();
                $open->save();
                $this->alert($s, 'reminder', $type, $open);
            }

            return;
        }

        if (! $nowBad) {
            return;
        }
        $since = $window();
        if ($since === null) {
            return;
        }

        $incident = ServerIncident::create([
            'server_key'       => $key,
            'server_name'      => $name,
            'type'             => $type,
            'detail'           => $detail,
            'started_at'       => $since,
            'last_notified_at' => now(),
        ]);
        $this->audit('monitor.incident', $incident);
        $this->alert($s, 'open', $type, $incident);
    }

    private function closeIfOpen(string $key, string $name, string $type, OptionsMonitoring $s, bool $loud): void
    {
        $open = ServerIncident::where('server_key', $key)->where('type', $type)->whereNull('resolved_at')->get();
        foreach ($open as $incident) {
            $incident->resolved_at = now();
            $incident->save();
            $this->audit('monitor.resolved', $incident);
            if ($loud) {
                $this->alert($s, 'resolved', $type, $incident);
            }
        }
    }

    /**
     * Début (Carbon) de la fenêtre si TOUTES les sondes des $minutes dernières
     * minutes remplissent la condition et que la fenêtre est couverte.
     */
    private function windowMatches(string $key, int $minutes, callable $predicate): ?\Illuminate\Support\Carbon
    {
        $cutoff = now()->subMinutes(max(1, $minutes));
        $rows = ServerCheck::where('server_key', $key)->where('created_at', '>=', $cutoff)->orderBy('created_at')->get();
        if ($rows->isEmpty()) {
            return null;
        }
        foreach ($rows as $c) {
            if (! $predicate($c)) {
                return null;
            }
        }
        $first = $rows->first()->created_at;
        if ($first->gt($cutoff->copy()->addSeconds(self::COVERAGE_SLACK))) {
            return null; // pas assez d'historique : on attend
        }

        return $first;
    }

    // ------------------------------------------------------------------
    // Alertes
    // ------------------------------------------------------------------

    public static function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h >= 24) {
            return intdiv($h, 24) . ' j ' . ($h % 24) . ' h';
        }

        return sprintf('%d h %02d min', $h, $m);
    }

    private function webhookUrl(OptionsMonitoring $s): ?string
    {
        if ($s->webhook_url) {
            return $s->webhook_url;
        }
        try {
            return OptionsGeneral::first()?->discord_webhook_url ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param 'open'|'reminder'|'resolved' $phase */
    private function alert(OptionsMonitoring $s, string $phase, string $type, ServerIncident $i): void
    {
        $url = $this->webhookUrl($s);
        if (! $url) {
            return;
        }
        $params = [
            'server'   => $i->server_name ?: $i->server_key,
            'duration' => self::formatDuration($i->durationMinutes()),
            'detail'   => (string) $i->detail,
        ];
        $loc = (string) config('geoventure.monitor_alert_locale', 'fr');
        $title = __("messages.monitor.alert.{$phase}_{$type}_title", $params, $loc);
        $body = __("messages.monitor.alert.{$phase}_{$type}", $params, $loc);
        $color = match (true) {
            $phase === 'resolved' => 0x4ade80,
            $type === 'empty'     => 0x60a5fa,
            $type === 'latency'   => 0xfbbf24,
            default               => 0xef4444,
        };
        DiscordWebhook::sendEmbed($url, $title, $body, $color);
    }

    /** Alerte de test (bouton admin). */
    public function sendTest(): bool
    {
        $url = $this->webhookUrl(OptionsMonitoring::current());
        if (! $url) {
            return false;
        }

        $loc = (string) config('geoventure.monitor_alert_locale', 'fr');

        return DiscordWebhook::sendEmbed($url, __('messages.monitor.alert.test_title', [], $loc), __('messages.monitor.alert.test', [], $loc), 0x60a5fa);
    }

    private function audit(string $action, ServerIncident $incident): void
    {
        try {
            AuditLog::record($action, $incident, [
                'type' => $incident->type, 'server' => $incident->server_key,
                'duration_min' => $incident->resolved_at ? $incident->durationMinutes() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ServerMonitor audit: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Lecture : disponibilité, courbes
    // ------------------------------------------------------------------

    /**
     * Disponibilité (%) 24 h / 7 j / 30 j par server_key ; null si aucune donnée.
     *
     * @return array<string, array{h24: ?float, d7: ?float, d30: ?float}>
     */
    public function uptimeAll(): array
    {
        $out = [];
        try {
            $t24 = now()->subDay()->toDateTimeString();
            $t7 = now()->subDays(7)->toDateTimeString();
            $t30 = now()->subDays(30)->toDateTimeString();
            $rows = DB::table('server_checks')
                ->selectRaw('server_key,
                    SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS n24,
                    SUM(CASE WHEN created_at >= ? AND online = 1 THEN 1 ELSE 0 END) AS u24,
                    SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS n7,
                    SUM(CASE WHEN created_at >= ? AND online = 1 THEN 1 ELSE 0 END) AS u7,
                    COUNT(*) AS n30,
                    SUM(CASE WHEN online = 1 THEN 1 ELSE 0 END) AS u30', [$t24, $t24, $t7, $t7])
                ->where('created_at', '>=', $t30)
                ->groupBy('server_key')
                ->get();
            $pct = fn ($u, $n) => (int) $n > 0 ? round(((int) $u / (int) $n) * 100, 2) : null;
            foreach ($rows as $r) {
                $out[$r->server_key] = [
                    'h24' => $pct($r->u24, $r->n24),
                    'd7'  => $pct($r->u7, $r->n7),
                    'd30' => $pct($r->u30, $r->n30),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ServerMonitor@uptimeAll: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Série 24 h par tranches de 10 min : labels HH:MM, joueurs et latence moyens.
     *
     * @return array{labels: string[], players: array<int, ?float>, latency: array<int, ?float>}
     */
    public function series(string $key): array
    {
        $empty = ['labels' => [], 'players' => [], 'latency' => []];
        try {
            $rows = ServerCheck::where('server_key', $key)->where('created_at', '>=', now()->subDay())->orderBy('created_at')->get();
            $buckets = [];
            foreach ($rows as $c) {
                $ts = intdiv($c->created_at->getTimestamp(), 600) * 600;
                $b = &$buckets[$ts];
                $b ??= ['p' => [], 'l' => []];
                if ($c->players !== null) {
                    $b['p'][] = $c->players;
                }
                if ($c->latency !== null) {
                    $b['l'][] = $c->latency;
                }
                unset($b);
            }
            ksort($buckets);
            $avg = fn (array $a) => $a ? round(array_sum($a) / count($a), 1) : null;
            $out = $empty;
            foreach ($buckets as $ts => $b) {
                $out['labels'][] = \Carbon\Carbon::createFromTimestamp($ts)->format('H:i');
                $out['players'][] = $avg($b['p']);
                $out['latency'][] = $avg($b['l']);
            }

            return $out;
        } catch (\Throwable $e) {
            return $empty;
        }
    }
}
