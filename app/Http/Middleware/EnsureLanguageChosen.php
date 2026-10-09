<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force the user to the language picker while they have not chosen
 * a preferred language yet. The picker itself and logout stay reachable.
 */
class EnsureLanguageChosen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->preferred_language === null && ! $request->is('select-language*')) {
            return redirect()->route('language.show');
        }

        return $next($request);
    }
}
