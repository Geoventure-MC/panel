<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chiffre `users.totp_secret` au repos (cast `encrypted` du modèle User, clé APP_KEY).
 *
 * - La colonne passe de VARCHAR(64) à TEXT : une valeur chiffrée fait ~150 caractères.
 * - Les secrets existants sont chiffrés SANS perte (les comptes 2FA déjà configurés
 *   continuent de valider leurs codes). La migration est rejouable : une valeur déjà
 *   chiffrée est laissée telle quelle.
 * - Réversible : `down()` redéchiffre les valeurs puis rétrécit la colonne.
 *
 * Les lectures/écritures passent par DB::table(), donc hors cast : on ne dépend pas
 * du comportement du modèle pendant la migration.
 *
 * Attention : si APP_KEY change plus tard, ces secrets deviennent illisibles (les
 * comptes concernés devront refaire l'activation 2FA).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'totp_secret')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_secret')->nullable()->change();
        });

        DB::table('users')
            ->whereNotNull('totp_secret')
            ->where('totp_secret', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                if ($this->isEncrypted($row->totp_secret)) {
                    return;
                }

                DB::table('users')->where('id', $row->id)->update([
                    'totp_secret' => Crypt::encryptString($row->totp_secret),
                ]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'totp_secret')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('totp_secret')
            ->where('totp_secret', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                try {
                    $plain = Crypt::decryptString($row->totp_secret);
                } catch (DecryptException $e) {
                    return; // déjà en clair (ou illisible : laissé tel quel)
                }

                DB::table('users')->where('id', $row->id)->update(['totp_secret' => $plain]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('totp_secret', 64)->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException $e) {
            return false;
        }
    }
};
