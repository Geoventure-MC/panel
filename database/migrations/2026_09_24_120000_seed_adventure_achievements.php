<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed des succès d'aventure (WorldReborn) signalés par le plugin GeoFactions.
 *
 * Le plugin les débloque en jeu (AdventureManager : repaires hostiles,
 * catastrophes, bestiaire, engins, tombe, niveau d'Aventurier) et POST
 * /utils/achievements/unlock avec le `code`. Sans ces lignes de catalogue le
 * launcher n'aurait rien à afficher.
 *
 * Les codes DOIVENT rester identiques à AdventureManager.ACHIEVEMENTS (Pluginmc).
 * Idempotent : updateOrInsert sur `code`.
 */
return new class extends Migration
{
    private array $achievements = [
        ['code' => 'adv_village_taken', 'name' => 'Conquérant', 'description' => 'Prends un repaire hostile.',
            'icon' => 'bi-flag-fill', 'points' => 20],
        ['code' => 'adv_villages_6', 'name' => 'Pacificateur', 'description' => 'Prends six repaires hostiles.',
            'icon' => 'bi-shield-fill-check', 'points' => 50],
        ['code' => 'adv_warriors_50', 'name' => 'Fléau des clans', 'description' => 'Abats 50 guerriers des factions hostiles.',
            'icon' => 'bi-crosshair', 'points' => 30],
        ['code' => 'adv_crater', 'name' => 'Chasseur de catastrophes', 'description' => "Trouve l'épicentre d'une catastrophe.",
            'icon' => 'bi-fire', 'points' => 25],
        ['code' => 'adv_capture', 'name' => 'Dompteur', 'description' => 'Capture un animal en cage.',
            'icon' => 'bi-box-seam', 'points' => 10],
        ['code' => 'adv_photo_10', 'name' => 'Photographe animalier', 'description' => 'Photographie 10 espèces.',
            'icon' => 'bi-camera-fill', 'points' => 20],
        ['code' => 'adv_bestiary', 'name' => 'Naturaliste', 'description' => "Complète la fiche d'une espèce.",
            'icon' => 'bi-journal-bookmark-fill', 'points' => 15],
        ['code' => 'adv_bestiary_10', 'name' => 'Encyclopédiste', 'description' => 'Complète la fiche de 10 espèces.',
            'icon' => 'bi-book-fill', 'points' => 40],
        ['code' => 'adv_mechanic', 'name' => 'Mécanicien', 'description' => 'Pose un module sur un engin.',
            'icon' => 'bi-wrench-adjustable', 'points' => 10],
        ['code' => 'adv_grave', 'name' => 'Revenant', 'description' => 'Récupère ta tombe.',
            'icon' => 'bi-heartbreak-fill', 'points' => 5],
        ['code' => 'adv_level_10', 'name' => 'Aventurier confirmé', 'description' => "Atteins le niveau 10 d'Aventurier.",
            'icon' => 'bi-compass-fill', 'points' => 30],
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
