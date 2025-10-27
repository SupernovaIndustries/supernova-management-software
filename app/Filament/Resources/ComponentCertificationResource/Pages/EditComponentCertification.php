<?php

namespace App\Filament\Resources\ComponentCertificationResource\Pages;

use App\Filament\Resources\ComponentCertificationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComponentCertification extends EditRecord
{
    protected static string $resource = ComponentCertificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}