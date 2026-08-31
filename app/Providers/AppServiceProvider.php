<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // O limite é por cliente de API; sem credencial, cai no IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(
            (int) config('scheduler.api.rate_limit', 120)
        )->by($request->header('X-Client-Id') ?: $request->ip()));
    }
}
