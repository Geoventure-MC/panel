<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }

    /** IP vue par l'application pour une requête arrivant du proxy 10.0.0.5 pour le client 203.0.113.9. */
    private function clientIp(?string $trusted): string
    {
        TrustProxies::flushState();
        config(['app.trusted_proxies' => $trusted]);
        (new AppServiceProvider($this->app))->boot();

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);
        (new TrustProxies())->handle($request, fn ($r) => response('ok'));

        return (string) $request->ip();
    }

    public function test_variable_vide_laisse_l_ip_du_proxy(): void
    {
        $this->assertSame('10.0.0.5', $this->clientIp(''));
        $this->assertSame('10.0.0.5', $this->clientIp(null));
    }

    public function test_liste_d_ip_fait_confiance_au_proxy_indique(): void
    {
        $this->assertSame('203.0.113.9', $this->clientIp('127.0.0.1, 10.0.0.5'));
        $this->assertSame('203.0.113.9', $this->clientIp('10.0.0.0/8'));
    }

    public function test_proxy_non_liste_ne_permet_pas_de_falsifier_l_ip(): void
    {
        $this->assertSame('10.0.0.5', $this->clientIp('192.168.1.1'));
    }

    public function test_etoile_fait_confiance_a_tout_appelant(): void
    {
        $this->assertSame('203.0.113.9', $this->clientIp('*'));
    }
}
