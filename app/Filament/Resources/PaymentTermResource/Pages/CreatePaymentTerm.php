<?php

namespace App\Filament\Resources\PaymentTermResource\Pages;

use App\Filament\Resources\PaymentTermResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentTerm extends CreateRecord
{
    protected static string $resource = PaymentTermResource::class;
}
