<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveProjectsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected function getStats(): array
    {
        // Active projects
        $activeProjects = Project::where('status', 'active')->count();

        // Projects in production
        $inProduction = Project::where('status', 'in_production')->count();

        // Completed this month
        $completedThisMonth = Project::where('status', 'completed')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        // Total boards produced (sum of boards_produced from all projects)
        $totalBoards = Project::whereNotNull('boards_produced')
            ->where('status', 'completed')
            ->sum('boards_produced');

        return [
            Stat::make('Progetti Attivi', $activeProjects)
                ->description('In lavorazione')
                ->descriptionIcon('heroicon-o-briefcase')
                ->color('success')
                ->chart($this->getActiveProjectsTrend()),

            Stat::make('In Produzione', $inProduction)
                ->description('Fase di produzione')
                ->descriptionIcon('heroicon-o-cog')
                ->color('warning'),

            Stat::make('Completati Mese', $completedThisMonth)
                ->description('Completati questo mese')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('info'),

            Stat::make('Schede Prodotte', number_format($totalBoards, 0, ',', '.'))
                ->description('Totale schede completate')
                ->descriptionIcon('heroicon-o-cpu-chip')
                ->color('success'),
        ];
    }

    protected function getActiveProjectsTrend(): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Project::where('status', 'active')
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();

            $trend[] = $count;
        }

        return $trend;
    }
}
