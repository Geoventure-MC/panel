<?php

namespace Tests\Unit;

use App\Services\GameBridge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pont web → jeu : une commande renvoyée avec le même request_id ne crée qu'UNE ligne
 * dans gf_web_commands (index UNIQUE uq_request_id côté jeu). La base du jeu est simulée
 * par une base SQLite en mémoire sous la connexion `game`.
 */
class GameBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.connections.game' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'geoventure.game_table_prefix' => 'gf_',
        ]);
        DB::purge('game');
    }

    private function table(bool $withRequestId = true): void
    {
        Schema::connection('game')->create('gf_web_commands', function (Blueprint $t) use ($withRequestId) {
            $t->id();
            $t->string('type', 32);
            $t->string('target', 255);
            $t->integer('amount')->default(0);
            $t->string('status', 16)->default('pending');
            $t->text('result')->nullable();
            if ($withRequestId) {
                $t->string('request_id', 64)->nullable()->unique('uq_request_id');
            }
        });
    }

    private function lignes(): int
    {
        return DB::connection('game')->table('gf_web_commands')->count();
    }

    public function test_meme_request_id_envoye_deux_fois_donne_une_seule_ligne(): void
    {
        $this->table();

        $this->assertSame('queued', GameBridge::queue('give_coins', 'Alex', 500, 'panel-abc'));
        $this->assertSame('duplicate', GameBridge::queue('give_coins', 'Alex', 500, 'panel-abc'));

        $this->assertSame(1, $this->lignes());
        $this->assertSame('panel-abc', DB::connection('game')->table('gf_web_commands')->value('request_id'));
    }

    public function test_request_id_differents_donnent_deux_lignes(): void
    {
        $this->table();

        GameBridge::queue('give_coins', 'Alex', 500, 'panel-a');
        GameBridge::queue('give_coins', 'Alex', 500, 'panel-b');

        $this->assertSame(2, $this->lignes());
    }

    public function test_request_id_trop_long_est_tronque_a_64_caracteres_de_facon_stable(): void
    {
        $this->table();
        $long = str_repeat('x', 90);

        GameBridge::queue('give_coins', 'Alex', 1, $long);

        $this->assertSame('duplicate', GameBridge::queue('give_coins', 'Alex', 1, $long));
        $this->assertSame(1, $this->lignes());
        $this->assertSame(64, strlen(DB::connection('game')->table('gf_web_commands')->value('request_id')));
    }

    public function test_sans_request_id_chaque_envoi_depose_une_ligne(): void
    {
        $this->table();

        GameBridge::queue('broadcast', 'Bonjour');
        GameBridge::queue('broadcast', 'Bonjour');

        $this->assertSame(2, $this->lignes());
    }

    public function test_base_ancienne_sans_colonne_request_id_replie_sur_l_insertion_simple(): void
    {
        $this->table(false);

        $this->assertSame('queued', GameBridge::queue('give_coins', 'Alex', 500, 'panel-abc'));
        $this->assertSame(1, $this->lignes());
    }
}
