<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionsMonitoring extends Model
{
    protected $table = 'options_monitoring';

    protected $fillable = [
        'enabled', 'offline_after_minutes', 'latency_threshold_ms',
        'latency_after_minutes', 'empty_after_minutes', 'reminder_minutes', 'webhook_url',
    ];

    protected $casts = [
        'enabled'               => 'boolean',
        'offline_after_minutes' => 'integer',
        'latency_threshold_ms'  => 'integer',
        'latency_after_minutes' => 'integer',
        'empty_after_minutes'   => 'integer',
        'reminder_minutes'      => 'integer',
    ];

    public const DEFAULTS = [
        'enabled' => true, 'offline_after_minutes' => 3, 'latency_threshold_ms' => 800,
        'latency_after_minutes' => 5, 'empty_after_minutes' => 120, 'reminder_minutes' => 30,
        'webhook_url' => null,
    ];

    /** Réglages courants ; valeurs par défaut (non enregistrées) si la table est absente. */
    public static function current(): self
    {
        try {
            return static::first() ?? static::create(self::DEFAULTS);
        } catch (\Throwable $e) {
            return new static(self::DEFAULTS);
        }
    }
}
