<?php

namespace App\Filament\Resources\ProjectPcbFileResource\Pages;

use App\Filament\Resources\ProjectPcbFileResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Services\PcbVersionControlService;
use Illuminate\Database\Eloquent\Model;

class CreateProjectPcbFile extends CreateRecord
{
    protected static string $resource = ProjectPcbFileResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(PcbVersionControlService::class);
        
        // Get the latest version for this project
        $latestVersion = \App\Models\ProjectPcbFile::where('project_id', $data['project_id'])
            ->max('version') ?? '0.0.0';
        
        // Generate new version based on change type
        $versionParts = explode('.', $latestVersion);
        
        if ($data['change_type'] === 'major') {
            $versionParts[0]++;
            $versionParts[1] = 0;
            $versionParts[2] = 0;
        } elseif ($data['change_type'] === 'minor') {
            $versionParts[1]++;
            $versionParts[2] = 0;
        } else {
            $versionParts[2]++;
        }
        
        $data['version'] = implode('.', $versionParts);
        
        // Create the record
        $record = static::getModel()::create($data);
        
        // If this is marked as primary, update other files
        if ($data['is_primary'] ?? false) {
            \App\Models\ProjectPcbFile::where('project_id', $data['project_id'])
                ->where('id', '!=', $record->id)
                ->update(['is_primary' => false]);
        }
        
        // Create automatic backup if file size > 10MB
        if ($record->file_size > 10 * 1024 * 1024) {
            $backupData = $data;
            $backupData['is_backup'] = true;
            $backupData['is_primary'] = false;
            $backupData['version'] .= '-backup';
            \App\Models\ProjectPcbFile::create($backupData);
            
            Notification::make()
                ->title('Backup Created')
                ->body('Automatic backup created for large file')
                ->info()
                ->send();
        }
        
        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'PCB file uploaded successfully';
    }
}