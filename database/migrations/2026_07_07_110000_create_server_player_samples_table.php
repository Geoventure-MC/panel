<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_player_samples', function (Blueprint $table) {
            $table->id();
            $table->string('server_key');
            $table->unsignedInteger('players');
            $table->timestamp('sampled_at');

            $table->index(['server_key', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_player_samples');
    }
};
