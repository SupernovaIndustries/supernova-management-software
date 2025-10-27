<?php

namespace App\Filament\Resources\ComponentImportResource\Pages;

use App\Filament\Resources\ComponentImportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComponentImports extends ListRecords
{
    protected static string $resource = ComponentImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
