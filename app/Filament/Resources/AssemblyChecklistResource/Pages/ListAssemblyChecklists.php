<?php

namespace App\Filament\Resources\AssemblyChecklistResource\Pages;

use App\Filament\Resources\AssemblyChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssemblyChecklists extends ListRecords
{
    protected static string $resource = AssemblyChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
