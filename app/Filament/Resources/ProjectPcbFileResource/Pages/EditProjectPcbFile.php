<?php

namespace App\Filament\Resources\ProjectPcbFileResource\Pages;

use App\Filament\Resources\ProjectPcbFileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Services\PcbVersionControlService;
use Illuminate\Database\Eloquent\Model;

class EditProjectPcbFile extends EditRecord
{
    protected static string $resource = ProjectPcbFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    // Prevent deletion of primary version if it's the only one
                    if ($record->is_primary) {
                        $otherVersions = \App\Models\ProjectPcbFile::where('project_id', $record->project_id)
                            ->where('id', '!=', $record->id)
                            ->count();
                        
                        if ($otherVersions === 0) {
                            Notification::make()
                                ->title('Cannot delete')
                                ->body('Cannot delete the only PCB file version')
                                ->danger()
                                ->send();
                            
                            $this->halt();
                        }
                    }
                }),
            Actions\Action::make('compare_with_previous')
                ->label('Compare with Previous')
                ->icon('heroicon-o-arrows-right-left')
                ->action(function ($record) {
                    // Find previous version
                    $previousVersion = \App\Models\ProjectPcbFile::where('project_id', $record->project_id)
                        ->where('created_at', '<', $record->created_at)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if (!$previousVersion) {
                        Notification::make()
                            ->title('No Previous Version')
                            ->body('This is the first version of the PCB file')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    // Redirect to comparison view
                    return redirect()->route('filament.admin.pcb-comparison', [
                        'file1' => $previousVersion->id,
                        'file2' => $record->id,
                    ]);
                }),
            Actions\Action::make('create_backup')
                ->label('Create Backup')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->action(function ($record) {
                    $backup = $record->replicate();
                    $backup->version .= '-backup-' . now()->format('YmdHis');
                    $backup->is_backup = true;
                    $backup->is_primary = false;
                    $backup->save();
                    
                    Notification::make()
                        ->title('Backup Created')
                        ->body('Backup version created successfully')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // If marking as primary, unmark others
        if (($data['is_primary'] ?? false) && !$record->is_primary) {
            \App\Models\ProjectPcbFile::where('project_id', $record->project_id)
                ->where('id', '!=', $record->id)
                ->update(['is_primary' => false]);
        }
        
        $record->update($data);
        
        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'PCB file updated successfully';
    }
}