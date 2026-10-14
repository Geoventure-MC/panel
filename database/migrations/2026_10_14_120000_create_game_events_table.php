<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Événements de jeu pilotés par le panel (Halloween…). Idempotente (hasTable).
 * Le plugin GeoFactions interroge GET /utils/game-events et applique dates + réglages.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('game_events')) {
            Schema::create('game_events', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 32)->unique();
                $table->string('name', 60);
                $table->boolean('enabled')->default(false);
                $table->timestamp('opens_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('announce')->default(true);
                $table->unsignedBigInteger('notification_id')->nullable();
                $table->timestamps();
            });
        }

        if (! DB::table('game_events')->where('slug', 'halloween')->exists()) {
            DB::table('game_events')->insert([
                'slug' => 'halloween', 'name' => 'Halloween', 'enabled' => true,
                'opens_at' => '2026-10-17 16:00:00', 'ends_at' => '2026-11-05 22:59:00',
                'settings' => json_encode(['mob_cap' => 120, 'mob_local' => 30, 'boss_respawn_minutes' => 20, 'flight_minutes' => 10, 'rewards' => [5000, 3000, 1500, 500]]),
                'announce' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
