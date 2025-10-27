<?php

namespace App\Filament\Resources\F24FormResource\Pages;

use App\Filament\Resources\F24FormResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListF24Forms extends ListRecords
{
    protected static string $resource = F24FormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
