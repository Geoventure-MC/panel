<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WonderScore extends Model
{
    protected $table = 'wonder_scores';
    protected $fillable = ['wonder_team_id', 'juror', 'technical', 'aesthetic', 'theme'];
}
