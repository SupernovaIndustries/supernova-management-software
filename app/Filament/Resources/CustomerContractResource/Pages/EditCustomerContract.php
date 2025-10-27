<?php

namespace App\Filament\Resources\CustomerContractResource\Pages;

use App\Filament\Resources\CustomerContractResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerContract extends EditRecord
{
    protected static string $resource = CustomerContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
