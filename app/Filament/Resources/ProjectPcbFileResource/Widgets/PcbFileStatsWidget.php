<?php

namespace App\Filament\Resources\ProjectPcbFileResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ProjectPcbFile;
use App\Models\Project;

class PcbFileStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalFiles = ProjectPcbFile::count();
        $totalProjects = ProjectPcbFile::distinct('project_id')->count();
        $primaryFiles = ProjectPcbFile::where('is_primary', true)->count();
        $backupFiles = ProjectPcbFile::where('is_backup', true)->count();
        
        // Calculate total storage used
        $totalStorage = ProjectPcbFile::sum('file_size') ?? 0;
        $storageInGB = round($totalStorage / 1024 / 1024 / 1024, 2);
        
        // Get format distribution
        $formats = ProjectPcbFile::selectRaw('file_type, COUNT(*) as count')
            ->groupBy('file_type')
            ->pluck('count', 'file_type')
            ->toArray();
        
        $formatBreakdown = collect($formats)
            ->map(fn ($count, $format) => strtoupper($format ?? 'N/A') . ': ' . $count)
            ->join(', ');
        
        // Recent activity
        $recentUploads = ProjectPcbFile::where('created_at', '>=', now()->subDays(7))->count();
        
        return [
            Stat::make('Total PCB Files', $totalFiles)
                ->description($totalProjects . ' projects')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),
            
            Stat::make('Storage Used', $storageInGB . ' GB')
                ->description($formatBreakdown)
                ->descriptionIcon('heroicon-m-server')
                ->color('warning'),
            
            Stat::make('Primary Files', $primaryFiles)
                ->description($backupFiles . ' backups')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('success'),
            
            Stat::make('Recent Uploads', $recentUploads)
                ->description('Last 7 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}