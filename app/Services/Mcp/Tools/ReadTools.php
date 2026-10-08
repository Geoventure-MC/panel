<?php

namespace App\Services\Mcp\Tools;

use App\Http\Controllers\api\ApiController;
use App\Http\Controllers\api\CollectController;
use App\Models\Achievement;
use App\Models\AuditLog;
use App\Models\CommunityMod;
use App\Models\OptionsGeneral;
use App\Models\OptionsLoader;
use App\Models\OptionsMods;
use App\Models\OptionsMonitoring;
use App\Models\OptionsNotification;
use App\Models\OptionsRPC;
use App\Models\OptionsSecurity;
use App\Models\OptionsServer;
use App\Models\OptionsUI;
use App\Models\ServerCheck;
use App\Models\ServerIncident;
use App\Models\TelemetryEvent;
use App\Models\User;
use App\Models\WonderEdition;
use App\Services\Mcp\McpContext;
use App\Services\Mcp\McpSanitizer;
use App\Services\Mcp\McpTool;
use App\Services\Mcp\McpToolError;
use App\Services\ServerMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Outils de LECTURE (portée read) : aucun effet de bord. */
class ReadTools
{
    private const DAYS = ['type' => 'integer', 'enum' => [7, 30, 90], 'description' => 'Fenêtre en jours (7, 30 ou 90 ; défaut 30).'];

