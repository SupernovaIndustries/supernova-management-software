<?php

namespace App\Filament\Resources\DatasheetTemplateResource\Pages;

use App\Filament\Resources\DatasheetTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDatasheetTemplate extends EditRecord
{
    protected static string $resource = DatasheetTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
