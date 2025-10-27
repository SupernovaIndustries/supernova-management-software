<?php

namespace App\Filament\Resources\ComponentCertificationResource\Pages;

use App\Filament\Resources\ComponentCertificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComponentCertifications extends ListRecords
{
    protected static string $resource = ComponentCertificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}