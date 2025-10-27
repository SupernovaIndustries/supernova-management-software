<?php

namespace App\Filament\Resources\DatasheetTemplateResource\Pages;

use App\Filament\Resources\DatasheetTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDatasheetTemplates extends ListRecords
{
    protected static string $resource = DatasheetTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
