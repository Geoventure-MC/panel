<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Succès secrets et à niveaux (plugin : AchievementService, catalogue
 * achievement-catalog.yml). Idempotente : chaque colonne n'est ajoutée que si
 * elle manque (`points` et `category` existent depuis la création de la table).
 *
 * - secret    : masqué (« ??? ») tant que le joueur ne l'a pas débloqué
 * - max_level : nombre de niveaux (1 = succès simple, jusqu'à 5 : I à V)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            if (!Schema::hasColumn('achievements', 'secret')) {
                $table->boolean('secret')->default(false);
            }
            if (!Schema::hasColumn('achievements', 'max_level')) {
                $table->unsignedTinyInteger('max_level')->default(1);
            }
            if (!Schema::hasColumn('achievements', 'points')) {
                $table->unsignedInteger('points')->default(10);
            }
            if (!Schema::hasColumn('achievements', 'category')) {
                $table->string('category')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['secret', 'max_level'] as $col) {
            if (Schema::hasColumn('achievements', $col)) {
                Schema::table('achievements', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
