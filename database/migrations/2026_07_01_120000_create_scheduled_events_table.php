<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_events', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // airdrop | invasion | dungeon_raid | convoy_ambush | bonus_xp | announce
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('recurring')->default('none'); // none | daily | weekly
            $table->string('status')->default('pending'); // pending | fired | cancelled
            $table->timestamp('fired_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_events');
    }
};
