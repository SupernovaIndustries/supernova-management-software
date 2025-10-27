<?php

namespace App\Filament\Resources\SupplierCsvMappingResource\Pages;

use App\Filament\Resources\SupplierCsvMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplierCsvMapping extends EditRecord
{
    protected static string $resource = SupplierCsvMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
