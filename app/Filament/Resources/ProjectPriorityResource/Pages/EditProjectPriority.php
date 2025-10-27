<?php

namespace App\Filament\Resources\ProjectPriorityResource\Pages;

use App\Filament\Resources\ProjectPriorityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectPriority extends EditRecord
{
    protected static string $resource = ProjectPriorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
