<?php

namespace App\Services\Mcp;

use App\Models\McpCall;
use App\Models\McpKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Moteur JSON-RPC 2.0 du MCP (transport HTTP « streamable », réponses application/json).
 * Méthodes : initialize, notifications/initialized, ping, tools/list, tools/call.
 */
class McpServer
{
    public const SUPPORTED_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public const E_PARSE = -32700;
    public const E_INVALID = -32600;
    public const E_METHOD = -32601;
    public const E_PARAMS = -32602;
    public const E_INTERNAL = -32603;

    public const INSTRUCTIONS = "Panel d'administration Geoventure (launcher Nexus, serveurs de jeu). "
        . "Cet endpoint permet de consulter et de piloter le panel à distance. "
        . "Les outils de LECTURE sont sans effet. Les outils d'ÉCRITURE (portée write) et d'ADMINISTRATION (portée admin) "
        . "modifient réellement le launcher ou le jeu : ils exigent confirm=true et sont tous consignés dans le journal d'audit "
        . "sous « MCP:<nom de la clé> ». Aucun outil ne renvoie ni n'accepte de secret (mots de passe, jetons, clés API, "
        . "webhooks) : les champs sensibles apparaissent masqués (••••). "
        . "Avant d'écrire, lisez l'état actuel (options_get, notifications_list, launcher_config_preview…). "
        . "Les commandes de jeu (game_command_send) agissent sur le serveur en production : exigez un motif clair, respectez les plafonds. "
        . "Si un outil n'apparaît pas dans la liste, la portée de la clé ne le permet pas.";

    /** Traite un message (ou un lot). Renvoie null s'il n'y a rien à répondre (notifications seules). */
    public function handleBody(mixed $decoded, McpKey $key, Request $request): ?array
    {
        if (is_array($decoded) && array_is_list($decoded) && $decoded !== []) {
            $responses = [];
            foreach ($decoded as $msg) {
                $r = $this->handleMessage($msg, $key, $request);
                if ($r !== null) {
                    $responses[] = $r;
                }
            }

            return $responses ?: null;
        }

        return $this->handleMessage($decoded, $key, $request);
    }

