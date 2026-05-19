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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(300)->by(
                $request->attributes->get('auth_user')?->id ?? $request->ip()
            );
        });

        if ($this->app->environment('local')) {
            \Illuminate\Database\Eloquent\Model::preventLazyLoading();
        }
    }
}
