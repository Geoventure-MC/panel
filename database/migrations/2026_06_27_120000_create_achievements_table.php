<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // nom d'icône Bootstrap (ex: bi-trophy) OU url
            $table->unsignedInteger('points')->default(10);
            $table->string('category')->nullable();
            // Vocabulaire partagé avec le launcher (NE PAS modifier sans synchroniser) :
            // first_launch | launch_count | playtime_hours | instances_tried | manual
            $table->string('condition_type')->default('manual');
            $table->integer('condition_value')->nullable(); // seuil N pour les conditions à compteur
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Quelques succès d'exemple (peuvent être modifiés/supprimés via l'admin).
        $now = now();
        DB::table('achievements')->insert([
            [
                'code' => 'first_launch', 'name' => 'Première partie',
                'description' => 'Lancer le jeu pour la première fois.',
                'icon' => 'bi-rocket-takeoff', 'points' => 10, 'category' => 'Débuts',
                'condition_type' => 'first_launch', 'condition_value' => null,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'regular', 'name' => 'Habitué',
                'description' => 'Lancer le jeu 10 fois.',
                'icon' => 'bi-arrow-repeat', 'points' => 20, 'category' => 'Assiduité',
                'condition_type' => 'launch_count', 'condition_value' => 10,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'veteran', 'name' => 'Vétéran',
                'description' => 'Cumuler 50 heures de jeu.',
                'icon' => 'bi-hourglass-split', 'points' => 50, 'category' => 'Assiduité',
                'condition_type' => 'playtime_hours', 'condition_value' => 50,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'explorer', 'name' => 'Explorateur',
                'description' => 'Essayer 3 instances différentes.',
                'icon' => 'bi-compass', 'points' => 30, 'category' => 'Découverte',
                'condition_type' => 'instances_tried', 'condition_value' => 3,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