    public static function all(): array
    {
        $P = Support::PAGE_PROPS;
        $R = Support::PAGE_RULES;

        return [
            McpTool::make('panel_status', 'read',
                'État du panel : version, base de données, files d\'attente, espace disque, tâches planifiées, maintenance.',
                [], [], [], fn () => self::panelStatus()),

            McpTool::make('stats_overview', 'read',
                'Télémétrie du launcher sur une période : lancements, joueurs uniques (comptés par empreinte anonyme), évolution et répartitions par serveur, version et OS.',
                ['days' => self::DAYS], [], ['days' => 'sometimes|integer|in:7,30,90'],
                fn (array $a) => self::statsOverview((int) ($a['days'] ?? 30))),

            McpTool::make('stats_launches', 'read',
                'Série quotidienne des lancements du launcher (un point par jour, trous comblés à 0).',
                ['days' => self::DAYS], [], ['days' => 'sometimes|integer|in:7,30,90'],
                fn (array $a) => self::statsLaunches((int) ($a['days'] ?? 30))),

            McpTool::make('audit_log', 'read',
                'Journal d\'audit (plus récent d\'abord), avec filtres. Les actions du MCP ont l\'acteur « MCP:<clé> ». Aucune IP n\'est renvoyée.',
                $P + [
                    'action' => ['type' => 'string', 'maxLength' => 64, 'description' => 'Début de nom d\'action (ex. « notification », « mcp.maintenance »).'],
                    'source' => ['type' => 'string', 'enum' => ['mcp', 'admin'], 'description' => 'mcp = actions faites via MCP ; admin = actions faites depuis le panel.'],
                    'since_hours' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 8760, 'description' => 'Ne garder que les N dernières heures.'],
                ], [],
                $R + ['action' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.\-]+$/'], 'source' => 'sometimes|in:mcp,admin', 'since_hours' => 'sometimes|integer|min:1|max:8760'],
                fn (array $a) => self::auditLog($a)),

            McpTool::make('notifications_list', 'read',
                'Annonces affichées dans le bandeau du launcher.',
                $P + ['active_only' => ['type' => 'boolean', 'description' => 'Uniquement les annonces actives et non expirées.']], [],
                $R + ['active_only' => 'sometimes|boolean'],
                function (array $a) {
                    $q = OptionsNotification::query()->orderByDesc('id');
                    if (! empty($a['active_only'])) {
                        $q->where('active', true)->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                    }

                    return Support::paginate($q, $a, fn ($n) => [
                        'id' => $n->id, 'type' => $n->type, 'message' => $n->message, 'url' => $n->url,
                        'active' => (bool) $n->active, 'expires_at' => Support::iso($n->expires_at), 'created_at' => Support::iso($n->created_at),
                    ]);
                }),

            McpTool::make('achievements_list', 'read',
                'Catalogue des succès du launcher (code, rareté, catégorie, condition, points, niveaux, secret).',
                $P + [
                    'category' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Filtrer par catégorie exacte.'],
                    'active' => ['type' => 'boolean', 'description' => 'Filtrer par état actif.'],
                ], [],
                $R + ['category' => 'sometimes|string|max:100', 'active' => 'sometimes|boolean'],
                function (array $a) {
                    $q = Achievement::query()->orderBy('category')->orderBy('code');
                    if (isset($a['category'])) {
                        $q->where('category', $a['category']);
                    }
                    if (array_key_exists('active', $a)) {
                        $q->where('active', (bool) $a['active']);
                    }

                    return Support::paginate($q, $a, fn ($x) => $x->only([
                        'id', 'code', 'name', 'description', 'icon', 'points', 'rarity', 'category',
                        'condition_type', 'condition_value', 'active', 'secret', 'max_level',
                    ]));
                }),

            McpTool::make('wonder_editions', 'read',
                'Liste des éditions du concours de construction Wonder (phase, dates, nombre d\'équipes).',
                ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Nombre maximum (défaut 20).']], [],
                ['limit' => 'sometimes|integer|min:1|max:50'],
                fn (array $a) => WonderEdition::withCount('teams')->orderByDesc('opens_at')->orderByDesc('id')
                    ->limit((int) ($a['limit'] ?? 20))->get()->map(fn ($e) => [
                        'id' => $e->id, 'name' => $e->name, 'theme' => $e->theme, 'world' => $e->world, 'status' => $e->status,
                        'phase' => $e->phase(), 'opens_at' => Support::iso($e->opens_at), 'closes_at' => Support::iso($e->closes_at),
                        'teams' => $e->teams_count,
                    ])->all()),

            McpTool::make('wonder_get', 'read',
                'Détail d\'une édition Wonder : paramètres, barème, équipes et classement. Sans edition_id : l\'édition la plus récente.',
                ['edition_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Identifiant de l\'édition.']], [],
                ['edition_id' => 'sometimes|integer|min:1'],
                function (array $a) {
                    $e = isset($a['edition_id']) ? WonderEdition::find($a['edition_id']) : WonderEdition::orderByDesc('opens_at')->orderByDesc('id')->first();
                    if (! $e) {
                        throw new McpToolError('Édition Wonder introuvable.');
                    }

                    return [
                        'edition' => $e->only(['id', 'name', 'theme', 'world', 'status', 'team_size', 'builders', 'weight_technical', 'weight_aesthetic', 'weight_theme', 'rewards'])
                            + ['phase' => $e->phase(), 'opens_at' => Support::iso($e->opens_at), 'closes_at' => Support::iso($e->closes_at), 'published_at' => Support::iso($e->published_at)],
                        'teams' => $e->teams()->orderBy('name')->get()->map(fn ($t) => [
                            'id' => $t->id, 'name' => $t->name, 'color' => $t->color,
                            'builders' => (array) $t->builders, 'reserves' => (array) $t->reserves, 'images' => count((array) $t->images),
                        ])->all(),
                        'ranking' => $e->status === 'published' || $e->phase() === 'closed' ? $e->ranking() : 'Classement visible une fois l\'édition close ou publiée.',
                    ];
                }),

            McpTool::make('collect_get', 'read',
                'Événement de collecte communautaire : jauge, palier, boss prévu, contributeurs (miroir du jeu, même contenu que /utils/collecte).',
                [], [], [],
                function () {
                    $resp = app(CollectController::class)->getCollecte(Request::create('/utils/collecte', 'GET'));

                    return json_decode($resp->getContent(), true) ?? [];
                }),

