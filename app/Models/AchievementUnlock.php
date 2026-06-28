<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AchievementUnlock extends Model
{
    use HasFactory;

    protected $table = 'achievement_unlocks';
    protected $fillable = [
        'player', 'code', 'unlocked_at',
    ];
    protected $casts = [
        'unlocked_at' => 'datetime',
    ];
}
