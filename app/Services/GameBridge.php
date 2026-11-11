<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Pont web → jeu : dépôt d'une commande dans gf_web_commands (connexion `game`).
 *
 * Le plugin la réserve (pending → running) puis la marque done / failed. La colonne
 * `request_id` (VARCHAR(64) NULL, index UNIQUE, ajoutée par le plugin au démarrage) sert
 * d'idempotence côté émetteur : un second INSERT avec le même identifiant est refusé par la
 * base, donc un double clic ou un rejeu ne verse pas deux fois. Si la colonne n'existe pas
 * encore (base ancienne, plugin pas redémarré), la commande est insérée sans idempotence.
 */
class GameBridge
{
    public static function table(): string
    {
        return config('geoventure.game_table_prefix', 'gf_') . 'web_commands';
    }

    public static function configured(): bool
    {
        return (string) config('database.connections.game.database') !== '';
    }

    /**
     * Dépose UNE commande. Renvoie `queued` ou `duplicate` (request_id déjà déposé) ;
     * laisse remonter l'exception si la base du jeu est injoignable.
     *
     * @return 'queued'|'duplicate'
     */
    public static function queue(string $type, string $target, int $amount = 0, ?string $requestId = null): string
    {
        $row = ['type' => $type, 'target' => mb_substr($target, 0, 255), 'amount' => $amount, 'status' => 'pending'];

        if ($requestId !== null && $requestId !== '') {
            try {
                DB::connection('game')->table(self::table())->insert($row + ['request_id' => mb_substr($requestId, 0, 64)]);

                return 'queued';
            } catch (QueryException $e) {
                if (self::isDuplicate($e)) {
                    return 'duplicate';
                }
                if (! self::isUnknownColumn($e)) {
                    throw $e;
                }
                // Colonne request_id absente : on retombe sur l'insertion classique ci-dessous.
            }
        }

        DB::connection('game')->table(self::table())->insert($row);

        return 'queued';
    }

    private static function isDuplicate(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'Duplicate entry') || str_contains($msg, 'UNIQUE constraint failed');
    }

    private static function isUnknownColumn(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'Unknown column') || str_contains($msg, 'no column named') || str_contains($msg, 'has no column');
    }
}
