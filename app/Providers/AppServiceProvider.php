<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

    /**
     * General limiter for /api/v1. Authenticated callers get their own budget;
     * guests share one per client IP.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $identifier = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute((int) config('api.rate_limit.per_minute'))
                ->by((string) $identifier);
        });
    }
}
