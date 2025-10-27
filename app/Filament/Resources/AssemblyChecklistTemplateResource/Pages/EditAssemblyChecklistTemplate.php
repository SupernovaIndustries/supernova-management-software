<?php

namespace App\Filament\Resources\AssemblyChecklistTemplateResource\Pages;

use App\Filament\Resources\AssemblyChecklistTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssemblyChecklistTemplate extends EditRecord
{
    protected static string $resource = AssemblyChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
