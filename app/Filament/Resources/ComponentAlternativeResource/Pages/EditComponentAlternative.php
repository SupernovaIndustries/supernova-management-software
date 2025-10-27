<?php

namespace App\Filament\Resources\ComponentAlternativeResource\Pages;

use App\Filament\Resources\ComponentAlternativeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComponentAlternative extends EditRecord
{
    protected static string $resource = ComponentAlternativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}