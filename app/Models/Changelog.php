<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Changelog extends Model
{
    use HasFactory;

    protected $table = 'changelogs';
    protected $fillable = ['version', 'title', 'body', 'image_url', 'active', 'published_at'];
    protected $casts = [
        'active'       => 'boolean',
        'published_at' => 'datetime',
    ];
}
