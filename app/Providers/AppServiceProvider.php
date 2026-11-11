<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Derrière nginx / Cloudflare : TRUSTED_PROXIES (vide = inchangé, « * » ou IP/CIDR séparés par des virgules).
        // Fait ici et non dans bootstrap/app.php : à ce stade .env n'est pas encore chargé (ou la config est en cache).
        $trusted = trim((string) config('app.trusted_proxies', ''));
        if ($trusted !== '') {
            $proxies = $trusted === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trusted))));
            if ($proxies !== []) {
                TrustProxies::at($proxies);
            }
        }
    }
}
