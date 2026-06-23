<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mods par instance : un mod optionnel peut être réservé à une instance
 * (instance = slug) ou partagé entre toutes (instance = null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->string('instance')->nullable()->after('recommended');
        });
    }

    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->dropColumn('instance');
        });
    }
};
