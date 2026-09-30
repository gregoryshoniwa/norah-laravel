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
        // The direct EcoCash API pushes a USSD prompt to any phone number the
        // caller supplies, so it is throttled per account (falling back to IP
        // for unauthenticated attempts). Tune with ECOCASH_DIRECT_RATE_LIMIT.
        RateLimiter::for('ecocash-direct', function (Request $request) {
            $perMinute = (int) env('ECOCASH_DIRECT_RATE_LIMIT', 60);
            $user = $request->user() ?: (auth('api')->check() ? auth('api')->user() : null);
            $key = $user ? 'user:' . $user->id : 'ip:' . $request->ip();

            return Limit::perMinute($perMinute)->by($key)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many EcoCash requests. Please slow down and retry shortly.',
                ], 429);
            });
        });
    }
}
