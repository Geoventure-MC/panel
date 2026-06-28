<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievement_unlocks', function (Blueprint $table) {
            $table->id();
            $table->string('player')->index(); // pseudo Minecraft du joueur
            $table->string('code');            // code du succès (cf. table achievements)
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            // Un même succès n'est débloqué qu'une fois par joueur.
            $table->unique(['player', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_unlocks');
    }
};
