<?php

namespace App\Filament\Resources\UserManualResource\Pages;

use App\Filament\Resources\UserManualResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserManual extends EditRecord
{
    protected static string $resource = UserManualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
