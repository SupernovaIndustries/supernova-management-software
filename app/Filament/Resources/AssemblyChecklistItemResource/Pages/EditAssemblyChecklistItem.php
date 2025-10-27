<?php

namespace App\Filament\Resources\AssemblyChecklistItemResource\Pages;

use App\Filament\Resources\AssemblyChecklistItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssemblyChecklistItem extends EditRecord
{
    protected static string $resource = AssemblyChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
