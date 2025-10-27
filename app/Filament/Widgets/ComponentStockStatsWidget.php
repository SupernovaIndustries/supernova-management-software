<?php

namespace App\Filament\Widgets;

use App\Models\Component;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ComponentStockStatsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        // Calculate total warehouse value
        $warehouseValue = Component::select(
            DB::raw('SUM(stock_quantity * unit_price) as total_value')
        )->value('total_value') ?? 0;

        // Count out of stock
        $outOfStock = Component::where('stock_quantity', '<=', 0)->count();

        // Count low stock (below minimum)
        $lowStock = Component::whereColumn('stock_quantity', '<=', 'min_stock_level')
            ->where('stock_quantity', '>', 0)
            ->count();

        // Total components
        $totalComponents = Component::count();

        return [
            Stat::make('Valore Magazzino', '€' . number_format($warehouseValue, 2, ',', '.'))
                ->description('Valore totale componenti')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Esauriti', $outOfStock)
                ->description('Componenti a stock zero')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Scorta Bassa', $lowStock)
                ->description('Sotto soglia minima')
                ->descriptionIcon('heroicon-o-exclamation-circle')
                ->color('warning'),

            Stat::make('Totale Componenti', number_format($totalComponents, 0, ',', '.'))
                ->description('In magazzino')
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),
        ];
    }
}
