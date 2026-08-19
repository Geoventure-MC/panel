<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * URL publique de la carte du monde (Dynmap).
 *
 * Elle était jusqu'ici codée en dur dans la page du site et introuvable depuis
 * le launcher, alors que la carte est déjà en ligne sur le serveur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('options_general', function (Blueprint $table) {
            $table->string('map_url')->nullable()->after('azuriom_url');
        });
    }

    public function down(): void
    {
        Schema::table('options_general', function (Blueprint $table) {
            $table->dropColumn('map_url');
        });
    }
};
