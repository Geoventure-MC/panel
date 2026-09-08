<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connexion unique : le panel délègue son authentification au site.
 *
 * Les comptes locaux existants ne bougent pas — le mot de passe reste une
 * porte de secours si le site est indisponible. Un compte est simplement
 * « lié » au site par son identifiant distant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'sso_id')) {
                // Identifiant du compte côté site. Unique : deux comptes
                // panel ne peuvent pas revendiquer le même joueur.
                $table->unsignedBigInteger('sso_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('users', 'sso_uuid')) {
                $table->string('sso_uuid', 36)->nullable()->after('sso_id');
            }
            if (!Schema::hasColumn('users', 'sso_linked_at')) {
                $table->timestamp('sso_linked_at')->nullable()->after('sso_uuid');
            }
        });

        Schema::table('options_general', function (Blueprint $table) {
            if (!Schema::hasColumn('options_general', 'sso_enabled')) {
                $table->boolean('sso_enabled')->default(false);
            }
            if (!Schema::hasColumn('options_general', 'sso_url')) {
                $table->string('sso_url')->nullable();
            }
            if (!Schema::hasColumn('options_general', 'sso_client_id')) {
                $table->string('sso_client_id')->nullable();
            }
            if (!Schema::hasColumn('options_general', 'sso_client_secret')) {
                $table->text('sso_client_secret')->nullable();
            }
            if (!Schema::hasColumn('options_general', 'sso_auto_provision')) {
                // Créer automatiquement le compte panel au premier passage.
                $table->boolean('sso_auto_provision')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['sso_id', 'sso_uuid', 'sso_linked_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('options_general', function (Blueprint $table) {
            foreach (['sso_enabled', 'sso_url', 'sso_client_id', 'sso_client_secret', 'sso_auto_provision'] as $column) {
                if (Schema::hasColumn('options_general', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