            McpTool::make('community_mods_list', 'read',
                'Mods communauté proposés dans le launcher.',
                $P + ['active' => ['type' => 'boolean', 'description' => 'Filtrer par état actif.']], [],
                $R + ['active' => 'sometimes|boolean'],
                function (array $a) {
                    $q = CommunityMod::query()->orderBy('name');
                    if (array_key_exists('active', $a)) {
                        $q->where('active', (bool) $a['active']);
                    }

                    return Support::paginate($q, $a, fn ($m) => $m->only(['id', 'name', 'description', 'filename', 'url', 'category', 'author', 'version', 'active']));
                }),

            McpTool::make('mods_list', 'read',
                'Mods optionnels du modpack (par instance ; instance vide = partagé).',
                $P + ['instance' => ['type' => 'string', 'maxLength' => 64, 'description' => 'Slug d\'instance : renvoie ses mods + les mods partagés.']], [],
                $R + ['instance' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/']],
                function (array $a) {
                    $q = OptionsMods::query()->orderBy('name');
                    if (isset($a['instance'])) {
                        $q->where(fn ($w) => $w->where('instance', $a['instance'])->orWhereNull('instance'));
                    }

                    return Support::paginate($q, $a, fn ($m) => [
                        'id' => $m->id, 'file' => $m->file, 'name' => $m->name, 'description' => $m->description,
                        'optional' => (bool) $m->optional, 'recommended' => (bool) $m->recommended, 'instance' => $m->instance,
                    ]);
                }),

            McpTool::make('servers_list', 'read',
                'Serveurs / instances du launcher : nom, slug d\'instance, version, loader, dossier de données (aucun secret).',
                [], [], [],
                fn () => OptionsServer::orderByDesc('is_default')->orderBy('server_name')->get()->map(fn ($s) => [
                    'server_id' => $s->server_id, 'instance_slug' => $s->instance_slug, 'name' => $s->server_name,
                    'address' => $s->server_ip, 'port' => (int) $s->server_port, 'type' => $s->type ?? 'minecraft',
                    'minecraft_version' => $s->minecraft_version, 'loader_type' => $s->loader_type,
                    'loader_build_version' => $s->loader_build_version, 'loader_activation' => $s->loader_activation,
                    'data_folder' => $s->data_folder, 'modpack_folder' => $s->modpack_folder, 'theme_color' => $s->theme_color,
                    'is_default' => (bool) $s->is_default,
                ])->all()),

            McpTool::make('options_get', 'read',
                'Lit une section de réglages du panel. Les champs sensibles (clé API, webhook, identifiants) sont masqués (••••).',
                ['section' => ['type' => 'string', 'enum' => ['general', 'loader', 'ui', 'rpc', 'security'], 'description' => 'Section : general, loader, ui, rpc ou security.']],
                ['section'], ['section' => 'required|in:general,loader,ui,rpc,security'],
                fn (array $a) => self::optionsGet($a['section'])),

            McpTool::make('users_list', 'read',
                'Comptes du panel : pseudo, rôle, 2FA activée. Ni e-mail, ni mot de passe, ni dernière connexion (non suivie).',
                $P, [], $R,
                fn (array $a) => Support::paginate(User::query()->orderBy('name'), $a, fn ($u) => [
                    'id' => $u->id, 'name' => $u->name, 'role' => $u->role ?: 'admin', 'is_admin' => (bool) $u->is_admin,
                    'two_factor' => ! empty($u->totp_secret), 'sso_linked' => ! empty($u->sso_uuid), 'created_at' => Support::iso($u->created_at),
                ])),

            McpTool::make('monitor_status', 'read',
                'Supervision des serveurs de jeu : dernière sonde (en ligne, joueurs, latence), disponibilité 24 h / 7 j / 30 j, réglages d\'alerte.',
                [], [], [], fn () => self::monitorStatus()),

            McpTool::make('monitor_incidents', 'read',
                'Incidents de supervision (hors ligne, latence, serveur vide), plus récents d\'abord.',
                $P + ['open_only' => ['type' => 'boolean', 'description' => 'Uniquement les incidents non résolus.']], [],
                $R + ['open_only' => 'sometimes|boolean'],
                function (array $a) {
                    $q = ServerIncident::query()->orderByDesc('started_at');
                    if (! empty($a['open_only'])) {
                        $q->whereNull('resolved_at');
                    }

                    return Support::paginate($q, $a, fn ($i) => [
                        'id' => $i->id, 'server' => $i->server_name ?: $i->server_key, 'type' => $i->type, 'detail' => $i->detail,
                        'started_at' => Support::iso($i->started_at), 'resolved_at' => Support::iso($i->resolved_at),
                        'duration_minutes' => $i->durationMinutes(),
                    ]);
                }),

            McpTool::make('analytics_overview', 'read',
                'État de l\'analytique de jeu (base du jeu configurée, tables disponibles, dernière métrique reçue).',
                [], [], [],
                function () {
                    if (! class_exists(\App\Services\GameAnalytics::class)) {
                        throw new McpToolError('Service d\'analytique absent de ce panel.');
                    }
                    $svc = app(\App\Services\GameAnalytics::class);
                    $last = $svc->lastMetricTs();

                    return ['status' => $svc->status(), 'last_metric_at' => $last ? Carbon::createFromTimestamp($last)->toIso8601String() : null];
                }),

            McpTool::make('launcher_config_preview', 'read',
                'Aperçu de ce que le launcher recevrait de /utils/api pour une instance (liste blanche de joueurs abrégée).',
                ['instance' => ['type' => 'string', 'maxLength' => 64, 'description' => 'Slug d\'instance ; vide = configuration globale.']], [],
                ['instance' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/']],
                fn (array $a, McpContext $ctx) => self::configPreview($a['instance'] ?? null, $ctx)),

            McpTool::make('game_commands_history', 'read',
                'Dernières commandes envoyées au jeu (table gf_web_commands) et leur résultat.',
                ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Nombre (défaut 20).'],
                 'status' => ['type' => 'string', 'enum' => ['pending', 'done', 'failed'], 'description' => 'Filtrer par statut.']], [],
                ['limit' => 'sometimes|integer|min:1|max:50', 'status' => 'sometimes|in:pending,done,failed'],
                fn (array $a) => self::gameHistory($a)),
        ];
    }

    private static function panelStatus(): array
    {
        $db = ['driver' => config('database.default'), 'ok' => false];
        try {
            DB::select('select 1');
            $db['ok'] = true;
        } catch (\Throwable $e) {
        }

        $count = function (string $table): ?int {
            try {
                return Schema::hasTable($table) ? DB::table($table)->count() : null;
            } catch (\Throwable $e) {
                return null;
            }
        };

        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        $tasks = [];
        try {
            app(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            foreach (app(\Illuminate\Console\Scheduling\Schedule::class)->events() as $ev) {
                $tasks[] = ['expression' => $ev->expression, 'description' => $ev->description ?: (string) preg_replace('#^.*artisan[\'"]?\s+#', '', (string) $ev->command)];
            }
        } catch (\Throwable $e) {
            $tasks = [];
        }

        $lastRun = null;
        try {
            $lastRun = ServerCheck::max('created_at');
        } catch (\Throwable $e) {
        }

        return [
            'version'     => (string) config('app.version'),
            'php'         => PHP_VERSION,
            'laravel'     => app()->version(),
            'environment' => config('app.env'),
            'database'    => $db,
            'queue'       => ['driver' => config('queue.default'), 'pending_jobs' => $count('jobs'), 'failed_jobs' => $count('failed_jobs')],
            'disk'        => [
                'free_mb' => $free !== false ? (int) round($free / 1048576) : null,
                'total_mb' => $total !== false ? (int) round($total / 1048576) : null,
                'used_percent' => ($free !== false && $total) ? (int) round(100 - $free * 100 / $total) : null,
            ],
            'scheduler'   => ['tasks' => $tasks, 'last_monitor_probe' => Support::iso($lastRun)],
            'maintenance' => (bool) OptionsSecurity::first()?->maintenance,
        ];
    }

    private static function statsOverview(int $days): array
    {
        $since = Carbon::now()->subDays($days)->startOfDay();
        $prev = (clone $since)->subDays($days);
        $launches = TelemetryEvent::where('event', 'launch')->where('created_at', '>=', $since)->count();
        $launchesPrev = TelemetryEvent::where('event', 'launch')->whereBetween('created_at', [$prev, $since])->count();
        $players = (int) TelemetryEvent::where('created_at', '>=', $since)->whereNotNull('ip_hash')->distinct()->count('ip_hash');
        $playersPrev = (int) TelemetryEvent::whereBetween('created_at', [$prev, $since])->whereNotNull('ip_hash')->distinct()->count('ip_hash');
        $names = OptionsServer::pluck('server_name', 'server_id');

        $group = fn (string $col, string $count) => TelemetryEvent::where('created_at', '>=', $since)->whereNotNull($col)
            ->selectRaw("$col as k, $count as n")->groupBy($col)->orderByDesc('n')->limit(10)->pluck('n', 'k')->map(fn ($n) => (int) $n)->all();

        $byServer = [];
        foreach (TelemetryEvent::where('event', 'launch')->where('created_at', '>=', $since)->selectRaw('server_id as k, COUNT(*) as n')->groupBy('server_id')->orderByDesc('n')->limit(10)->get() as $r) {
            $byServer[(string) ($names[$r->k] ?? ($r->k ?: 'inconnu'))] = (int) $r->n;
        }
        $pct = fn (int $now, int $before) => $before > 0 ? round(($now - $before) * 100 / $before, 1) : null;

        return [
            'days' => $days,
            'launches' => $launches, 'launches_trend_percent' => $pct($launches, $launchesPrev),
            'unique_players' => $players, 'players_trend_percent' => $pct($players, $playersPrev),
            'launches_by_server' => $byServer,
            'players_by_launcher_version' => $group('launcher_version', 'COUNT(DISTINCT ip_hash)'),
            'events_by_os' => $group('os', 'COUNT(*)'),
        ];
    }

    private static function statsLaunches(int $days): array
    {
        $since = Carbon::now()->subDays($days)->startOfDay();
        $raw = TelemetryEvent::where('event', 'launch')->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy('day')->pluck('total', 'day');
        $series = [];
        for ($d = $since->copy(); $d <= Carbon::now(); $d->addDay()) {
            $series[] = ['date' => $d->format('Y-m-d'), 'launches' => (int) ($raw[$d->format('Y-m-d')] ?? 0)];
        }

        return ['days' => $days, 'total' => array_sum(array_column($series, 'launches')), 'series' => $series];
    }

    private static function auditLog(array $a): array
    {
        $q = AuditLog::with('user:id,name')->orderByDesc('id');
        if (isset($a['action'])) {
            $q->where('action', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $a['action']) . '%');
        }
        if (($a['source'] ?? null) === 'mcp') {
            $q->where('action', 'like', 'mcp.%');
        } elseif (($a['source'] ?? null) === 'admin') {
            $q->where('action', 'not like', 'mcp.%');
        }
        if (isset($a['since_hours'])) {
            $q->where('created_at', '>=', now()->subHours((int) $a['since_hours']));
        }

        return Support::paginate($q, $a, function ($l) {
            $changes = (array) $l->changes;
            $actor = $l->user?->name ?? ($changes['actor'] ?? null);

            return [
                'id' => $l->id, 'date' => Support::iso($l->created_at), 'actor' => $actor,
                'action' => $l->action, 'target' => $l->target,
                'changes' => $changes ? McpSanitizer::scrub(array_diff_key($changes, ['actor' => 1])) : null,
            ];
        });
    }

    private static function optionsGet(string $section): array
    {
        $strip = ['id', 'created_at', 'updated_at'];
        switch ($section) {
            case 'general':
                $m = OptionsGeneral::first();
                $data = $m ? array_diff_key($m->attributesToArray(), array_flip($strip)) : [];
                foreach (['azuriom_api_key', 'discord_webhook_url', 'sso_client_id'] as $k) {
                    if (array_key_exists($k, $data)) {
                        $data[$k] = Support::mask($data[$k]);
                    }
                }
                break;
            case 'loader':
                $data = ($m = OptionsLoader::first()) ? array_diff_key($m->attributesToArray(), array_flip($strip)) : [];
                break;
            case 'ui':
                $data = ($m = OptionsUI::first()) ? array_diff_key($m->attributesToArray(), array_flip($strip)) : [];
                break;
            case 'rpc':
                $data = ($m = OptionsRPC::first()) ? array_diff_key($m->attributesToArray(), array_flip($strip)) : [];
                break;
            default:
                $data = ($m = OptionsSecurity::first()) ? array_diff_key($m->attributesToArray(), array_flip($strip)) : [];
        }

        return ['section' => $section, 'configured' => $data !== [], 'values' => $data];
    }

    private static function monitorStatus(): array
    {
        $s = OptionsMonitoring::current();
        $monitor = app(ServerMonitor::class);
        $uptime = $monitor->uptimeAll();
        $servers = [];
        foreach (OptionsServer::all() as $srv) {
            $key = ServerMonitor::serverKey($srv);
            $last = ServerCheck::where('server_key', $key)->latest('created_at')->first();
            $servers[] = [
                'key' => $key, 'name' => $srv->server_name,
                'last_check' => $last ? [
                    'online' => (bool) $last->online, 'players' => $last->players, 'max_players' => $last->max_players,
                    'latency_ms' => $last->latency, 'version' => $last->version, 'at' => Support::iso($last->created_at),
                    'fresh' => $last->created_at->gt(now()->subMinutes(5)),
                ] : null,
                'uptime_percent' => $uptime[$key] ?? ['h24' => null, 'd7' => null, 'd30' => null],
            ];
        }

        return [
            'settings' => [
                'enabled' => (bool) $s->enabled, 'offline_after_minutes' => $s->offline_after_minutes,
                'latency_threshold_ms' => $s->latency_threshold_ms, 'latency_after_minutes' => $s->latency_after_minutes,
                'empty_after_minutes' => $s->empty_after_minutes, 'reminder_minutes' => $s->reminder_minutes,
                'has_dedicated_webhook' => ! empty($s->webhook_url),
            ],
            'servers' => $servers,
            'open_incidents' => ServerIncident::whereNull('resolved_at')->count(),
        ];
    }

    private static function configPreview(?string $instance, McpContext $ctx): array
    {
        $req = Request::create($ctx->request->getSchemeAndHttpHost() . '/utils/api', 'GET', $instance ? ['instance' => $instance] : []);
        $old = app('request');
        app()->instance('request', $req);
        try {
            $resp = app(ApiController::class)->getOptions();
        } finally {
            app()->instance('request', $old);
        }
        $data = json_decode($resp->getContent(), true) ?? [];
        if ($instance && ($data['instance'] ?? null) === null && OptionsServer::resolveInstance($instance) === null) {
            $data['_warning'] = "Instance « {$instance} » inconnue : configuration globale renvoyée.";
        }
        foreach (['whitelist', 'ignored'] as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                $data[$k . '_count'] = count($data[$k]);
                $data[$k] = array_slice($data[$k], 0, 10);
            }
        }

        return $data;
    }

    private static function gameHistory(array $a): array
    {
        if ((string) config('database.connections.game.database') === '') {
            throw new McpToolError('Base du jeu non configurée (GEO_GAME_DB_*).');
        }
        $table = config('geoventure.game_table_prefix', 'gf_') . 'web_commands';
        try {
            $q = DB::connection('game')->table($table)->orderByDesc('id')->limit((int) ($a['limit'] ?? 20));
            if (isset($a['status'])) {
                $q->where('status', $a['status']);
            }
            $keep = array_flip(['id', 'type', 'target', 'amount', 'status', 'result', 'created_at', 'processed_at', 'done_at']);

            return $q->get()->map(fn ($r) => array_intersect_key((array) $r, $keep))->all();
        } catch (\Throwable $e) {
            throw new McpToolError('Base du jeu injoignable.');
        }
    }
}
