<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supervision du serveur de jeu (idempotente : chaque table n'est créée que
 * si elle n'existe pas déjà).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('server_checks')) {
            Schema::create('server_checks', function (Blueprint $table) {
                $table->id();
                $table->string('server_key', 191);
                $table->boolean('online');
                $table->unsignedInteger('players')->nullable();
                $table->unsignedInteger('max_players')->nullable();
                $table->unsignedInteger('latency')->nullable();
                $table->string('version', 191)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['server_key', 'created_at'], 'idx_checks_server_time');
                $table->index('created_at', 'idx_checks_time');
            });
        }

        if (! Schema::hasTable('server_incidents')) {
            Schema::create('server_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('server_key', 191);
                $table->string('server_name', 191)->nullable();
                $table->string('type', 20); // offline | latency | empty
                $table->string('detail', 255)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('last_notified_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['server_key', 'type', 'resolved_at'], 'idx_incidents_open');
            });
        }

        if (! Schema::hasTable('options_monitoring')) {
            Schema::create('options_monitoring', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->unsignedSmallInteger('offline_after_minutes')->default(3);
                $table->unsignedSmallInteger('latency_threshold_ms')->default(800);
                $table->unsignedSmallInteger('latency_after_minutes')->default(5);
                $table->unsignedSmallInteger('empty_after_minutes')->default(120); // 0 = désactivé
                $table->unsignedSmallInteger('reminder_minutes')->default(30);
                $table->string('webhook_url', 500)->nullable(); // vide = webhook général
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('options_monitoring');
        Schema::dropIfExists('server_incidents');
        Schema::dropIfExists('server_checks');
    }
};
