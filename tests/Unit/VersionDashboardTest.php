<?php

namespace Tests\Unit;

use App\Services\VersionDashboard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VersionDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['geoventure.versions.github_token' => null, 'geoventure.versions.cache_ttl' => 600]);
    }

    private function definition(string $key): array
    {
        foreach (VersionDashboard::COMPONENTS as $c) {
            if ($c['key'] === $key) {
                return $c;
            }
        }
        $this->fail("composant inconnu : {$key}");
    }

    private function release(string $tag, array $extra = []): array
    {
        return $extra + ['tag_name' => $tag, 'html_url' => 'https://github.com/Geoventure-MC/x/releases/tag/' . $tag, 'draft' => false, 'prerelease' => false, 'published_at' => '2026-11-01T10:00:00Z'];
    }

    // ------------------------------------------------------------------ comparaison de versions

    public function test_comparaison_de_versions(): void
    {
        $this->assertSame(-1, VersionDashboard::compare('1.9.9', '1.10.0'));
        $this->assertSame(1, VersionDashboard::compare('3.42.20', '3.42.3'));
        $this->assertSame(0, VersionDashboard::compare('1.2', '1.2.0'));
        $this->assertSame(0, VersionDashboard::compare('v1.2.0', '1.2.0-beta'));
        $this->assertNull(VersionDashboard::compare('abc', '1.0.0'));
        $this->assertNull(VersionDashboard::compare(null, '1.0.0'));
    }

    public function test_badges_a_jour_en_retard_inconnu(): void
    {
        $this->assertSame(VersionDashboard::UP_TO_DATE, VersionDashboard::status('2.36.8', '2.36.8'));
        $this->assertSame(VersionDashboard::UP_TO_DATE, VersionDashboard::status('2.37.0', '2.36.8'), 'un build en avance reste à jour');
        $this->assertSame(VersionDashboard::BEHIND, VersionDashboard::status('2.36.7', '2.36.8'));
        $this->assertSame(VersionDashboard::UNKNOWN, VersionDashboard::status(null, '2.36.8'));
        $this->assertSame(VersionDashboard::UNKNOWN, VersionDashboard::status('2.36.8', null));
    }

    public function test_version_d_un_tag_selon_les_prefixes(): void
    {
        $this->assertSame('1.2.3', VersionDashboard::versionFromTag('geo-v1.2.3', ['geo-v', 'v']));
        $this->assertSame('1.8.4', VersionDashboard::versionFromTag('v1.8.4', ['geo-v', 'v']));
        $this->assertNull(VersionDashboard::versionFromTag('forum-v1.0.9', ['geo-v', 'v']));
        $this->assertNull(VersionDashboard::versionFromTag('vNext', ['v']));
    }

    public function test_plus_haute_release_ignore_brouillons_et_prereleases(): void
    {
        $releases = [
            $this->release('v1.10.0', ['draft' => true]),
            $this->release('v1.9.0', ['prerelease' => true]),
            $this->release('forum-v9.9.9'),
            $this->release('v1.2.0'),
            $this->release('v1.10.0-rc1', ['html_url' => 'javascript:alert(1)']),
            'pas un tableau',
        ];
        $best = VersionDashboard::latestFromReleases($releases, ['v'], 'Geoventure-MC/x');

        $this->assertSame('1.10.0', $best['version']);
        $this->assertSame('https://github.com/Geoventure-MC/x/releases/tag/v1.10.0-rc1', $best['url'], 'un lien non GitHub est remplacé');
        $this->assertSame('2026-11-01', $best['published_at']);
        $this->assertNull(VersionDashboard::latestFromReleases([$this->release('v1.0.0', ['draft' => true])], ['v'], 'Geoventure-MC/x'));
    }

    public function test_version_lue_dans_le_nom_du_jar(): void
    {
        $this->assertSame('3.42.20', VersionDashboard::versionFromJar('geomods-3.42.20.jar', 'geomods'));
        $this->assertSame('3.42.20', VersionDashboard::versionFromJar('geomods-1.20.1-3.42.20.jar', 'geomods'));
        $this->assertSame('1.1.3', VersionDashboard::versionFromJar('GeoWarfare-v1.1.3.jar', 'geowarfare'));
        $this->assertNull(VersionDashboard::versionFromJar('geomods-compat-1.0.0.jar', 'geomods'));
        $this->assertNull(VersionDashboard::versionFromJar('geomods-3.42.20.jar.disabled', 'geomods'));
    }

    public function test_version_nettoyee(): void
    {
        $this->assertSame('1.2.3', VersionDashboard::clean('v1.2.3'));
        $this->assertSame('1.2.3-beta', VersionDashboard::clean(' 1.2.3-beta '));
        $this->assertNull(VersionDashboard::clean('<script>alert(1)</script>'));
        $this->assertNull(VersionDashboard::clean(null));
    }

    // ------------------------------------------------------------------ GitHub, cache, jeton

    public function test_la_release_est_mise_en_cache(): void
    {
        Http::fake(['api.github.com/*' => Http::response([$this->release('v1.2.0'), $this->release('v1.3.0')], 200)]);
        $svc = new VersionDashboard();
        $c = $this->definition('panel');

        $first = $svc->latest($c);
        $second = $svc->latest($c);

        $this->assertSame('1.3.0', $first['release']['version']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);

        $svc->forgetCache();
        $svc->latest($c);
        Http::assertSentCount(2);
    }

    public function test_sans_jeton_aucun_en_tete_d_autorisation(): void
    {
        Http::fake(['api.github.com/*' => Http::response([$this->release('v1.0.0')], 200)]);

        $this->assertFalse(VersionDashboard::hasToken());
        $res = (new VersionDashboard())->latest($this->definition('panel'));

        $this->assertTrue($res['ok']);
        Http::assertSent(fn (Request $r) => ! $r->hasHeader('Authorization')
            && str_starts_with($r->url(), 'https://api.github.com/repos/Geoventure-MC/panel/releases'));
    }

    public function test_jeton_envoye_seulement_s_il_est_configure(): void
    {
        config(['geoventure.versions.github_token' => 'jeton-de-test']);
        Http::fake(['api.github.com/*' => Http::response([$this->release('v1.0.0')], 200)]);

        $this->assertTrue(VersionDashboard::hasToken());
        (new VersionDashboard())->latest($this->definition('panel'));

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer jeton-de-test'));
    }

    public function test_depot_prive_sans_acces_donne_inconnu_sans_exception(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found', 'documentation_url' => 'interne'], 404)]);

        $res = (new VersionDashboard())->latest($this->definition('geomods'));

        $this->assertFalse($res['ok']);
        $this->assertNull($res['release']);
        $this->assertSame('no_access', $res['error']);
        $this->assertSame(VersionDashboard::UNKNOWN, VersionDashboard::status('3.42.20', $res['release']['version'] ?? null));
        $this->assertStringNotContainsString('interne', json_encode($res), 'le corps de la réponse GitHub n\'est pas conservé');
    }

    public function test_limite_de_requetes_github(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 403, ['X-RateLimit-Remaining' => '0'])]);

        $this->assertSame('rate_limited', (new VersionDashboard())->fetchLatest($this->definition('panel'))['error']);
    }

    public function test_github_injoignable(): void
    {
        Http::fake(['api.github.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('délai dépassé')]);

        $this->assertSame('unreachable', (new VersionDashboard())->fetchLatest($this->definition('panel'))['error']);
    }

    public function test_derniere_valeur_connue_resservie_si_github_echoue(): void
    {
        $svc = new VersionDashboard();
        $c = $this->definition('panel');

        Http::fake(['api.github.com/*' => Http::sequence()->push([$this->release('v1.5.0')], 200)->push([], 500)]);
        $svc->latest($c);
        $svc->forgetCache();
        $res = $svc->latest($c);

        $this->assertTrue($res['stale']);
        $this->assertSame('1.5.0', $res['release']['version']);
        $this->assertSame('error', $res['error']);
    }

    // ------------------------------------------------------------------ versions relevées

    public function test_fichier_de_versions_relevees(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'geover');
        file_put_contents($file, json_encode(['GeoFactions' => 'v2.36.8', 'geovigie' => '<b>x</b>', 'geopasse' => 12]));
        config(['geoventure.versions.reported_file' => $file]);

        $reported = (new VersionDashboard())->reported();
        @unlink($file);

        $this->assertSame(['geofactions'], array_keys($reported));
        $this->assertSame('2.36.8', $reported['geofactions']['version']);

        config(['geoventure.versions.reported_file' => $file]);
        $this->assertSame([], (new VersionDashboard())->reported(), 'fichier absent : aucune version devinée');
    }

    public function test_ecriture_des_versions_relevees_filtre_et_conserve(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'versions');
        file_put_contents($file, json_encode(['geovigie' => '0.1.3']));
        config(['geoventure.versions.reported_file' => $file]);

        $r = VersionDashboard::writeReported(['GeoFactions' => 'v2.38.0', 'inconnu' => '1.0.0', 'geopasse' => 'pas une version']);
        $this->assertSame(['geofactions' => '2.38.0'], $r['written'], 'seule la clé connue et la version valide sont gardées');
        $this->assertSame(['inconnu', 'geopasse'], $r['refused']);

        $back = (new VersionDashboard())->reported();
        $this->assertSame('2.38.0', $back['geofactions']['version']);
        $this->assertSame('0.1.3', $back['geovigie']['version'], 'les composants déjà relevés sont conservés');
        $this->assertArrayNotHasKey('inconnu', $back);
        $this->assertContains('geofactions', VersionDashboard::reportedKeys());
        $this->assertNotContains('geomods', VersionDashboard::reportedKeys(), 'le mod se lit dans le modpack, pas ici');
        @unlink($file);
    }
}