    private function handleMessage(mixed $msg, McpKey $key, Request $request): ?array
    {
        if (! is_array($msg) || ($msg['jsonrpc'] ?? null) !== '2.0' || ! is_string($msg['method'] ?? null)) {
            // Une réponse client (result/error) n'appelle aucune réponse.
            if (is_array($msg) && (array_key_exists('result', $msg) || array_key_exists('error', $msg))) {
                return null;
            }

            return self::error($msg['id'] ?? null, self::E_INVALID, 'Requête JSON-RPC invalide.');
        }

        $isNotification = ! array_key_exists('id', $msg);
        $id = $msg['id'] ?? null;
        if (! $isNotification && ! (is_int($id) || is_string($id))) {
            return self::error(null, self::E_INVALID, 'Identifiant JSON-RPC invalide.');
        }
        $params = $msg['params'] ?? [];
        if (! is_array($params)) {
            return $isNotification ? null : self::error($id, self::E_PARAMS, 'Paramètres invalides.');
        }

        if ($isNotification) {
            return null; // notifications/initialized, notifications/cancelled… : acquittées en 202 par le contrôleur
        }

        try {
            return match ($msg['method']) {
                'initialize' => self::result($id, $this->initialize($params)),
                'ping'       => self::result($id, (object) []),
                'tools/list' => self::result($id, ['tools' => $this->listTools($key)]),
                'tools/call' => self::result($id, $this->callTool($params, $key, $request)),
                default      => self::error($id, self::E_METHOD, 'Méthode inconnue : ' . mb_substr($msg['method'], 0, 60)),
            };
        } catch (McpProtocolError $e) {
            return self::error($id, $e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[MCP] erreur interne : ' . $e->getMessage());

            return self::error($id, self::E_INTERNAL, 'Erreur interne.');
        }
    }

    private function initialize(array $params): array
    {
        $asked = is_string($params['protocolVersion'] ?? null) ? $params['protocolVersion'] : null;
        $version = in_array($asked, self::SUPPORTED_VERSIONS, true) ? $asked : config('mcp.protocol_version');

        return [
            'protocolVersion' => $version,
            'capabilities'    => ['tools' => ['listChanged' => false]],
            'serverInfo'      => ['name' => 'geoventure-panel', 'title' => 'Panel Geoventure', 'version' => (string) config('app.version', '1.0.0')],
            'instructions'    => self::INSTRUCTIONS,
        ];
    }

    /** Outils visibles pour cette clé : portée suffisante ET présents dans allowed_tools s'il est défini. */
    public static function visibleTools(McpKey $key): array
    {
        $allowed = $key->allowed_tools;
        $out = [];
        foreach (McpRegistry::all() as $name => $tool) {
            if ($tool->level() > $key->level()) {
                continue;
            }
            if (is_array($allowed) && $allowed !== [] && ! in_array($name, $allowed, true)) {
                continue;
            }
            $out[$name] = $tool;
        }

        return $out;
    }

    private function listTools(McpKey $key): array
    {
        return array_values(array_map(fn (McpTool $t) => $t->definition(), self::visibleTools($key)));
    }

    private function callTool(array $params, McpKey $key, Request $request): array
    {
        $name = $params['name'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new McpProtocolError(self::E_PARAMS, 'Nom d\'outil manquant.');
        }
        $tool = McpRegistry::all()[$name] ?? null;
        if (! $tool) {
            throw new McpProtocolError(self::E_PARAMS, 'Outil inconnu : ' . mb_substr($name, 0, 60));
        }
        $args = $params['arguments'] ?? [];
        if (! is_array($args) || ($args !== [] && array_is_list($args))) {
            throw new McpProtocolError(self::E_PARAMS, 'Les arguments doivent être un objet JSON.');
        }

        $start = microtime(true);
        $status = 'ok';
        $error = null;
        try {
            if (! isset(self::visibleTools($key)[$name])) {
                $status = 'denied';
                $error = $tool->level() > $key->level()
                    ? "Portée insuffisante : l'outil exige la portée « {$tool->scope} » (clé : « {$key->scope} »)."
                    : 'Outil non autorisé pour cette clé.';

                return self::toolError($error);
            }

            $unknown = array_diff(array_keys($args), array_keys($tool->properties), $tool->needsConfirm() ? ['confirm'] : []);
            if ($unknown) {
                $status = 'error';
                $error = 'Argument(s) inconnu(s) : ' . implode(', ', array_map(fn ($k) => mb_substr((string) $k, 0, 40), $unknown));

                return self::toolError($error);
            }

            if ($tool->needsConfirm()) {
                if (($args['confirm'] ?? false) !== true) {
                    $status = 'denied';
                    $error = 'Confirmation requise : cette action modifie le panel ou le jeu. Relancez l\'appel avec confirm=true.';

                    return self::toolError($error);
                }
                unset($args['confirm']);
            }

            $v = Validator::make($args, $tool->rules);
            if ($v->fails()) {
                $status = 'error';
                $parts = [];
                foreach ($v->failed() as $field => $rules) {
                    $parts[] = $field . ' (' . strtolower(implode(', ', array_keys($rules))) . ')';
                }
                $error = 'Arguments invalides : ' . implode(' ; ', array_slice($parts, 0, 6));

                return self::toolError($error);
            }
            // Les valeurs passées au handler sont celles validées ET connues du schéma.
            $clean = array_intersect_key($args, $tool->properties);

            $ctx = new McpContext($key, $request);
            $ctx->tool = $name;
            try {
                $data = ($tool->handler)($clean, $ctx);
            } catch (McpToolError $e) {
                $status = 'error';
                $error = $e->getMessage();

                return self::toolError($error);
            } catch (\Throwable $e) {
                Log::error("[MCP] outil {$name} : " . $e->getMessage());
                $status = 'error';
                $error = 'Erreur interne de l\'outil.';

                return self::toolError($error);
            }

            $json = json_encode(McpSanitizer::scrub($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            return ['content' => [['type' => 'text', 'text' => $json === false ? '{}' : $json]], 'isError' => false];
        } finally {
            try {
                McpCall::create([
                    'mcp_key_id'  => $key->id,
                    'key_name'    => $key->name,
                    'tool'        => mb_substr($name, 0, 64),
                    'arguments'   => McpSanitizer::forLog($args),
                    'status'      => $status,
                    'error'       => $error ? mb_substr($error, 0, 255) : null,
                    'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                    'created_at'  => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[MCP] journal des appels indisponible : ' . $e->getMessage());
            }
        }
    }

    public static function toolError(string $message): array
    {
        return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
    }

    public static function result(int|string|null $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    public static function error(int|string|null $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
