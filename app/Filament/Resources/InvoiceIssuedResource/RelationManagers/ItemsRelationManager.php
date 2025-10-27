<?php

namespace App\Filament\Resources\InvoiceIssuedResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Righe Fattura';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('description')
                    ->label('Descrizione')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Quantità')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(0.01)
                    ->reactive()
                    ->afterStateUpdated(fn (Set $set, $state, Get $get) =>
                        static::calculateLineTotal($set, $state, $get)
                    ),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Prezzo Unitario (€)')
                    ->numeric()
                    ->required()
                    ->prefix('€')
                    ->reactive()
                    ->afterStateUpdated(fn (Set $set, $state, Get $get) =>
                        static::calculateLineTotal($set, $state, $get)
                    ),

                Forms\Components\TextInput::make('discount_percentage')
                    ->label('Sconto (%)')
                    ->numeric()
                    ->default(0)
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->reactive()
                    ->afterStateUpdated(fn (Set $set, $state, Get $get) =>
                        static::calculateLineTotal($set, $state, $get)
                    ),

                Forms\Components\TextInput::make('tax_rate')
                    ->label('Aliquota IVA (%)')
                    ->numeric()
                    ->required()
                    ->default(22)
                    ->suffix('%')
                    ->reactive()
                    ->afterStateUpdated(fn (Set $set, $state, Get $get) =>
                        static::calculateLineTotal($set, $state, $get)
                    ),

                Forms\Components\Select::make('component_id')
                    ->label('Componente (Opzionale)')
                    ->relationship('component', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Collega un componente a questa riga'),

                Forms\Components\Placeholder::make('subtotal_display')
                    ->label('Subtotale')
                    ->content(fn (Get $get) => '€ ' . number_format($get('subtotal') ?? 0, 2, ',', '.')),

                Forms\Components\Placeholder::make('tax_amount_display')
                    ->label('Importo IVA')
                    ->content(fn (Get $get) => '€ ' . number_format($get('tax_amount') ?? 0, 2, ',', '.')),

                Forms\Components\Placeholder::make('total_display')
                    ->label('Totale Riga')
                    ->content(fn (Get $get) => '€ ' . number_format($get('total') ?? 0, 2, ',', '.')),

                Forms\Components\Hidden::make('subtotal'),
                Forms\Components\Hidden::make('tax_amount'),
                Forms\Components\Hidden::make('total'),
            ]);
    }

    protected static function calculateLineTotal(Set $set, $state, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discountPercentage = (float) ($get('discount_percentage') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 22);

        $subtotal = $quantity * $unitPrice;
        $discountAmount = $subtotal * ($discountPercentage / 100);
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = $taxableAmount * ($taxRate / 100);
        $total = $taxableAmount + $taxAmount;

        $set('subtotal', round($taxableAmount, 2));
        $set('tax_amount', round($taxAmount, 2));
        $set('total', round($total, 2));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(50)
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantità')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Prezzo Unit.')
                    ->money('EUR')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label('Sconto %')
                    ->suffix('%')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('IVA %')
                    ->suffix('%')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotale')
                    ->money('EUR')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('tax_amount')
                    ->label('IVA')
                    ->money('EUR')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Totale')
                    ->money('EUR')
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn ($record) => $record->invoice->calculateTotals()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn ($record) => $record->invoice->calculateTotals()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn ($record) => $record->invoice->calculateTotals()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn ($records) => $records->first()?->invoice->calculateTotals()),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
