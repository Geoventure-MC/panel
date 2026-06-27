<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasFactory;

    protected $table = 'achievements';
    protected $fillable = [
        'code', 'name', 'description', 'icon', 'points',
        'category', 'condition_type', 'condition_value', 'active',
    ];
    protected $casts = [
        'active'          => 'boolean',
        'points'          => 'integer',
        'condition_value' => 'integer',
    ];
}
