<?php

namespace App\Filament\Resources\F24FormResource\Pages;

use App\Filament\Resources\F24FormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditF24Form extends EditRecord
{
    protected static string $resource = F24FormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
