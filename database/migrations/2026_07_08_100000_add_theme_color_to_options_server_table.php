<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thème par instance : couleur d'accent (#rrggbb) propre à chaque serveur,
     * exposée au launcher via /utils/api (theme_color + servers[].theme_color).
     * Nullable → si vide, le launcher applique ses couleurs par défaut.
     */
    public function up(): void
    {
        Schema::table('options_server', function (Blueprint $table) {
            $table->string('theme_color', 7)->nullable()->after('data_folder');
        });
    }

    public function down(): void
    {
        Schema::table('options_server', function (Blueprint $table) {
            $table->dropColumn('theme_color');
        });
    }
};
