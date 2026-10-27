<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seasons') && !Schema::hasColumn('seasons', 'podium')) {
            Schema::table('seasons', function (Blueprint $table) {
                // Podium récompensé envoyé par le plugin : [{rank,name,score,reward,title}]
                $table->json('podium')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seasons') && Schema::hasColumn('seasons', 'podium')) {
            Schema::table('seasons', function (Blueprint $table) {
                $table->dropColumn('podium');
            });
        }
    }
};
