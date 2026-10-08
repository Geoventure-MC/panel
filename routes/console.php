<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Supervision du serveur de jeu : sonde chaque minute (cron hébergement :
// `* * * * * php artisan schedule:run`).
Schedule::command('geo:monitor')->everyMinute()->withoutOverlapping(5);
