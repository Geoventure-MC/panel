<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WonderScore extends Model
{
    /** /utils/wonder est mis en cache 30 s : toute modification admin l'invalide aussitôt. */
    protected static function booted(): void
    {
        $forget = static fn () => \Illuminate\Support\Facades\Cache::forget('geo_wonder');
        static::saved($forget);
        static::deleted($forget);
    }

    protected $table = 'wonder_scores';
    protected $fillable = ['wonder_team_id', 'juror', 'technical', 'aesthetic', 'theme'];
}
