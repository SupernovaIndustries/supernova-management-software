<?php

namespace App\Filament\Resources\SystemCategoryResource\Pages;

use App\Filament\Resources\SystemCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSystemCategories extends ListRecords
{
    protected static string $resource = SystemCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
