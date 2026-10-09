<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set the app locale from the user's saved preference (DB-driven,
 * so it persists across sessions). Falls back to Bangla.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            App::setLocale($user->preferred_language ?? 'bn');
        }

        return $next($request);
    }
}
