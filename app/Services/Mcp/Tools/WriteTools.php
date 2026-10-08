<?php

namespace App\Services\Mcp\Tools;

use App\Models\Achievement;
use App\Models\CommunityMod;
use App\Models\OptionsNotification;
use App\Models\OptionsSecurity;
use App\Models\WonderEdition;
use App\Services\Mcp\McpContext;
use App\Services\Mcp\McpTool;
use App\Services\Mcp\McpToolError;
use App\Services\ServerMonitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Outils d'ÉCRITURE (portée write, confirm=true obligatoire). Chaque appel est consigné dans l'audit. */
class WriteTools
{
    private const ACH_COND = ['first_launch', 'launch_count', 'playtime_hours', 'instances_tried', 'manual'];

    public static function all(): array
    {
        $types = self::gameTypes();

        return [
            McpTool::make('notification_create', 'write',
                'Crée une annonce affichée dans le bandeau du launcher (active immédiatement).',
                [
                    'type' => ['type' => 'string', 'enum' => ['info', 'warning', 'maintenance', 'event']],
                    'message' => ['type' => 'string', 'maxLength' => 500, 'description' => 'Texte de l\'annonce (500 caractères max).'],
                    'url' => ['type' => 'string', 'maxLength' => 255, 'description' => 'Lien « En savoir plus » (http/https), optionnel.'],
                    'expires_at' => ['type' => 'string', 'description' => 'Date d\'expiration ISO 8601 (dans le futur), optionnelle.'],
                ], ['type', 'message'],
                [
                    'type' => 'required|in:info,warning,maintenance,event',
                    'message' => 'required|string|min:1|max:500',
                    'url' => 'sometimes|nullable|url|max:255|starts_with:http://,https://',
                    'expires_at' => 'sometimes|nullable|date|after:now',
                ],
                function (array $a, McpContext $ctx) {
                    $n = OptionsNotification::create([
                        'type' => $a['type'], 'message' => $a['message'], 'url' => $a['url'] ?? null,
                        'active' => true, 'expires_at' => $a['expires_at'] ?? null,
                    ]);
                    $ctx->audit('notification.create', $n, [], ['type' => $n->type, 'message' => $n->message, 'url' => $n->url, 'expires_at' => Support::iso($n->expires_at)]);

                    return ['id' => $n->id, 'type' => $n->type, 'active' => true, 'expires_at' => Support::iso($n->expires_at)];
                }),

            McpTool::make('notification_toggle', 'write',
                'Active ou désactive une annonce du launcher (bascule).',
                ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], ['id' => 'required|integer|min:1'],
                function (array $a, McpContext $ctx) {
                    $n = OptionsNotification::find($a['id']) ?? throw new McpToolError('Annonce introuvable.');
                    $before = (bool) $n->active;
                    $n->update(['active' => ! $before]);
                    $ctx->audit('notification.toggle', $n, ['active' => $before], ['active' => (bool) $n->active]);

                    return ['id' => $n->id, 'active' => (bool) $n->active];
                }),

            McpTool::make('maintenance_set', 'write',
                'Active ou désactive le mode maintenance du launcher (le lancement du jeu est bloqué tant qu\'il est actif) et fixe le message affiché.',
                [
                    'enabled' => ['type' => 'boolean', 'description' => 'true = maintenance active.'],
                    'message' => ['type' => 'string', 'maxLength' => 255, 'description' => 'Message affiché aux joueurs (optionnel).'],
                ], ['enabled'],
                ['enabled' => 'required|boolean', 'message' => 'sometimes|string|min:1|max:255'],
                function (array $a, McpContext $ctx) {
                    $s = OptionsSecurity::first() ?? OptionsSecurity::create(['maintenance' => 0, 'maintenance_message' => 'Maintenance en cours.']);
                    $before = ['maintenance' => (bool) $s->maintenance, 'message' => $s->maintenance_message];
                    $upd = ['maintenance' => (bool) $a['enabled']];
                    if (isset($a['message'])) {
                        $upd['maintenance_message'] = $a['message'];
                    }
                    $s->update($upd);
                    $after = ['maintenance' => (bool) $s->maintenance, 'message' => $s->maintenance_message];
                    $ctx->audit('maintenance.set', $s, $before, $after, 'maintenance.toggle');

                    return $after;
                }),

            McpTool::make('achievement_upsert', 'write',
                'Crée ou met à jour un succès par son code. À la création : name, points et condition_type sont obligatoires ; à la mise à jour, seuls les champs fournis changent.',
                [
                    'code' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Identifiant stable (lettres, chiffres, _ . -).'],
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'description' => ['type' => 'string', 'maxLength' => 1000],
                    'icon' => ['type' => 'string', 'maxLength' => 255],
                    'points' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100000],
                    'rarity' => ['type' => 'string', 'enum' => Achievement::RARITIES],
                    'category' => ['type' => 'string', 'maxLength' => 255],
                    'condition_type' => ['type' => 'string', 'enum' => self::ACH_COND],
                    'condition_value' => ['type' => 'integer', 'minimum' => 0],
                    'max_level' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                    'secret' => ['type' => 'boolean'],
                    'active' => ['type' => 'boolean'],
                ], ['code'],
                [
                    'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.\-]+$/'],
                    'name' => 'sometimes|string|min:1|max:255',
                    'description' => 'sometimes|nullable|string|max:1000',
                    'icon' => 'sometimes|nullable|string|max:255',
                    'points' => 'sometimes|integer|min:0|max:100000',
                    'rarity' => ['sometimes', Rule::in(Achievement::RARITIES)],
                    'category' => 'sometimes|nullable|string|max:255',
                    'condition_type' => ['sometimes', Rule::in(self::ACH_COND)],
                    'condition_value' => 'sometimes|nullable|integer|min:0',
                    'max_level' => 'sometimes|integer|min:1|max:5',
                    'secret' => 'sometimes|boolean',
                    'active' => 'sometimes|boolean',
                ],
                function (array $a, McpContext $ctx) {
                    $fields = array_diff_key($a, ['code' => 1]);
                    $ach = Achievement::where('code', $a['code'])->first();
                    if (! $ach) {
                        foreach (['name', 'points', 'condition_type'] as $req) {
                            if (! isset($fields[$req])) {
                                throw new McpToolError("Création d'un succès : le champ « {$req} » est obligatoire.");
                            }
                        }
                        $ach = Achievement::create($fields + ['code' => $a['code'], 'rarity' => 'common', 'active' => true, 'secret' => false, 'max_level' => 1]);
                        $ctx->audit('achievement.create', $ach, [], $fields + ['code' => $a['code']]);

                        return ['created' => true, 'code' => $ach->code, 'id' => $ach->id];
                    }
                    $before = array_intersect_key($ach->only(array_keys($fields)), $fields);
                    $ach->update($fields);
                    $ctx->audit('achievement.update', $ach, $before, $fields);

                    return ['created' => false, 'code' => $ach->code, 'id' => $ach->id, 'updated' => array_keys($fields)];
                }),

            McpTool::make('community_mod_set_status', 'write',
                'Active ou désactive un mod communauté (visible ou non dans le launcher).',
                ['id' => ['type' => 'integer', 'minimum' => 1], 'active' => ['type' => 'boolean']], ['id', 'active'],
                ['id' => 'required|integer|min:1', 'active' => 'required|boolean'],
                function (array $a, McpContext $ctx) {
                    $m = CommunityMod::find($a['id']) ?? throw new McpToolError('Mod communauté introuvable.');
                    $before = (bool) $m->active;
                    $m->update(['active' => (bool) $a['active']]);
                    $ctx->audit('community_mod.status', $m, ['active' => $before], ['active' => (bool) $m->active]);

                    return ['id' => $m->id, 'name' => $m->name, 'active' => (bool) $m->active];
                }),

            McpTool::make('wonder_edition_update', 'write',
                'Modifie les champs d\'une édition Wonder (liste blanche) puis la renvoie au jeu. Refusé une fois l\'édition publiée (résultats figés).',
                [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'name' => ['type' => 'string', 'maxLength' => 40],
                    'theme' => ['type' => 'string', 'maxLength' => 80],
                    'world' => ['type' => 'string', 'maxLength' => 32, 'description' => 'Nom du monde (lettres, chiffres, _ -).'],
                    'opens_at' => ['type' => 'string', 'description' => 'Ouverture, ISO 8601.'],
                    'closes_at' => ['type' => 'string', 'description' => 'Clôture, ISO 8601 (après l\'ouverture).'],
                    'team_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60],
                    'builders' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                    'weight_technical' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'weight_aesthetic' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'weight_theme' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'rewards' => ['type' => 'string', 'maxLength' => 1000],
                ], ['id'],
                [
                    'id' => 'required|integer|min:1',
                    'name' => 'sometimes|string|min:1|max:40',
                    'theme' => 'sometimes|string|min:1|max:80',
                    'world' => ['sometimes', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/'],
                    'opens_at' => 'sometimes|date',
                    'closes_at' => 'sometimes|date',
                    'team_size' => 'sometimes|integer|min:1|max:60',
                    'builders' => 'sometimes|integer|min:1|max:10',
                    'weight_technical' => 'sometimes|integer|min:0|max:100',
                    'weight_aesthetic' => 'sometimes|integer|min:0|max:100',
                    'weight_theme' => 'sometimes|integer|min:0|max:100',
                    'rewards' => 'sometimes|nullable|string|max:1000',
                ],
                fn (array $a, McpContext $ctx) => self::wonderUpdate($a, $ctx)),

            McpTool::make('game_command_send', 'write',
                'Envoie UNE commande au serveur de jeu via gf_web_commands (consommée par le plugin sous ~5 s). Types : ' . implode(', ', $types)
                . '. Montant plafonné par type, motif obligatoire (consigné dans l\'audit), 30 commandes/heure par clé.',
                [
                    'type' => ['type' => 'string', 'enum' => $types],
                    'target' => ['type' => 'string', 'maxLength' => 255, 'description' => 'Cible : pseudo, pays, message ou identifiant selon le type.'],
                    'amount' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Montant (plafond selon le type ; 0 pour broadcast/trigger_event).'],
                    'reason' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 200, 'description' => 'Motif de l\'action (obligatoire, audité).'],
                ], ['type', 'target', 'reason'],
                [
                    'type' => ['required', Rule::in($types)],
                    'target' => ['required', 'string', 'min:1', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/u'],
                    'amount' => 'sometimes|integer|min:0|max:100000000',
                    'reason' => 'required|string|min:5|max:200',
                ],
                fn (array $a, McpContext $ctx) => self::gameCommand($a, $ctx)),

            McpTool::make('monitor_test_alert', 'write',
                'Envoie une alerte de test au webhook Discord de supervision (5 par minute maximum).',
                [], [], [],
                function (array $a, McpContext $ctx) {
                    if (RateLimiter::tooManyAttempts('mcp:monitor-test', 5)) {
                        throw new McpToolError('Trop d\'alertes de test : réessayez dans une minute.');
                    }
                    RateLimiter::hit('mcp:monitor-test', 60);
                    $ok = app(ServerMonitor::class)->sendTest();
                    $ctx->audit('monitor.test', null, [], ['sent' => $ok]);

                    return ['sent' => $ok, 'note' => $ok ? 'Alerte de test envoyée.' : 'Aucun webhook configuré ou envoi refusé.'];
                }),
        ];
    }

    /** Types de commandes de jeu prévus par AdminGameCommandController (source unique). */
    public static function gameTypes(): array
    {
        try {
            return (new \ReflectionClassConstant(\App\Http\Controllers\AdminGameCommandController::class, 'TYPES'))->getValue();
        } catch (\Throwable $e) {
            return array_keys(config('mcp.game_command_caps'));
        }
    }

    private static function gameCommand(array $a, McpContext $ctx): array
    {
        $cap = config('mcp.game_command_caps')[$a['type']] ?? null;
        if ($cap === null) {
            throw new McpToolError('Type de commande non autorisé via MCP : ' . $a['type']);
        }
        $amount = (int) ($a['amount'] ?? 0);
        if ($amount > $cap) {
            throw new McpToolError("Montant trop élevé pour {$a['type']} : plafond {$cap}.");
        }
        if ($cap > 0 && ! in_array($a['type'], ['architecture_rating'], true) && $amount < 1) {
            throw new McpToolError("Le montant doit être d'au moins 1 pour {$a['type']}.");
        }
        if ($cap === 0 && $amount !== 0) {
            throw new McpToolError("{$a['type']} n'accepte pas de montant.");
        }
        if ((string) config('database.connections.game.database') === '') {
            throw new McpToolError('Base du jeu non configurée (GEO_GAME_DB_*) : commande impossible.');
        }

        $hourKey = 'mcp:gamecmd:' . $ctx->key->id . ':' . now()->format('YmdH');
        $max = (int) config('mcp.game_commands_per_hour');
        if ((int) Cache::get($hourKey, 0) >= $max) {
            throw new McpToolError("Plafond de {$max} commandes de jeu par heure atteint pour cette clé.");
        }

        try {
            DB::connection('game')->table(config('geoventure.game_table_prefix', 'gf_') . 'web_commands')->insert([
                'type' => $a['type'], 'target' => $a['target'], 'amount' => $amount, 'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[MCP] commande de jeu impossible : ' . $e->getMessage());
            throw new McpToolError('Base du jeu injoignable : commande non envoyée.');
        }
        Cache::add($hourKey, 0, 3700);
        Cache::increment($hourKey);

        $ctx->audit('game_command.create', null, [], ['type' => $a['type'], 'target' => $a['target'], 'amount' => $amount, 'reason' => $a['reason']]);

        return ['queued' => true, 'type' => $a['type'], 'amount' => $amount, 'note' => 'Commande en attente : le plugin la traite sous quelques secondes (voir game_commands_history).'];
    }

    private static function wonderUpdate(array $a, McpContext $ctx): array
    {
        $e = WonderEdition::find($a['id']) ?? throw new McpToolError('Édition Wonder introuvable.');
        if ($e->status === 'published') {
            throw new McpToolError('Édition publiée : notes et paramètres sont figés.');
        }
        $fields = array_diff_key($a, ['id' => 1]);
        if ($fields === []) {
            throw new McpToolError('Aucun champ à modifier.');
        }
        if (isset($fields['name'])) {
            $fields['name'] = trim(str_replace(['|', "\n", "\r"], ' ', $fields['name']));
        }
        $opens = \Illuminate\Support\Carbon::parse($fields['opens_at'] ?? $e->opens_at);
        $closes = \Illuminate\Support\Carbon::parse($fields['closes_at'] ?? $e->closes_at);
        if ($closes->lte($opens)) {
            throw new McpToolError('La clôture doit être postérieure à l\'ouverture.');
        }
        $before = array_intersect_key($e->only(array_keys($fields)), $fields);
        $e->update($fields);
        $ctx->audit('wonder.edition.update', $e, $before, $fields);

        // Renvoi de l'édition au plugin (comme AdminWonderController) ; échec = avertissement, jamais d'erreur.
        $synced = false;
        if ((string) config('database.connections.game.database') !== '') {
            try {
                DB::connection('game')->table(config('geoventure.game_table_prefix', 'gf_') . 'web_commands')->insert([
                    'type' => 'wonder_edition', 'status' => 'pending', 'amount' => 0,
                    'target' => json_encode([
                        'id' => $e->id, 'name' => $e->name, 'theme' => $e->theme, 'world' => $e->world,
                        'open' => $e->opens_at?->getTimestamp() ?? 0, 'close' => $e->closes_at?->getTimestamp() ?? 0,
                        'size' => $e->team_size, 'builders' => $e->builders,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
                $synced = true;
            } catch (\Throwable $ex) {
                Log::warning('[MCP] Wonder : envoi au jeu impossible : ' . $ex->getMessage());
            }
        }

        return ['id' => $e->id, 'updated' => array_keys($fields), 'game_synced' => $synced,
            'note' => $synced ? null : 'Enregistré dans le panel ; jeu non synchronisé (utiliser « Resynchroniser » dans Admin → Wonder).'];
    }
}
