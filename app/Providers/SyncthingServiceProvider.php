<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SyncthingPathManager;

class SyncthingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register SyncthingPathManager as a singleton
        $this->app->singleton('syncthing.paths', function ($app) {
            return new SyncthingPathManager();
        });

        // Register alias for easier access
        $this->app->alias('syncthing.paths', SyncthingPathManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Ensure Syncthing directories exist on boot
        if ($this->app->runningInConsole()) {
            $this->app->make('syncthing.paths')->ensureDirectoriesExist();
        }

        // Register custom Artisan commands
        $this->commands([
            \App\Console\Commands\SyncthingStatus::class,
            \App\Console\Commands\SyncthingSetup::class,
        ]);
    }
}