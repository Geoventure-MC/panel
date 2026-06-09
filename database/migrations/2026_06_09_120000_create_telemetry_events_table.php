<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->string('event')->index();            // launch | login | crash | ...
            $table->string('server_id')->nullable()->index();
            $table->string('launcher_version')->nullable();
            $table->string('os')->nullable();
            $table->string('ip_hash')->nullable();        // hash de l'IP (anonymisé, pour compter les uniques)
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_events');
    }
};
