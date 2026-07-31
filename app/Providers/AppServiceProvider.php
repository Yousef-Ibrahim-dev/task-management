<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
    }

    /**
     * Outside production, lazy loading, missing attributes and silently
     * discarded attributes throw instead of passing silently, so N+1 queries
     * and typos surface during development rather than in a live request.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }


    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $identifier = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute((int) config('api.rate_limit.per_minute'))
                ->by((string) $identifier);
        });

        RateLimiter::for('auth-register', fn (Request $request): Limit => Limit::perMinute(
            (int) config('api.rate_limit.register_per_minute')
        )->by((string) $request->ip()));

        RateLimiter::for('auth-login', function (Request $request): Limit {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return Limit::perMinute((int) config('api.rate_limit.login_per_minute'))
                ->by($email.'|'.$request->ip());
        });
    }
}
