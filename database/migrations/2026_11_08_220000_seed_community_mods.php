<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed des mods communautaires populaires pour le launcher Geoventure.
 *
 * Forgematica (Litematica), MaFgLib (MaLiLib), Tweakerge (Tweakeroo),
 * ForgematicaPrinter et Oculus pour Minecraft 1.20.1 Forge.
 * Idempotent : updateOrInsert sur `filename`.
 */
return new class extends Migration
{
    private array $mods = [
        [
            'name'        => 'Forgematica (Litematica)',
            'description' => 'Portage Forge non-officiel de Litematica pour Minecraft 1.20.1. Affiche des hologrammes et schémas 3D avec aperçu couche par couche, liste des matériaux requis et aide à la construction. Nécessite MaFgLib.',
            'filename'    => 'Forgematica-0.1.13-mc1.20.1.jar',
            'url'         => 'https://cdn.modrinth.com/data/dCKRaeBC/versions/YncE2C0Y/Forgematica-0.1.13-mc1.20.1.jar',
            'icon'        => 'https://cdn.modrinth.com/data/dCKRaeBC/06d4e5a628094cfd307e0cdc0eba4fb007d7987f_96.webp',
            'category'    => 'Construction',
            'author'      => 'Aedenthorn / masa',
            'version'     => '0.1.13-mc1.20.1',
            'active'      => true,
        ],
        [
            'name'        => 'MaFgLib (MaLiLib)',
            'description' => 'Bibliothèque requise pour faire fonctionner Forgematica (Litematica) et les portages Forge des mods de masa. À installer obligatoirement avec Forgematica !',
            'filename'    => 'MaFgLib-0.1.14-mc1.20.1.jar',
            'url'         => 'https://cdn.modrinth.com/data/SKI34J7B/versions/adXONMb5/MaFgLib-0.1.14-mc1.20.1.jar',
            'icon'        => 'https://cdn.modrinth.com/data/SKI34J7B/a73f6cb616d0454dfd26a13746fb4f964cf3b021_96.webp',
            'category'    => 'Bibliothèque',
            'author'      => 'Aedenthorn / masa',
            'version'     => '0.1.14-mc1.20.1',
            'active'      => true,
        ],
        [
            'name'        => 'Tweakerge (Tweakeroo)',
            'description' => 'Portage Forge de Tweakeroo pour 1.20.1. Ajoute de nombreux outils pratiques de construction client : placement flexible de blocs, rotation rapide, gamma/luminosité améliorée et raccourcis d\'inventaire. Nécessite MaFgLib.',
            'filename'    => 'Tweakerge-0.1.5-mc1.20.1.jar',
            'url'         => 'https://cdn.modrinth.com/data/yke6wdGF/versions/CYN4k7x6/Tweakerge-0.1.5-mc1.20.1.jar',
            'icon'        => 'https://cdn.modrinth.com/data/SKI34J7B/a73f6cb616d0454dfd26a13746fb4f964cf3b021_96.webp',
            'category'    => 'Utilitaires',
            'author'      => 'Aedenthorn / masa',
            'version'     => '0.1.5-mc1.20.1',
            'active'      => true,
        ],
        [
            'name'        => 'ForgematicaPrinter',
            'description' => 'Extension pour Forgematica permettant l\'impression automatique (printer) des schémas de construction bloc par bloc dans le monde.',
            'filename'    => 'ForgematicaPrinter-0.1.0+mc1.20.1.jar',
            'url'         => 'https://cdn.modrinth.com/data/oqw1j8E7/versions/Iu8zwuRq/ForgematicaPrinter-0.1.0%2Bmc1.20.1.jar',
            'icon'        => 'https://cdn.modrinth.com/data/dCKRaeBC/06d4e5a628094cfd307e0cdc0eba4fb007d7987f_96.webp',
            'category'    => 'Construction',
            'author'      => 'Aedenthorn',
            'version'     => '0.1.0+mc1.20.1',
            'active'      => true,
        ],
        [
            'name'        => 'Oculus (Shaders)',
            'description' => 'Support complet des packs de shaders (BSL, Complementary, Sildurs...) optimisé pour le moteur graphique Embeddium du modpack.',
            'filename'    => 'oculus-mc1.20.1-1.8.0.jar',
            'url'         => 'https://cdn.modrinth.com/data/GchcoXML/versions/iQ1SwGc3/oculus-mc1.20.1-1.8.0.jar',
            'icon'        => 'https://cdn.modrinth.com/data/GchcoXML/icon.png',
            'category'    => 'Graphismes',
            'author'      => 'Asek3 / Iris team',
            'version'     => '1.20.1-1.8.0',
            'active'      => true,
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->mods as $m) {
            $exists = DB::table('community_mods')->where('filename', $m['filename'])->exists();

            DB::table('community_mods')->updateOrInsert(
                ['filename' => $m['filename']],
                [
                    'name'        => $m['name'],
                    'description' => $m['description'],
                    'url'         => $m['url'],
                    'icon'        => $m['icon'],
                    'category'    => $m['category'],
                    'author'      => $m['author'],
                    'version'     => $m['version'],
                    'active'      => $m['active'],
                    'updated_at'  => $now,
                ] + ($exists ? [] : ['created_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('community_mods')
            ->whereIn('filename', array_column($this->mods, 'filename'))
            ->delete();
    }
};
