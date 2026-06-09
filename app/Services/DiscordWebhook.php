<?php

namespace App\Services;

use App\Models\OptionsGeneral;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordWebhook
{
    /** Audit actions that trigger a Discord notification. */
    public const CRITICAL_ACTIONS = [
        'server.add',
        'server.edit',
        'server.delete',
        'security.update',
        'maintenance.toggle',
        'user.create',
        'user.delete',
        'loader.update',
        'settings.import',
        'update.run',
    ];

    /**
     * Send a notification for a critical admin action.
     * Silently no-ops if no webhook is configured; never throws.
     */
    public static function notify(string $action, ?string $target = null, ?string $actor = null): void
    {
        if (!in_array($action, self::CRITICAL_ACTIONS, true)) {
            return;
        }

        $url = OptionsGeneral::first()?->discord_webhook_url;
        if (!$url) {
            return;
        }

        try {
            Http::timeout(5)->post($url, [
                'embeds' => [[
                    'title'       => '🛡️ Action admin — ' . $action,
                    'description' => trim(implode("\n", array_filter([
                        $actor ? "**Par :** {$actor}" : null,
                        $target ? "**Cible :** {$target}" : null,
                    ]))),
                    'color'     => 0x4ade80,
                    'timestamp' => now()->toIso8601String(),
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Discord webhook failed: ' . $e->getMessage());
        }
    }
}
