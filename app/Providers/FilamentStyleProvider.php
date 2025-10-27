<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class FilamentStyleProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Add custom CSS for table enhancements
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => Blade::render('<style>' . file_get_contents(resource_path('css/table-enhancements.css')) . '</style>')
        );

        // Add custom JavaScript for table enhancements
        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): string => Blade::render('<script>' . file_get_contents(resource_path('js/table-enhancements.js')) . '</script>')
        );

    }
}