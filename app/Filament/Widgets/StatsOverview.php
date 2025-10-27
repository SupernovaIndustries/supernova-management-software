<?php

namespace App\Filament\Widgets;

use App\Models\Component;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Quotation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Componenti Totali', Component::count())
                ->description('In magazzino')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Componenti Low Stock', Component::lowStock()->count())
                ->description('Necessitano riordino')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Clienti Attivi', Customer::active()->count())
                ->description('Totale clienti')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Progetti Attivi', Project::active()->count())
                ->description(Project::overdue()->count() . ' in ritardo')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning'),

            Stat::make('Preventivi Mese', Quotation::whereMonth('created_at', now()->month)->count())
                ->description('€ ' . number_format(Quotation::whereMonth('created_at', now()->month)->sum('total'), 2))
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('Valore Magazzino', '€ ' . number_format(Component::sum(\DB::raw('stock_quantity * unit_price')), 2))
                ->description('Valore totale componenti')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),
        ];
    }
}