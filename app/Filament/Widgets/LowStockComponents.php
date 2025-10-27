<?php

namespace App\Filament\Widgets;

use App\Models\Component;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockComponents extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Component::query()
                    ->with('category')
                    ->lowStock()
                    ->orderBy('stock_quantity', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Quantità')
                    ->sortable()
                    ->color('danger')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('min_stock_level')
                    ->label('Livello Minimo')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('reorder_quantity')
                    ->label('Qtà Riordino')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('storage_location')
                    ->label('Posizione')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('reorder')
                    ->label('Riordina')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('primary')
                    ->url(fn (Component $record): string => route('filament.admin.resources.components.edit', ['record' => $record])),
            ])
            ->paginated([5, 10, 25])
            ->heading('Componenti in Esaurimento')
            ->description('Componenti che necessitano riordino');
    }
}