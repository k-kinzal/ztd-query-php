<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('authentication', fn (Request $request) => [Limit::perMinute(10)->by($request->ip()), Limit::perHour(30)->by(strtolower((string) $request->input('email')))]);
        RateLimiter::for('publishing', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?? $request->ip()));
    }
}
