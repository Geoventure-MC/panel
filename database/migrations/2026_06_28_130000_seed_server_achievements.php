<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed des 5 succès « serveur » signalés par le plugin GeoFactions.
 *
 * Ces succès sont déverrouillés côté serveur (condition_type = manual) : le
 * plugin détecte la condition en jeu et POST /utils/achievements/unlock avec le
 * `code` correspondant. Sans ces lignes de catalogue, le launcher n'a rien à
 * afficher → la feature reste inerte. Cette migration la rend clé en main.
 *
 * Idempotent : updateOrInsert sur `code` → ré-exécutable sans doublon, et sans
 * écraser les éventuelles éditions admin au-delà des champs ci-dessous.
 */
return new class extends Migration
{
    /** Les 5 succès serveur (code = ce que le plugin POST). */
    private array $achievements = [
        [
            'code' => 'faction_member', 'name' => "Membre d'une faction",
            'description' => 'Rejoins une faction en jeu.',
            'icon' => 'bi-people-fill', 'points' => 10, 'category' => 'Faction',
        ],
        [
            'code' => 'geocoins_1000', 'name' => 'Petite fortune',
            'description' => 'Possède 1 000 GeoCoins.',
            'icon' => 'bi-coin', 'points' => 15, 'category' => 'Économie',
        ],
        [
            'code' => 'geocoins_10000', 'name' => 'Magnat',
            'description' => 'Possède 10 000 GeoCoins.',
            'icon' => 'bi-cash-stack', 'points' => 30, 'category' => 'Économie',
        ],
        [
            'code' => 'age_iron', 'name' => 'Âge du Fer',
            'description' => "Atteins l'âge du Fer.",
            'icon' => 'bi-hammer', 'points' => 20, 'category' => 'Progression',
        ],
        [
            'code' => 'age_industrial', 'name' => 'Ère industrielle',
            'description' => "Atteins l'âge industriel.",
            'icon' => 'bi-gear-fill', 'points' => 35, 'category' => 'Progression',
        ],
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
                    'category'        => $a['category'],
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
