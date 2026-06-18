<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityMod extends Model
{
    use HasFactory;

    protected $table = 'community_mods';
    protected $fillable = ['name', 'description', 'filename', 'url', 'icon', 'active'];
    protected $casts = [
        'active' => 'boolean',
    ];
}
