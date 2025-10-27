<?php

namespace App\Filament\Resources\ProjectPcbFileResource\Pages;

use App\Filament\Resources\ProjectPcbFileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use App\Services\PcbVersionControlService;

class ListProjectPcbFiles extends ListRecords
{
    protected static string $resource = ProjectPcbFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('cleanup_old_versions')
                ->label('Cleanup Old Versions')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $service = app(PcbVersionControlService::class);
                    $deleted = 0;
                    
                    // Get all PCB files older than 6 months with more than 5 versions
                    $oldFiles = \App\Models\ProjectPcbFile::where('created_at', '<', now()->subMonths(6))
                        ->get()
                        ->groupBy('project_id')
                        ->filter(function ($files) {
                            return $files->count() > 5;
                        });
                    
                    foreach ($oldFiles as $projectFiles) {
                        // Keep only the latest 5 versions
                        $toDelete = $projectFiles->sortByDesc('version')->skip(5);
                        foreach ($toDelete as $file) {
                            if ($file->is_backup) {
                                $file->delete();
                                $deleted++;
                            }
                        }
                    }
                    
                    Notification::make()
                        ->title('Cleanup Complete')
                        ->body("Deleted {$deleted} old backup versions")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProjectPcbFileResource\Widgets\PcbFileStatsWidget::class,
        ];
    }
}