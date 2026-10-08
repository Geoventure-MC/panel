<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Réglages clé/valeur de l'API MCP. */
class McpSetting extends Model
{
    protected $table = 'mcp_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function read(string $key, ?string $default = null): ?string
    {
        try {
            return static::query()->whereKey($key)->value('value') ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Endpoint MCP activé ? DÉSACTIVÉ par défaut (et si la table est absente). */
    public static function enabled(): bool
    {
        return static::read('enabled', '0') === '1';
    }
}
