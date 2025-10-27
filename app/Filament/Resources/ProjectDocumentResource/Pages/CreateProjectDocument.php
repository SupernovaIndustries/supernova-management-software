<?php

namespace App\Filament\Resources\ProjectDocumentResource\Pages;

use App\Filament\Resources\ProjectDocumentResource;
use App\Services\NextcloudService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CreateProjectDocument extends CreateRecord
{
    protected static string $resource = ProjectDocumentResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Upload to Nextcloud after creating the document
        if ($record->file_path) {
            try {
                $nextcloudService = app(NextcloudService::class);
                $localPath = Storage::disk('local')->path($record->file_path);

                // Set the filename for Nextcloud (using original filename)
                $tempDocument = clone $record;
                $tempDocument->filename = $record->original_filename;

                $uploaded = $nextcloudService->uploadProjectDocument($tempDocument, $localPath);

                if ($uploaded) {
                    Notification::make()
                        ->success()
                        ->title('Documento caricato con successo')
                        ->body('Il documento è stato salvato localmente e caricato su Nextcloud.')
                        ->send();
                } else {
                    Notification::make()
                        ->warning()
                        ->title('Upload Nextcloud fallito')
                        ->body('Il documento è stato salvato localmente, ma l\'upload su Nextcloud è fallito.')
                        ->send();
                }
            } catch (\Exception $e) {
                Log::error('Error uploading to Nextcloud: ' . $e->getMessage(), [
                    'document_id' => $record->id,
                    'exception' => $e,
                ]);

                Notification::make()
                    ->warning()
                    ->title('Avviso Upload Nextcloud')
                    ->body('Documento salvato localmente. Errore Nextcloud: ' . $e->getMessage())
                    ->persistent()
                    ->send();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
