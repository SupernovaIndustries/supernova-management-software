<?php

namespace App\Filament\Resources\ObsolescenceAlertResource\Pages;

use App\Filament\Resources\ObsolescenceAlertResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListObsolescenceAlerts extends ListRecords
{
    protected static string $resource = ObsolescenceAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}