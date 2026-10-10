<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Keep translations in resources/lang as per project convention.
        $this->app->useLangPath(base_path('resources/lang'));

        // Brute-force protection for the single-identifier login/register form.
        RateLimiter::for('login', function (Request $request): Limit {
            $identifier = (string) $request->input('identifier', $request->input('phone', ''));

            return Limit::perMinute(5)->by(strtolower($identifier).'|'.$request->ip());
        });
    }
}
