<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Filament\Resources\InventoryMovementResource\RelationManagers;
use App\Models\InventoryMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    
    protected static ?string $navigationLabel = 'Movimenti Inventario';
    
    protected static ?string $modelLabel = 'Movimento Inventario';
    
    protected static ?string $pluralModelLabel = 'Movimenti Inventario';
    
    protected static ?string $navigationGroup = 'Inventario';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('component_id')
                    ->relationship('component', 'name')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('quantity_before')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('quantity_after')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('unit_cost')
                    ->numeric(),
                Forms\Components\TextInput::make('reference_type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference_id')
                    ->numeric(),
                Forms\Components\TextInput::make('reason')
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (InventoryMovement $record): string => 
                        $record->invoice_date ? 'Fattura: ' . $record->invoice_date->format('d/m/Y') : ''
                    ),
                Tables\Columns\TextColumn::make('component.name')
                    ->label('Componente')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (InventoryMovement $record): ?string {
                        return $record->component?->name;
                    }),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'adjustment' => 'warning',
                        'return' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'Entrata',
                        'out' => 'Uscita',
                        'adjustment' => 'Aggiustamento',
                        'return' => 'Reso',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantità')
                    ->numeric()
                    ->sortable()
                    ->color(fn (InventoryMovement $record): string => 
                        in_array($record->type, ['in', 'return']) ? 'success' : 'danger'
                    )
                    ->formatStateUsing(fn (int $state, InventoryMovement $record): string => 
                        (in_array($record->type, ['in', 'return']) ? '+' : '-') . $state
                    ),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Costo Unit.')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valore Tot.')
                    ->money('EUR')
                    ->state(fn (InventoryMovement $record): float => $record->total_value)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('N. Fattura')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Numero fattura copiato!')
                    ->description(fn (InventoryMovement $record): string => 
                        $record->supplier ? 'Fornitore: ' . ucfirst($record->supplier) : ''
                    ),
                Tables\Columns\TextColumn::make('invoice_total')
                    ->label('Tot. Fattura')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (InventoryMovement $record): ?string {
                        return $record->reason;
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utente')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('quantity_before')
                    ->label('Prima')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('quantity_after')
                    ->label('Dopo')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo Movimento')
                    ->options([
                        'in' => 'Entrata',
                        'out' => 'Uscita',
                        'adjustment' => 'Aggiustamento',
                        'return' => 'Reso',
                    ]),
                Tables\Filters\SelectFilter::make('supplier')
                    ->label('Fornitore')
                    ->options([
                        'mouser' => 'Mouser',
                        'digikey' => 'DigiKey',
                        'farnell' => 'Farnell',
                        'rs' => 'RS Components',
                        'tme' => 'TME',
                    ]),
                Tables\Filters\Filter::make('with_invoice')
                    ->label('Con Fattura')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('invoice_number')),
                Tables\Filters\Filter::make('without_invoice')
                    ->label('Senza Fattura')
                    ->query(fn (Builder $query): Builder => $query->whereNull('invoice_number')),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Da'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryMovements::route('/'),
            'create' => Pages\CreateInventoryMovement::route('/create'),
            'edit' => Pages\EditInventoryMovement::route('/{record}/edit'),
        ];
    }
}
