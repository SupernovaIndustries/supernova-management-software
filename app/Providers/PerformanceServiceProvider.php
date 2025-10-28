<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Skip durante migrations e altri console commands
        // per evitare errori di tabelle mancanti
        if ($this->app->runningInConsole()) {
            return;
        }

        // Ottimizzazione delle query in produzione
        if ($this->app->environment('production')) {
            // Previeni lazy loading non intenzionale
            Model::preventLazyLoading();
        }

        // Configurazione pool di connessioni PostgreSQL
        DB::connection()->getPdo()->setAttribute(\PDO::ATTR_PERSISTENT, true);

        // Abilita query caching per le query comuni
        $this->enableQueryCaching();
    }

    /**
     * Abilita il caching per le query frequenti
     */
    protected function enableQueryCaching(): void
    {
        // Cache delle categorie per 1 ora
        if (!Cache::has('categories_list')) {
            Cache::remember('categories_list', 3600, function () {
                return \App\Models\Category::orderBy('name')->get();
            });
        }

        // Cache dei package types per 1 ora
        if (!Cache::has('package_types')) {
            Cache::remember('package_types', 3600, function () {
                return \App\Models\Component::whereNotNull('package_type')
                    ->distinct()
                    ->pluck('package_type')
                    ->toArray();
            });
        }
    }
}