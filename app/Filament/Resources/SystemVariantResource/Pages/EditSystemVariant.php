<?php

namespace App\Filament\Resources\SystemVariantResource\Pages;

use App\Filament\Resources\SystemVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSystemVariant extends EditRecord
{
    protected static string $resource = SystemVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
