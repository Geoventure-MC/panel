<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\SsoAccountLinker;
use Tests\TestCase;

class SsoAccountLinkerTest extends TestCase
{
    private function local(bool $verified = true, ?int $ssoId = null): User
    {
        $u = new User();
        $u->forceFill([
            'email' => 'admin@example.org',
            'email_verified_at' => $verified ? now() : null,
            'sso_id' => $ssoId,
        ]);

        return $u;
    }

    public function test_rapprochement_permis_si_email_verifie_des_deux_cotes(): void
    {
        $profile = ['id' => 7, 'email' => 'admin@example.org', 'email_verified' => true];

        $this->assertNull(SsoAccountLinker::emailLinkRefusal($profile, $this->local()));
    }

    public function test_refuse_si_email_non_verifie_cote_site(): void
    {
        foreach ([[], ['email_verified' => false], ['email_verified' => 'false'], ['email_verified' => 0], ['email_verified' => null]] as $extra) {
            $profile = ['id' => 7, 'email' => 'admin@example.org'] + $extra;

            $this->assertSame(
                SsoAccountLinker::EMAIL_UNVERIFIED,
                SsoAccountLinker::emailLinkRefusal($profile, $this->local()),
                'Un profil sans e-mail vérifié ne doit jamais rapprocher un compte : '.json_encode($extra)
            );
        }
    }

    public function test_refuse_si_email_non_verifie_cote_panel(): void
    {
        $profile = ['id' => 7, 'email' => 'admin@example.org', 'email_verified' => true];

        $this->assertSame(
            SsoAccountLinker::EMAIL_UNVERIFIED,
            SsoAccountLinker::emailLinkRefusal($profile, $this->local(false))
        );
    }

    public function test_refuse_un_compte_deja_lie_a_un_autre_compte_du_site(): void
    {
        $profile = ['id' => 7, 'email' => 'admin@example.org', 'email_verified' => true];

        $this->assertSame(
            SsoAccountLinker::ALREADY_LINKED,
            SsoAccountLinker::emailLinkRefusal($profile, $this->local(true, 99))
        );
    }

    public function test_les_messages_de_refus_existent_dans_les_deux_langues(): void
    {
        foreach (['fr', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ([SsoAccountLinker::EMAIL_UNVERIFIED, SsoAccountLinker::ALREADY_LINKED] as $key) {
                $this->assertNotSame('messages.sso.'.$key, __('messages.sso.'.$key), "{$locale} : clé manquante {$key}");
            }
        }
    }
}
