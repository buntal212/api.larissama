<?php

namespace App\Providers;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\User;
use App\Models\Warung;
use App\Policies\KategoriMenuPolicy;
use App\Policies\MenuPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarungPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Warung::class, WarungPolicy::class);
        Gate::policy(KategoriMenu::class, KategoriMenuPolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);

        RateLimiter::for('login', static function (Request $request): Limit {
            $username = Str::lower((string) $request->input('username', ''));
            $ipAddress = (string) $request->ip();

            return Limit::perMinute(5)->by(hash('sha256', $username.'|'.$ipAddress));
        });
    }
}
