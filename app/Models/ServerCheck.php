<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerCheck extends Model
{
    protected $table = 'server_checks';
    public $timestamps = false;

    protected $fillable = ['server_key', 'online', 'players', 'max_players', 'latency', 'version', 'created_at'];

    protected $casts = [
        'online'     => 'boolean',
        'created_at' => 'datetime',
    ];
}
