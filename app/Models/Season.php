<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;

    protected $table = 'seasons';
    protected $fillable = [
        'external_id', 'name', 'starts_at', 'ends_at', 'status',
        'winner_name', 'winner_faction', 'winner_score', 'reward',
    ];
    protected $casts = [
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'winner_score' => 'integer',
    ];
}
