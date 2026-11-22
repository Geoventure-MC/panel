<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LauncherDiagnostic extends Model
{
    protected $table = 'launcher_diagnostics';

    protected $fillable = ['launcher_version', 'instance', 'os', 'summary', 'report', 'ip_hash', 'handled'];

    protected $casts = ['handled' => 'boolean'];

    /** Rapports gardés 30 jours. */
    public const KEEP_DAYS = 30;
}
