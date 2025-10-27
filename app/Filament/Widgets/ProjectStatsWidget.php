<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Quotation;
use App\Models\ProjectDocument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $activeProjects = Project::where('project_status', 'active')->count();
        $totalQuotations = Quotation::count();
        $totalDocuments = ProjectDocument::count();
        $overdueProjects = Project::whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        return [
            Stat::make('Active Projects', $activeProjects)
                ->description('Currently active projects')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('success'),
                
            Stat::make('Total Quotations', $totalQuotations)
                ->description('All quotations in system')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
                
            Stat::make('Project Documents', $totalDocuments)
                ->description('Files uploaded to projects')
                ->descriptionIcon('heroicon-m-folder')
                ->color('warning'),
                
            Stat::make('Overdue Projects', $overdueProjects)
                ->description('Projects past due date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueProjects > 0 ? 'danger' : 'success'),
        ];
    }
}