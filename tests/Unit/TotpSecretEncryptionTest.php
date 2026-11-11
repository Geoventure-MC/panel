<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TotpSecretEncryptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clé jetable de test : aucun .env n'est requis pour lancer PHPUnit.
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'app.cipher' => 'AES-256-CBC']);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    public function test_le_secret_est_chiffre_dans_les_attributs_et_relu_en_clair(): void
    {
        $plain = Totp::generateSecret();
        $user = new User();
        $user->totp_secret = $plain;

        $stored = $user->getAttributes()['totp_secret'];
        $this->assertNotSame($plain, $stored);
        $this->assertStringNotContainsString($plain, $stored);
        $this->assertSame($plain, Crypt::decryptString($stored));
        $this->assertSame($plain, $user->totp_secret);
    }

    public function test_le_secret_n_est_pas_serialise(): void
    {
        $user = new User();
        $user->totp_secret = Totp::generateSecret();

        $this->assertArrayNotHasKey('totp_secret', $user->toArray());
    }

    public function test_la_verification_totp_fonctionne_avec_le_secret_chiffre(): void
    {
        $plain = Totp::generateSecret();
        $user = (new User())->setRawAttributes(['totp_secret' => Crypt::encryptString($plain)]);

        $this->assertTrue(Totp::verify($user->totp_secret, Totp::now($plain)));
    }

    public function test_la_migration_chiffre_sans_perte_est_rejouable_et_reversible(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite indisponible : migration non rejouable en mémoire.');
        }

        config([
            'database.connections.totp_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'database.default' => 'totp_test',
        ]);
        DB::purge('totp_test');

        Schema::create('users', function ($t) {
            $t->id();
            $t->string('totp_secret', 64)->nullable();
        });

        $plain = 'JBSWY3DPEHPK3PXP';
        $already = Crypt::encryptString('KKKKKKKKKKKKKKKK');
        DB::table('users')->insert([
            ['id' => 1, 'totp_secret' => $plain],
            ['id' => 2, 'totp_secret' => null],
            ['id' => 3, 'totp_secret' => $already],
        ]);

        $migration = require base_path('database/migrations/2026_11_11_100000_encrypt_totp_secrets_on_users.php');

        $migration->up();
        $migration->up(); // rejouable : ne double-chiffre rien

        $raw = DB::table('users')->orderBy('id')->pluck('totp_secret', 'id')->all();
        $this->assertNotSame($plain, $raw[1]);
        $this->assertSame($plain, Crypt::decryptString($raw[1]));
        $this->assertNull($raw[2]);
        $this->assertSame($already, $raw[3]);

        // Un compte déjà configuré continue de valider ses codes après migration.
        $user = (new User())->setRawAttributes(['totp_secret' => $raw[1]]);
        $this->assertTrue(Totp::verify($user->totp_secret, Totp::now($plain)));

        $migration->down();

        $back = DB::table('users')->orderBy('id')->pluck('totp_secret', 'id')->all();
        $this->assertSame($plain, $back[1]);
        $this->assertNull($back[2]);
        $this->assertSame('KKKKKKKKKKKKKKKK', $back[3]);
    }
}
