<?php

namespace App\Filament\Widgets;

use App\Models\ComponentLifecycleStatus;
use App\Models\ObsolescenceAlert;
use App\Services\ComponentLifecycleService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ComponentLifecycleStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $service = app(ComponentLifecycleService::class);
        $summary = $service->getLifecycleSummary();
        
        return [
            Stat::make('Active Components', $summary['active'])
                ->description('Components in active lifecycle')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('At Risk Components', $summary['nrnd'] + $summary['eol_announced'])
                ->description('NRND + EOL Announced')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
                
            Stat::make('Critical Issues', $summary['eol'] + $summary['obsolete'])
                ->description('EOL + Obsolete components')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
                
            Stat::make('Unresolved Alerts', $summary['pending_alerts'])
                ->description('Require attention')
                ->descriptionIcon('heroicon-m-bell')
                ->color('danger')
                ->url(route('filament.admin.resources.obsolescence-alerts.index')),
        ];
    }

    protected static ?int $sort = 2;
}