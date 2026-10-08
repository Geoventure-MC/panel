<?php

namespace App\Services\Mcp\Tools;

use App\Models\OptionsGeneral;
use App\Models\OptionsLoader;
use App\Models\OptionsNotification;
use App\Models\OptionsRPC;
use App\Models\OptionsSecurity;
use App\Models\OptionsServer;
use App\Models\OptionsUI;
use App\Models\User;
use App\Services\Mcp\McpContext;
use App\Services\Mcp\McpTool;
use App\Services\Mcp\McpToolError;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Outils d'ADMINISTRATION (portée admin, confirm=true obligatoire). */
class AdminTools
{
    private const LOADERS = ['forge', 'fabric', 'legacyfabric', 'neoForge', 'quilt'];

    /** Clés modifiables par section : règle de validation. Tout le reste est refusé. */
    private static function optionRules(): array
    {
        return [
            'general' => [
                'mods_enabled' => 'boolean', 'file_verification' => 'boolean', 'embedded_java' => 'boolean',
                'game_folder_name' => ['string', 'min:1', 'max:100', 'regex:/^[A-Za-z0-9_. \-]+$/'],
                'email_verified' => 'boolean', 'role_display' => 'integer|min:0|max:10', 'money_display' => 'integer|min:0|max:10',
                'min_ram' => 'integer|min:512|max:65536', 'max_ram' => 'integer|min:512|max:65536',
                'map_url' => 'nullable|string|url|max:255|starts_with:http://,https://',
            ],
            'loader' => [
                'minecraft_version' => ['string', 'regex:/^\d+\.\d+(\.\d+)?$/'],
                'loader_activation' => 'boolean',
                'loader_type' => [Rule::in(self::LOADERS)],
                'loader_forge_version' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
                'loader_build_version' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            ],
            'ui' => [
                'alert_activation' => 'boolean', 'alert_scroll' => 'boolean', 'alert_msg' => 'string|min:1|max:255',
                'video_activation' => 'boolean', 'video_url' => 'string|url|max:255|starts_with:http://,https://',
                'splash' => 'string|min:1|max:255', 'splash_author' => 'string|min:1|max:255',
                'accent_color' => ['string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ],
            'rpc' => [
                'rpc_activation' => 'boolean', 'rpc_details' => 'string|min:1|max:255', 'rpc_state' => 'string|min:1|max:255',
                'rpc_large_image' => 'nullable|string|max:255', 'rpc_large_text' => 'string|min:1|max:255',
                'rpc_small_image' => 'nullable|string|max:255', 'rpc_small_text' => 'string|min:1|max:255',
                'rpc_button1' => 'nullable|string|max:50', 'rpc_button1_url' => 'nullable|url|max:200|starts_with:http://,https://',
                'rpc_button2' => 'nullable|string|max:50', 'rpc_button2_url' => 'nullable|url|max:200|starts_with:http://,https://',
            ],
            'security' => ['whitelist' => 'boolean'],
        ];
    }

    private const MODELS = [
        'general' => OptionsGeneral::class, 'loader' => OptionsLoader::class, 'ui' => OptionsUI::class,
        'rpc' => OptionsRPC::class, 'security' => OptionsSecurity::class,
    ];

    /** Action « critique » relayée à Discord par section. */
    private const DISCORD = ['loader' => 'loader.update', 'security' => 'security.update'];

    public static function all(): array
    {
        $allowedKeys = array_map('array_keys', self::optionRules());

        return [
            McpTool::make('option_set', 'admin',
                'Modifie UN réglage non sensible. Clés autorisées — general : ' . implode(', ', $allowedKeys['general'])
                . ' ; loader : ' . implode(', ', $allowedKeys['loader']) . ' ; ui : ' . implode(', ', $allowedKeys['ui'])
                . ' ; rpc : ' . implode(', ', $allowedKeys['rpc']) . ' ; security : whitelist. '
                . 'Les secrets (clé API, webhook, SSO, identifiants) sont refusés. Pour la maintenance, utiliser maintenance_set.',
                [
                    'section' => ['type' => 'string', 'enum' => array_keys(self::MODELS)],
                    'key' => ['type' => 'string', 'maxLength' => 64, 'description' => 'Nom du champ (voir la liste ci-dessus).'],
                    'value' => ['type' => ['string', 'integer', 'boolean', 'null'], 'description' => 'Nouvelle valeur (booléen, entier ou texte selon le champ).'],
                ], ['section', 'key', 'value'],
                ['section' => 'required|in:' . implode(',', array_keys(self::MODELS)), 'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/']],
                fn (array $a, McpContext $ctx) => self::optionSet($a, $ctx)),

            McpTool::make('server_instance_upsert', 'admin',
                'Crée ou modifie un serveur / une instance du launcher (nom, adresse, slug d\'instance, version Minecraft, loader, dossier du modpack, couleur). '
                . 'Sans server_id : création (server_name, server_ip, server_port obligatoires). Avec server_id : seuls les champs fournis changent. Pas de suppression ni d\'icône.',
                [
                    'server_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Identifiant existant (modification).'],
                    'server_name' => ['type' => 'string', 'maxLength' => 255],
                    'server_ip' => ['type' => 'string', 'maxLength' => 253, 'description' => 'Nom d\'hôte ou IPv4 (sans schéma ni port).'],
                    'server_port' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 65535],
                    'instance_slug' => ['type' => 'string', 'maxLength' => 64, 'description' => 'Slug [a-z0-9_-] envoyé par le launcher (?instance=).'],
                    'type' => ['type' => 'string', 'maxLength' => 32],
                    'minecraft_version' => ['type' => 'string', 'maxLength' => 32],
                    'loader_type' => ['type' => 'string', 'enum' => self::LOADERS],
                    'loader_build_version' => ['type' => 'string', 'maxLength' => 64],
                    'loader_activation' => ['type' => 'boolean'],
                    'data_folder' => ['type' => 'string', 'maxLength' => 64, 'description' => 'Sous-dossier du modpack [a-z0-9_-].'],
                    'theme_color' => ['type' => 'string', 'description' => 'Couleur #rrggbb.'],
                ], [],
                [
                    'server_id' => 'sometimes|integer|min:1',
                    'server_name' => 'sometimes|string|min:1|max:255',
                    'server_ip' => ['sometimes', 'string', 'max:253', 'regex:/^[A-Za-z0-9.\-]+$/'],
                    'server_port' => 'sometimes|integer|min:1|max:65535',
                    'instance_slug' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
                    'type' => ['sometimes', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/'],
                    'minecraft_version' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9._\-]+$/'],
                    'loader_type' => ['sometimes', 'nullable', Rule::in(self::LOADERS)],
                    'loader_build_version' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
                    'loader_activation' => 'sometimes|nullable|boolean',
                    'data_folder' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
                    'theme_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                ],
                fn (array $a, McpContext $ctx) => self::serverUpsert($a, $ctx)),

            McpTool::make('user_role_set', 'admin',
                'Change le rôle d\'un compte du panel. Rétrogradation vers « moderator » uniquement : l\'élévation à « superadmin » est REFUSÉE par MCP, '
                . 'comme la rétrogradation du dernier superadmin.',
                [
                    'user_id' => ['type' => 'integer', 'minimum' => 1],
                    'role' => ['type' => 'string', 'enum' => ['moderator', 'superadmin'], 'description' => 'Seul « moderator » est accepté.'],
                ], ['user_id', 'role'],
                ['user_id' => 'required|integer|min:1', 'role' => 'required|in:moderator,superadmin'],
                function (array $a, McpContext $ctx) {
                    if ($a['role'] === 'superadmin') {
                        throw new McpToolError('Élévation à superadmin interdite par MCP : à faire depuis le panel, connecté.');
                    }
                    $u = User::find($a['user_id']) ?? throw new McpToolError('Compte introuvable.');
                    if ($u->role === 'moderator') {
                        return ['id' => $u->id, 'name' => $u->name, 'role' => 'moderator', 'changed' => false];
                    }
                    $isSuper = $u->isSuperAdmin();
                    if ($isSuper && User::where('is_admin', true)->where(fn ($q) => $q->whereNull('role')->orWhere('role', '!=', 'moderator'))->count() <= 1) {
                        throw new McpToolError('Refusé : dernier superadmin du panel.');
                    }
                    $before = $u->role ?: 'admin';
                    $u->update(['role' => 'moderator', 'is_admin' => true]);
                    $ctx->audit('user.role.update', $u, ['role' => $before], ['role' => 'moderator']);

                    return ['id' => $u->id, 'name' => $u->name, 'role' => 'moderator', 'changed' => true];
                }, true),

            McpTool::make('notification_delete', 'admin',
                'Supprime définitivement UNE annonce du launcher (irréversible).',
                ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], ['id' => 'required|integer|min:1'],
                function (array $a, McpContext $ctx) {
                    $n = OptionsNotification::find($a['id']) ?? throw new McpToolError('Annonce introuvable.');
                    $ctx->audit('notification.delete', $n, ['type' => $n->type, 'message' => $n->message, 'active' => (bool) $n->active]);
                    $n->delete();

                    return ['deleted' => true, 'id' => $a['id']];
                }, true),

            McpTool::make('notification_purge', 'admin',
                'Supprime en masse des annonces du launcher : « expired » (expirées), « inactive » (désactivées) ou « all » (toutes). Irréversible.',
                ['scope' => ['type' => 'string', 'enum' => ['expired', 'inactive', 'all']]], ['scope'],
                ['scope' => 'required|in:expired,inactive,all'],
                function (array $a, McpContext $ctx) {
                    $q = OptionsNotification::query();
                    match ($a['scope']) {
                        'expired'  => $q->whereNotNull('expires_at')->where('expires_at', '<=', now()),
                        'inactive' => $q->where('active', false),
                        default    => null,
                    };
                    $n = $q->count();
                    $q->delete();
                    $ctx->audit('notification.purge', null, [], ['scope' => $a['scope'], 'deleted' => $n]);

                    return ['scope' => $a['scope'], 'deleted' => $n];
                }, true),
        ];
    }

    private static function optionSet(array $a, McpContext $ctx): array
    {
        $rules = self::optionRules()[$a['section']];
        $key = $a['key'];
        if (! array_key_exists($key, $rules)) {
            throw new McpToolError("Réglage « {$key} » non modifiable par MCP (inconnu ou sensible). Clés de la section {$a['section']} : " . implode(', ', array_keys($rules)) . '.');
        }
        if (! array_key_exists('value', $a)) {
            throw new McpToolError('Le champ « value » est obligatoire (null pour vider un champ optionnel).');
        }
        $v = Validator::make(['value' => $a['value']], ['value' => $rules[$key]]);
        if ($v->fails()) {
            throw new McpToolError("Valeur invalide pour {$key} : " . $v->errors()->first('value'));
        }
        $value = $a['value'];
        if (is_string($rules[$key]) && str_contains($rules[$key], 'boolean')) {
            $value = (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_string($rules[$key]) && str_contains($rules[$key], 'integer')) {
            $value = (int) $value;
        }

        $model = (self::MODELS[$a['section']])::first();
        if (! $model) {
            throw new McpToolError("Section « {$a['section']} » non initialisée : ouvrez-la une fois dans le panel.");
        }
        if ($a['section'] === 'general' && in_array($key, ['min_ram', 'max_ram'], true)) {
            $min = $key === 'min_ram' ? $value : (int) $model->min_ram;
            $max = $key === 'max_ram' ? $value : (int) $model->max_ram;
            if ($min > $max) {
                throw new McpToolError('min_ram ne peut pas dépasser max_ram.');
            }
        }

        $before = $model->getAttribute($key);
        $model->forceFill([$key => $value])->save();
        $ctx->audit('option.set', $model, [$a['section'] . '.' . $key => $before], [$a['section'] . '.' . $key => $model->getAttribute($key)], self::DISCORD[$a['section']] ?? null);

        return ['section' => $a['section'], 'key' => $key, 'before' => $before, 'after' => $model->getAttribute($key)];
    }

    private static function serverUpsert(array $a, McpContext $ctx): array
    {
        $fields = array_diff_key($a, ['server_id' => 1]);
        if (isset($fields['instance_slug']) && $fields['instance_slug'] !== null) {
            $clash = OptionsServer::where('instance_slug', $fields['instance_slug'])
                ->when(isset($a['server_id']), fn ($q) => $q->where('server_id', '!=', $a['server_id']))->exists();
            if ($clash) {
                throw new McpToolError('Ce slug d\'instance est déjà utilisé.');
            }
        }
        if (isset($fields['server_port'])) {
            $fields['server_port'] = (string) $fields['server_port'];
        }

        if (isset($a['server_id'])) {
            $s = OptionsServer::where('server_id', $a['server_id'])->first() ?? throw new McpToolError('Serveur introuvable.');
            if ($fields === []) {
                throw new McpToolError('Aucun champ à modifier.');
            }
            $before = array_intersect_key($s->only(array_keys($fields)), $fields);
            $s->update($fields);
            $ctx->audit('server.edit', $s, $before, $fields, 'server.edit');
            $created = false;
        } else {
            foreach (['server_name', 'server_ip', 'server_port'] as $req) {
                if (! isset($fields[$req])) {
                    throw new McpToolError("Création d'un serveur : « {$req} » est obligatoire.");
                }
            }
            $next = max(1000, (int) (OptionsServer::max('server_id') ?? 0) + 1);
            $s = OptionsServer::create($fields + ['server_id' => $next, 'type' => 'minecraft', 'is_default' => ! OptionsServer::exists()]);
            $ctx->audit('server.add', $s, [], $fields, 'server.add');
            $created = true;
        }

        return ['created' => $created, 'server_id' => $s->server_id, 'name' => $s->server_name, 'instance_slug' => $s->instance_slug];
    }
}
