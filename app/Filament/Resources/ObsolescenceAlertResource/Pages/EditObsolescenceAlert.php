<?php

namespace App\Filament\Resources\ObsolescenceAlertResource\Pages;

use App\Filament\Resources\ObsolescenceAlertResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditObsolescenceAlert extends EditRecord
{
    protected static string $resource = ObsolescenceAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}