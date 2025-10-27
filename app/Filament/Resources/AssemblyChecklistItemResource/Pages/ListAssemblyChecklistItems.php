<?php

namespace App\Filament\Resources\AssemblyChecklistItemResource\Pages;

use App\Filament\Resources\AssemblyChecklistItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssemblyChecklistItems extends ListRecords
{
    protected static string $resource = AssemblyChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
