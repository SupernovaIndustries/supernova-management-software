<?php

namespace App\Filament\Resources\AssemblyChecklistTemplateResource\Pages;

use App\Filament\Resources\AssemblyChecklistTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssemblyChecklistTemplates extends ListRecords
{
    protected static string $resource = AssemblyChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
