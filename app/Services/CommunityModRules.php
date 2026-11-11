<?php

namespace App\Services;

/**
 * Règles de validation communes aux mods communauté (outil MCP community_mod_upsert
 * et page Admin → Mods Communauté) : le launcher télécharge ces jars chez les joueurs.
 *  - url : https obligatoire, hôte pris dans config('mcp.community_mod_download_hosts') ;
 *  - filename : nom de fichier .jar simple (lettres, chiffres, . _ + -), sans « .. ».
 */
class CommunityModRules
{
    /** @return array{filename: array, url: array} */
    public static function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._+-]+\.jar$/D', 'not_regex:/\.\./'],
            'url'      => ['required', 'string', 'url', 'max:500', 'regex:' . self::urlPattern()],
        ];
    }

    /** Motif PCRE d'une URL https dont l'hôte (sans port ni identifiants) figure dans la liste autorisée. */
    public static function urlPattern(): string
    {
        $hosts = array_values(array_filter(array_map(
            fn ($h) => strtolower(trim((string) $h)),
            (array) config('mcp.community_mod_download_hosts', [])
        ), fn ($h) => $h !== ''));

        if ($hosts === []) {
            return '#^(?!)#';
        }

        return '#^https://(?:' . implode('|', array_map(fn ($h) => preg_quote($h, '#'), $hosts)) . ')/[^\s\\\\]*$#iD';
    }
}
