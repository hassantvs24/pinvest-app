<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block non-owner users (403).
 */
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isOwner(), 403);

        return $next($request);
    }
}
