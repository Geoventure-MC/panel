<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Ensure the authenticated user has admin privileges.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);

        return $next($request);
    }
}
