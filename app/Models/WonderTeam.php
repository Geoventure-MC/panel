<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WonderTeam extends Model
{
    protected $table = 'wonder_teams';
    protected $fillable = ['wonder_edition_id', 'name', 'color', 'builders', 'reserves', 'images'];
    protected $casts = ['builders' => 'array', 'reserves' => 'array', 'images' => 'array'];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(WonderEdition::class, 'wonder_edition_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(WonderScore::class);
    }
}
