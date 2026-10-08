<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Clé d'accès à l'API MCP. Seul le hash SHA-256 est conservé. */
class McpKey extends Model
{
    public const SCOPES = ['read', 'write', 'admin'];

    protected $table = 'mcp_keys';
    protected $fillable = [
        'name', 'key_hash', 'key_prefix', 'scope', 'allowed_tools', 'allowed_ips', 'created_by',
        'expires_at', 'last_used_at', 'last_used_ip_hash', 'revoked_at',
    ];
    protected $hidden = ['key_hash', 'last_used_ip_hash'];
    protected $casts = [
        'allowed_tools' => 'array',
        'allowed_ips'   => 'array',
        'expires_at'    => 'datetime',
        'last_used_at'  => 'datetime',
        'revoked_at'    => 'datetime',
    ];

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Génère une clé `gmcp_` + 40 caractères aléatoires. */
    public static function generate(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        for ($i = 0; $i < 40; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'gmcp_' . $out;
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function status(): string
    {
        if ($this->revoked_at) {
            return 'revoked';
        }

        return $this->expires_at && $this->expires_at->isPast() ? 'expired' : 'active';
    }

    /** Niveau numérique de la portée : read 1 < write 2 < admin 3. */
    public function level(): int
    {
        return ['read' => 1, 'write' => 2, 'admin' => 3][$this->scope] ?? 0;
    }
}
