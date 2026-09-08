<?php

namespace App\Services;

use App\Models\OptionsGeneral;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Côté client du SSO Geoventure : le panel ne gère plus d'identité, il la
 * demande au site (geoventure.fr).
 *
 * Le compte local n'est pas supprimé pour autant. Il reste :
 *   • une porte de secours si le site est en panne (le mot de passe marche
 *     toujours) ;
 *   • le porteur des droits panel (`is_admin`, `role`), qui n'ont pas de
 *     sens côté site.
 *
 * Le secret client ne transite jamais par le navigateur : seul l'échange
 * code → ticket, fait de serveur à serveur, le présente.
 */
class SsoClient
{
    public function options(): ?OptionsGeneral
    {
        return OptionsGeneral::first();
    }

    /** Le SSO n'est proposé que s'il est activé ET complètement configuré. */
    public function isConfigured(): bool
    {
        $o = $this->options();

        return $o !== null
            && (bool) ($o->sso_enabled ?? false)
            && !empty($o->sso_url)
            && !empty($o->sso_client_id)
            && $this->secret() !== '';
    }

    public function siteUrl(): string
    {
        return rtrim((string) optional($this->options())->sso_url, '/');
    }

    public function clientId(): string
    {
        return (string) optional($this->options())->sso_client_id;
    }

    /**
     * Secret déchiffré. Il est stocké chiffré avec la clé applicative : un
     * accès en lecture seule à la base ne suffit donc pas à usurper le panel.
     */
    public function secret(): string
    {
        $raw = optional($this->options())->sso_client_secret;
        if (empty($raw)) {
            return '';
        }

        try {
            return (string) Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            // Valeur saisie avant le chiffrement (ou APP_KEY changée) : on la
            // prend telle quelle plutôt que de bloquer toutes les connexions.
            return (string) $raw;
        }
    }

    public static function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    /** URL vers laquelle envoyer le joueur pour qu'il s'identifie. */
    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return $this->siteUrl().'/geo-countries/sso/authorize?'.http_build_query([
            'client_id'    => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'state'        => $state,
        ]);
    }

    /**
     * Échange le code contre un ticket et le profil du joueur.
     *
     * @return array{ticket: string, user: array}|null  null si l'échange échoue
     */
    public function exchange(string $code, string $redirectUri): ?array
    {
        try {
            $response = Http::timeout(8)->asJson()
                ->post($this->siteUrl().'/api/geo-countries/sso/token', [
                    'client_id'     => $this->clientId(),
                    'client_secret' => $this->secret(),
                    'code'          => $code,
                    'redirect_uri'  => $redirectUri,
                ]);

            if (!$response->ok()) {
                return null;
            }

            $body = $response->json();
            if (!is_array($body) || empty($body['ticket']) || empty($body['user']['id'])) {
                return null;
            }

            return ['ticket' => (string) $body['ticket'], 'user' => $body['user']];
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Fin de session côté site. Sans effet visible si l'appel échoue. */
    public function logout(string $ticket): void
    {
        try {
            Http::timeout(5)
                ->withHeaders([
                    'X-Sso-Client' => $this->clientId(),
                    'X-Sso-Secret' => $this->secret(),
                    'Authorization' => 'Bearer '.$ticket,
                ])
                ->post($this->siteUrl().'/api/geo-countries/sso/logout');
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
