<?php

namespace App\Filament\Resources\ComponentImportResource\Pages;

use App\Filament\Resources\ComponentImportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewComponentImport extends ViewRecord
{
    protected static string $resource = ComponentImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('delete_with_data')
                ->label('Elimina con Dati')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Elimina Import e Dati Correlati')
                ->modalDescription(fn ($record) =>
                    "Sei sicuro di voler eliminare questo import?\n\n" .
                    "Questa azione eliminerà:\n" .
                    "• {$record->movements_created} movimenti di inventario\n" .
                    "• Le quantità verranno sottratte dal magazzino\n" .
                    "• I componenti rimarranno nel sistema\n\n" .
                    "L'azione è irreversibile!"
                )
                ->modalSubmitActionLabel('Elimina Tutto')
                ->action(function () {
                    try {
                        $this->record->deleteWithRelatedData();
                        
                        $this->redirect(static::$resource::getUrl('index'));
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Import Eliminato')
                            ->body("Import #{$this->record->id} e tutti i dati correlati sono stati eliminati con successo.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Errore')
                            ->body('Impossibile eliminare l\'import: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
