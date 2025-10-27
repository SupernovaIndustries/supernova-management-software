<?php

namespace App\Filament\Resources\InvoiceReceivedResource\Pages;

use App\Filament\Resources\InvoiceReceivedResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceReceived extends EditRecord
{
    protected static string $resource = InvoiceReceivedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
