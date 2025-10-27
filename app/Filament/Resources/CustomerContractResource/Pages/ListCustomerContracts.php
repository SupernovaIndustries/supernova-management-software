<?php

namespace App\Filament\Resources\CustomerContractResource\Pages;

use App\Filament\Resources\CustomerContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerContracts extends ListRecords
{
    protected static string $resource = CustomerContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
