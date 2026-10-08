<?php

namespace App\Services\Mcp;

use App\Models\AuditLog;
use App\Models\McpKey;
use App\Services\DiscordWebhook;
use Illuminate\Http\Request;

/** Contexte d'un appel d'outil : clé appelante, requête, journal d'audit. */
class McpContext
{
    public function __construct(public McpKey $key, public Request $request)
    {
    }

    public function actor(): string
    {
        return 'MCP:' . $this->key->name;
    }

    /**
     * Écrit dans le journal d'audit (acteur « MCP:<clé> »). `before`/`after` sont
     * purgés de tout secret. `discord` = action « critique » à relayer au webhook Discord.
     */
    public function audit(string $action, ?object $target = null, array $before = [], array $after = [], ?string $discord = null): void
    {
        $label = $target ? get_class($target) . '#' . ($target->getKey() ?? '?') : null;
        $changes = array_filter([
            'actor'  => $this->actor(),
            'tool'   => $this->tool ?? null,
            'before' => $before ?: null,
            'after'  => $after ?: null,
        ], fn ($v) => $v !== null);

        AuditLog::create([
            'user_id' => null,
            'action'  => 'mcp.' . $action,
            'target'  => $label,
            'changes' => McpSanitizer::scrub($changes),
            'ip'      => $this->request->ip(),
        ]);

        if ($discord) {
            DiscordWebhook::notify($discord, $label, $this->actor());
        }
    }

    public ?string $tool = null;
}
