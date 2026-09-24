<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed des succès « raids, Lune, voyage, conduite, recherche » débloqués par
 * le plugin GeoFactions (AdventureManager) : raids de boss, Colosse Sélène
 * (boss lunaire), biomes découverts, kilomètres au volant, recherches du pays.
 *
 * Les codes DOIVENT rester identiques à AdventureManager.ACHIEVEMENTS (Pluginmc).
 * Idempotent : updateOrInsert sur `code`.
 */
return new class extends Migration
{
    private array $achievements = [
        ['code' => 'adv_boss', 'name' => 'Tueur de titans', 'description' => "Participe à la chute d'un boss de raid.",
            'icon' => 'bi-lightning-charge-fill', 'points' => 20],
        ['code' => 'adv_boss_10', 'name' => 'Légende des raids', 'description' => 'Participe à la chute de 10 boss.',
            'icon' => 'bi-trophy-fill', 'points' => 60],
        ['code' => 'adv_lunar_boss', 'name' => 'Vainqueur de Sélène', 'description' => 'Abats le Colosse Sélène, sur la Lune.',
            'icon' => 'bi-moon-stars-fill', 'points' => 80],
        ['code' => 'adv_biomes_10', 'name' => 'Voyageur', 'description' => 'Découvre 10 biomes.',
            'icon' => 'bi-globe-europe-africa', 'points' => 15],
        ['code' => 'adv_biomes_30', 'name' => 'Globe-trotteur', 'description' => 'Découvre 30 biomes.',
            'icon' => 'bi-globe2', 'points' => 40],
        ['code' => 'adv_driver_10', 'name' => 'Routier', 'description' => "Parcours 10 km au volant d'un engin.",
            'icon' => 'bi-truck', 'points' => 10],
        ['code' => 'adv_driver_100', 'name' => 'Pilote chevronné', 'description' => "Parcours 100 km au volant d'un engin.",
            'icon' => 'bi-speedometer2', 'points' => 40],
        ['code' => 'adv_research_5', 'name' => 'Chercheur', 'description' => 'Ton pays débloque 5 recherches.',
            'icon' => 'bi-lightbulb-fill', 'points' => 10],
        ['code' => 'adv_research_20', 'name' => 'Savant', 'description' => 'Ton pays débloque 20 recherches.',
            'icon' => 'bi-mortarboard-fill', 'points' => 30],
        ['code' => 'adv_research_all', 'name' => 'Encyclopédie vivante', 'description' => "Ton pays débloque tout l'arbre.",
            'icon' => 'bi-diagram-3-fill', 'points' => 100],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->achievements as $a) {
            $exists = DB::table('achievements')->where('code', $a['code'])->exists();

            DB::table('achievements')->updateOrInsert(
                ['code' => $a['code']],
                [
                    'name'            => $a['name'],
                    'description'     => $a['description'],
                    'icon'            => $a['icon'],
                    'points'          => $a['points'],
                    'category'        => 'Aventure',
                    'condition_type'  => 'manual',
                    'condition_value' => null,
                    'active'          => true,
                    'updated_at'      => $now,
                ] + ($exists ? [] : ['created_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('achievements')
            ->whereIn('code', array_column($this->achievements, 'code'))
            ->delete();
    }
};
