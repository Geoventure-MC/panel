<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité du groupe « web ».
 *
 * - nosniff et Referrer-Policy : partout (aucun effet sur les réponses JSON du launcher).
 * - X-Frame-Options : seulement sur l'administration et la connexion (anti-clickjacking des actions admin).
 *   Les pages publiques (/status, /joueurs) restent intégrables dans un iframe du site.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if ($request->is('admin', 'admin/*', 'login', 'two-factor', 'file-manager', 'file-manager/*', 'password/*', 'auth/*')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }
}
