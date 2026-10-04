<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', static function (Request $request): Limit {
            $username = Str::lower((string) $request->input('username', ''));
            $ipAddress = (string) $request->ip();

            return Limit::perMinute(5)->by(hash('sha256', $username.'|'.$ipAddress));
        });
    }
}
