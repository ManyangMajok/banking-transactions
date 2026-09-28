<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
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
        if (($this->app->environment('testing') || getenv('APP_ENV') === 'testing' || ($_SERVER['APP_ENV'] ?? null) === 'testing') &&
            (config('database.default') !== 'mysql' || DB::connection()->getDatabaseName() !== 'banking_test')) {
            throw new \RuntimeException('Tests may only use the isolated mysql banking_test database.');
        }
    }
}
