<?php

namespace App\Filament\Resources\UserManualResource\Pages;

use App\Filament\Resources\UserManualResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserManuals extends ListRecords
{
    protected static string $resource = UserManualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
