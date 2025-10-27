<?php

namespace App\Filament\Resources\ComponentImportResource\Pages;

use App\Filament\Resources\ComponentImportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComponentImport extends EditRecord
{
    protected static string $resource = ComponentImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
