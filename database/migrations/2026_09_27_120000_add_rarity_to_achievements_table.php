<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rareté des succès : commun, peu commun, rare, épique, légendaire — le
 * vocabulaire du mod (item/GeoRarity) et du plugin (AchievementService), ids
 * common | uncommon | rare | epic | legendary.
 *
 * Idempotente : la colonne n'est ajoutée que si elle manque, et la rareté des
 * succès du plugin n'est posée que là où elle vaut encore le défaut `common`
 * (une rareté changée à la main dans l'admin n'est jamais écrasée).
 * Les raretés DOIVENT rester celles de AchievementService.DEFAULT_RARITY (Pluginmc).
 */
return new class extends Migration
{
    private array $rarities = [
        'faction_member' => 'common',
        'geocoins_1000' => 'common',
        'geocoins_10000' => 'rare',
        'age_iron' => 'uncommon',
        'age_industrial' => 'rare',
        'adv_village_taken' => 'uncommon',
        'adv_villages_6' => 'epic',
        'adv_warriors_50' => 'rare',
        'adv_crater' => 'rare',
        'adv_capture' => 'common',
        'adv_photo_10' => 'uncommon',
        'adv_bestiary' => 'uncommon',
        'adv_bestiary_10' => 'epic',
        'adv_mechanic' => 'common',
        'adv_grave' => 'common',
        'adv_level_10' => 'rare',
        'adv_boss' => 'rare',
        'adv_boss_10' => 'legendary',
        'adv_lunar_boss' => 'legendary',
        'adv_biomes_10' => 'uncommon',
        'adv_biomes_30' => 'epic',
        'adv_driver_10' => 'uncommon',
        'adv_driver_100' => 'epic',
        'adv_research_5' => 'common',
        'adv_research_20' => 'rare',
        'adv_research_all' => 'legendary',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('achievements', 'rarity')) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->string('rarity', 16)->default('common')->after('points');
            });
        }

        foreach ($this->rarities as $code => $rarity) {
            DB::table('achievements')
                ->where('code', $code)
                ->where(function ($q) {
                    $q->whereNull('rarity')->orWhere('rarity', 'common');
                })
                ->update(['rarity' => $rarity]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('achievements', 'rarity')) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->dropColumn('rarity');
            });
        }
    }
};
