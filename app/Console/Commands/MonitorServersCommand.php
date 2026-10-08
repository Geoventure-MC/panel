<?php

namespace App\Console\Commands;

use App\Services\ServerMonitor;
use Illuminate\Console\Command;

class MonitorServersCommand extends Command
{
    protected $signature = 'geo:monitor';
    protected $description = 'Sonde les serveurs de jeu (SLP), historise, détecte les incidents et alerte (Discord + audit)';

    public function handle(ServerMonitor $monitor): int
    {
        try {
            $summary = $monitor->run();
            foreach ($summary as $row) {
                $this->line(sprintf('%s : %s%s%s', $row['server'], $row['online'] ? 'en ligne' : 'HORS LIGNE',
                    $row['players'] !== null ? ' · ' . $row['players'] . ' joueur(s)' : '',
                    $row['latency'] !== null ? ' · ' . $row['latency'] . ' ms' : ''));
            }
        } catch (\Throwable $e) {
            $this->warn('geo:monitor : ' . $e->getMessage());
        }

        // Jamais d'échec : une supervision qui plante ne doit pas polluer le cron.
        return self::SUCCESS;
    }
}
