<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Nexus\Search\Domain\Port\SearchCachePort;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SearchCachePort::class, function () {
            return new class implements SearchCachePort {
                public function get(string $key): ?array { return null; }
                public function put(string $key, array $results, int $ttlSeconds): void {}
                public function invalidateAll(): void {}
                public function has(string $key): bool { return false; }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
