<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsLauncherContent extends Model
{
    use HasFactory;

    protected $table = 'options_launcher_content';
    protected $fillable = ['section', 'title', 'description', 'icon', 'image_url', 'url', 'sort_order', 'active'];
    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];
}
