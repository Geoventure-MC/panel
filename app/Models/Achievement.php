<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasFactory;

    /** Vocabulaire de rareté partagé avec le mod (GeoRarity) et le plugin (AchievementService). */
    public const RARITIES = ['common', 'uncommon', 'rare', 'epic', 'legendary'];

    protected $table = 'achievements';
    protected $fillable = [
        'code', 'name', 'description', 'icon', 'points', 'rarity',
        'category', 'condition_type', 'condition_value', 'active', 'secret', 'max_level',
    ];
    protected $casts = [
        'active'          => 'boolean',
        'secret'          => 'boolean',
        'max_level'       => 'integer',
        'points'          => 'integer',
        'condition_value' => 'integer',
    ];
}
