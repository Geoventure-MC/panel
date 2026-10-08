<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerIncident extends Model
{
    protected $table = 'server_incidents';

    protected $fillable = ['server_key', 'server_name', 'type', 'detail', 'started_at', 'last_notified_at', 'resolved_at'];

    protected $casts = [
        'started_at'       => 'datetime',
        'last_notified_at' => 'datetime',
        'resolved_at'      => 'datetime',
    ];

    /** Durée en minutes (jusqu'à maintenant si l'incident est ouvert). */
    public function durationMinutes(): int
    {
        return max(0, (int) $this->started_at->diffInMinutes($this->resolved_at ?? now()));
    }
}
