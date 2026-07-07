<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerPlayerSample extends Model
{
    protected $table = 'server_player_samples';
    public $timestamps = false;

    protected $fillable = ['server_key', 'players', 'sampled_at'];
    protected $casts = [
        'players'    => 'integer',
        'sampled_at' => 'datetime',
    ];
}
