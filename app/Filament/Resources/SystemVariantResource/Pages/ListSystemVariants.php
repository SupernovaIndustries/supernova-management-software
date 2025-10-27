<?php

namespace App\Filament\Resources\SystemVariantResource\Pages;

use App\Filament\Resources\SystemVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSystemVariants extends ListRecords
{
    protected static string $resource = SystemVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
