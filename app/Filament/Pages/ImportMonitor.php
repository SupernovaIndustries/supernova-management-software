<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class ImportMonitor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static string $view = 'filament.pages.import-monitor';

    protected static ?string $title = 'Monitor Import Componenti';

    protected static ?string $navigationLabel = 'Monitor Import';

    // Don't show in navigation (accessed via button)
    protected static bool $shouldRegisterNavigation = false;

    // Disable this page - using standalone version instead
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return false;
    }

    // Enable polling every 2 seconds for real-time updates
    protected static ?string $pollingInterval = '2s';

    public array $importJobs = [];

    public function mount(): void
    {
        $this->loadImportJobs();
    }

    public function loadImportJobs(): void
    {
        $jobIds = Cache::get('import_jobs_list', []);
        $jobs = [];

        // Reverse to show newest first
        foreach (array_reverse($jobIds) as $jobId) {
            $progress = Cache::get("import_progress_{$jobId}");
            if ($progress) {
                $progress['job_id'] = $jobId;
                $jobs[] = $progress;
            }
        }

        $this->importJobs = $jobs;
    }

    // This method is called when polling
    public function refresh(): void
    {
        $this->loadImportJobs();
    }
}
