<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsGeneral extends Model
{
    use HasFactory;

    protected $table = 'options_general';

    protected $fillable = [
        'mods_enabled',
        'file_verification',
        'embedded_java',
        'game_folder_name',
        'email_verified',
        'role_display',
        'money_display',
        'azuriom_url',
        'map_url',
        'azuriom_api_key',
        'discord_webhook_url',
        'min_ram',
        'max_ram',
    ];

    protected $casts = [
        'mods_enabled' => 'boolean',
        'file_verification' => 'boolean',
        'embedded_java' => 'boolean',
        'email_verified' => 'boolean',
        'role_display' => 'integer',
        'money_display' => 'integer',
        'min_ram' => 'integer',
        'max_ram' => 'integer',
    ];
}

