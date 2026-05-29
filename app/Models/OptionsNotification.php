<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsNotification extends Model
{
    use HasFactory;

    protected $table = 'options_notifications';
    protected $fillable = ['type', 'message', 'url', 'active', 'expires_at'];
    protected $casts = [
        'active'     => 'boolean',
        'expires_at' => 'datetime',
    ];
}
