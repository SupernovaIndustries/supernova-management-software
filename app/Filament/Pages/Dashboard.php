<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveProjectsStatsWidget;
use App\Filament\Widgets\ComponentStockStatsWidget;
use App\Filament\Widgets\MonthlyRevenueWidget;
use App\Filament\Widgets\OutstandingInvoicesWidget;
use App\Filament\Widgets\ProfitLossWidget;
use App\Filament\Widgets\RevenueForecastWidget;
use App\Filament\Widgets\TopCustomersWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.pages.dashboard';
    protected static ?string $title = 'Dashboard';

    /**
     * Get header widgets (displayed at the top).
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ProfitLossWidget::class,
        ];
    }

    /**
     * Get main widgets.
     */
    public function getWidgets(): array
    {
        return [
            MonthlyRevenueWidget::class,
            RevenueForecastWidget::class,
            OutstandingInvoicesWidget::class,
            ComponentStockStatsWidget::class,
            TopCustomersWidget::class,
            ActiveProjectsStatsWidget::class,
        ];
    }

    /**
     * Get columns layout (responsive).
     */
    public function getColumns(): int | string | array
    {
        return 1;  // Single column layout - widgets span full width
    }
}
