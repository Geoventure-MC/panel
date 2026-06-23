<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-instance : chaque serveur du launcher (Geoventure / Elandor / Pokeland)
 * devient une instance complète avec son propre loader, sa version Minecraft,
 * son modpack (dossier dédié) et ses mods. Le launcher route via ?instance=<slug>.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('options_server', function (Blueprint $table) {
            // Slug stable qui matche l'id d'instance envoyé par le launcher
            // (?instance=geoventure). Indépendant du server_id numérique Azuriom.
            $table->string('instance_slug')->nullable()->after('server_id');

            // Overrides loader par instance (si null → fallback config globale).
            $table->string('minecraft_version')->nullable()->after('type');
            $table->string('loader_type')->nullable()->after('minecraft_version');
            $table->string('loader_build_version')->nullable()->after('loader_type');
            $table->boolean('loader_activation')->nullable()->after('loader_build_version');

            // Sous-dossier du modpack sous storage/app/public/data/ (défaut = slug).
            $table->string('data_folder')->nullable()->after('loader_activation');
        });
    }

    public function down(): void
    {
        Schema::table('options_server', function (Blueprint $table) {
            $table->dropColumn([
                'instance_slug',
                'minecraft_version',
                'loader_type',
                'loader_build_version',
                'loader_activation',
                'data_folder',
            ]);
        });
    }
};
