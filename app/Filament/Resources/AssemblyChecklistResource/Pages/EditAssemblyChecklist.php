<?php

namespace App\Filament\Resources\AssemblyChecklistResource\Pages;

use App\Filament\Resources\AssemblyChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssemblyChecklist extends EditRecord
{
    protected static string $resource = AssemblyChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
