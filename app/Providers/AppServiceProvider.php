<?php

namespace App\Providers;

use App\ERP\Core\Models\Company;
use App\ERP\HR\Models\Employee;
use App\Models\User;
use App\Policies\CompanyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('landing-public', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $host = strtolower((string) $request->getHost());

            return Limit::perMinute(60)->by($host.'|'.$ip);
        });

        RateLimiter::for('landing-track', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $host = strtolower((string) $request->getHost());

            return Limit::perMinute(20)->by($host.'|'.$ip);
        });

        Route::bind('employee', fn (string $value) => Employee::whereKey($value)->firstOrFail());

        Gate::define('manage-rnd', fn (User $user): bool => $user->hasRole('admin') || $user->hasPermissionTo('manage-rnd'));

        Gate::policy(Company::class, CompanyPolicy::class);
    }
}
