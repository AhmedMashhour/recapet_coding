<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Too many login attempts',
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'retry_after' => $headers['Retry-After'],
                    ], 429);
                });
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Too many registration attempts',
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'retry_after' => $headers['Retry-After'],
                    ], 429);
                });
        });

        RateLimiter::for('deposits', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(10)
                    ->by($request->user()->id)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Too many deposit attempts',
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'retry_after' => $headers['Retry-After'],
                        ], 429);
                    })
                : Limit::none();
        });

        RateLimiter::for('withdrawals', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(5)
                    ->by($request->user()->id)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Too many withdrawal attempts',
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'retry_after' => $headers['Retry-After'],
                        ], 429);
                    })
                : Limit::none();
        });

        RateLimiter::for('transfers', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(10)
                    ->by($request->user()->id)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Too many transfer attempts',
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'retry_after' => $headers['Retry-After'],
                        ], 429);
                    })
                : Limit::none();

        });
    }

}


