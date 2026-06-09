<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemetryEvent extends Model
{
    use HasFactory;

    protected $table = 'telemetry_events';
    protected $fillable = [
        'event',
        'server_id',
        'launcher_version',
        'os',
        'ip_hash',
    ];
}
