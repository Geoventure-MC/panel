<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('server_status_history')) {
            return;
        }

        Schema::create('server_status_history', function (Blueprint $table) {
            $table->id();
            $table->string('server_ip', 191);
            $table->unsignedInteger('server_port');
            $table->boolean('online');
            $table->unsignedInteger('latency')->nullable();
            $table->unsignedInteger('players')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['server_ip', 'server_port', 'created_at'], 'idx_server_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_status_history');
    }
};
