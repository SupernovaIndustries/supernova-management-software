<?php

namespace App\Filament\Resources\InvoiceReceivedResource\Pages;

use App\Filament\Resources\InvoiceReceivedResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoicesReceived extends ListRecords
{
    protected static string $resource = InvoiceReceivedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
