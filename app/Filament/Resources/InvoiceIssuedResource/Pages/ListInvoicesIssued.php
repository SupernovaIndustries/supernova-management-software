<?php

namespace App\Filament\Resources\InvoiceIssuedResource\Pages;

use App\Filament\Resources\InvoiceIssuedResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoicesIssued extends ListRecords
{
    protected static string $resource = InvoiceIssuedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
