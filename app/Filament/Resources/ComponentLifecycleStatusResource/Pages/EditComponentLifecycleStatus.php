<?php

namespace App\Filament\Resources\ComponentLifecycleStatusResource\Pages;

use App\Filament\Resources\ComponentLifecycleStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComponentLifecycleStatus extends EditRecord
{
    protected static string $resource = ComponentLifecycleStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}