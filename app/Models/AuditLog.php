<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $fillable = ['user_id', 'action', 'target', 'changes', 'ip'];
    protected $casts = ['changes' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?object $target = null, array $changes = []): void
    {
        $targetLabel = $target ? get_class($target) . '#' . ($target->getKey() ?? '?') : null;

        static::create([
            'user_id' => Auth::id(),
            'action'  => $action,
            'target'  => $targetLabel,
            'changes' => $changes ?: null,
            'ip'      => request()->ip(),
        ]);

        \App\Services\DiscordWebhook::notify($action, $targetLabel, Auth::user()?->name);
    }
}
