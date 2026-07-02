<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledEvent extends Model
{
    use HasFactory;

    public const TYPES = ['airdrop', 'invasion', 'dungeon_raid', 'convoy_ambush', 'bonus_xp', 'announce'];
    public const RECURRINGS = ['none', 'daily', 'weekly'];

    protected $table = 'scheduled_events';
    protected $fillable = [
        'type', 'title', 'description', 'scheduled_at', 'recurring', 'status', 'fired_at',
    ];
    protected $casts = [
        'scheduled_at' => 'datetime',
        'fired_at'     => 'datetime',
    ];
}
