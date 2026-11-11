<?php

namespace App\Services;

use App\Models\User;

/**
 * Règle de rapprochement d'un compte panel existant avec un profil du site
 * (connexion unique).
 *
 * Un e-mail non vérifié ne prouve rien : n'importe qui peut ouvrir un compte
 * sur le site avec l'adresse d'un administrateur du panel. Le rapprochement
 * par e-mail n'est donc permis que si l'adresse est vérifiée DES DEUX CÔTÉS
 * et que le compte panel n'est pas déjà lié à un autre compte du site.
 * Le lien par identifiant du site (`sso_id`, stable) n'est pas concerné.
 */
class SsoAccountLinker
{
    public const EMAIL_UNVERIFIED = 'email_unverified';
    public const ALREADY_LINKED = 'already_linked';

    /**
     * @return string|null  null si le rapprochement est permis, sinon la clé
     *                      `messages.sso.*` qui explique le refus
     */
    public static function emailLinkRefusal(array $profile, User $local): ?string
    {
        $siteVerified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$siteVerified || $local->email_verified_at === null) {
            return self::EMAIL_UNVERIFIED;
        }

        if ($local->sso_id !== null) {
            return self::ALREADY_LINKED;
        }

        return null;
    }
}
