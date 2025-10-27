<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentTermResource\Pages;
use App\Filament\Resources\PaymentTermResource\RelationManagers;
use App\Models\PaymentTerm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentTermResource extends Resource
{
    protected static ?string $model = PaymentTerm::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationGroup = 'Amministrazione';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $navigationLabel = 'Termini di Pagamento';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Base')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nome Termine di Pagamento')
                            ->placeholder('es. 30/70, Pagamento a 30 giorni'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->placeholder('Descrizione dettagliata dei termini di pagamento')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('active')
                            ->label('Attivo')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configurazione Pagamento')
                    ->description('Scegli se questo termine prevede pagamento unico o a tranche')
                    ->schema([
                        Forms\Components\Radio::make('payment_type')
                            ->label('Tipo Pagamento')
                            ->options([
                                'single' => 'Pagamento Unico',
                                'tranches' => 'Pagamento a Tranche (30/70, 30/30/40, etc.)',
                            ])
                            ->default('single')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state === 'tranches') {
                                    // Initialize with a default 30/70 split
                                    $set('days', null);
                                }
                            })
                            ->required()
                            ->columnSpanFull(),

                        // Single payment fields
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('days')
                                    ->label('Giorni Netti')
                                    ->numeric()
                                    ->default(30)
                                    ->required(fn (Forms\Get $get) => $get('payment_type') === 'single')
                                    ->helperText('Numero di giorni per pagare la fattura'),

                                Forms\Components\TextInput::make('discount_percentage')
                                    ->label('Sconto Anticipato %')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(0)
                                    ->suffix('%')
                                    ->helperText('Sconto se pagato anticipatamente'),

                                Forms\Components\TextInput::make('discount_days')
                                    ->label('Giorni per Sconto')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Giorni entro cui si applica lo sconto'),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('payment_type') === 'single'),

                        // Tranches fields
                        Forms\Components\Repeater::make('tranches')
                            ->relationship('tranches')
                            ->label('Tranche di Pagamento')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome')
                                    ->required()
                                    ->placeholder('es. Acconto, Saldo, Prima rata')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('percentage')
                                    ->label('Percentuale %')
                                    ->numeric()
                                    ->required()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(30),

                                Forms\Components\Select::make('trigger_event')
                                    ->label('Evento Scatenante')
                                    ->options([
                                        'contract' => 'Firma Contratto / Accettazione',
                                        'delivery' => 'Consegna',
                                        'completion' => 'Completamento',
                                        'custom' => 'Personalizzato',
                                    ])
                                    ->default('contract')
                                    ->required(),

                                Forms\Components\TextInput::make('days_offset')
                                    ->label('Giorni dall\'evento')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('giorni')
                                    ->helperText('Giorni dall\'evento scatenante'),

                                Forms\Components\Hidden::make('sort_order'),
                            ])
                            ->columns(2)
                            ->reorderable('sort_order')
                            ->orderColumn('sort_order')
                            ->addActionLabel('Aggiungi Tranche')
                            ->defaultItems(2)
                            ->visible(fn (Forms\Get $get) => $get('payment_type') === 'tranches')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Auto-calculate total percentage
                                if (is_array($state)) {
                                    $total = collect($state)->sum('percentage');
                                    $set('total_percentage', $total);
                                }
                            }),

                        Forms\Components\Placeholder::make('total_percentage_display')
                            ->label('Totale Percentuali')
                            ->content(function (Forms\Get $get) {
                                $tranches = $get('tranches');
                                if (!is_array($tranches)) {
                                    return '0%';
                                }
                                $total = collect($tranches)->sum('percentage');
                                $color = $total == 100 ? 'green' : ($total < 100 ? 'orange' : 'red');
                                return "<span style='color: {$color}; font-weight: bold; font-size: 1.2em;'>{$total}%</span>" .
                                       ($total != 100 ? " ⚠️ Deve essere 100%" : " ✓");
                            })
                            ->visible(fn (Forms\Get $get) => $get('payment_type') === 'tranches'),
                    ]),

                Forms\Components\Section::make('Esempi Comuni')
                    ->schema([
                        Forms\Components\Placeholder::make('examples')
                            ->content('
                                **Pagamenti a Tranche:**
                                • **30/70**: 30% acconto, 70% a consegna
                                • **50/50**: 50% acconto, 50% a consegna
                                • **30/30/40**: 30% acconto, 30% mid-project, 40% finale

                                **Pagamenti Unici:**
                                • **30 giorni**: Pagamento dopo 30 giorni dalla fattura
                                • **60 giorni**: Pagamento dopo 60 giorni dalla fattura
                                • **2% 10 Netto 30**: 2% sconto se pagato entro 10 giorni, altrimenti 30
                            ')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tranches_display')
                    ->label('Configurazione')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $record->load('tranches');
                        if ($record->tranches->isEmpty()) {
                            return $record->days . ' giorni';
                        }
                        return $record->tranches->pluck('percentage')
                            ->map(fn($p) => number_format($p, 0) . '%')
                            ->implode(' / ');
                    })
                    ->color('primary'),

                Tables\Columns\TextColumn::make('tranches_count')
                    ->label('N° Tranche')
                    ->counts('tranches')
                    ->badge()
                    ->color('info')
                    ->default(1),

                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label('Sconto')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state . '%' : '-')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Attivo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quotations_count')
                    ->label('Preventivi')
                    ->counts('quotations')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('customers_count')
                    ->label('Clienti')
                    ->counts('customers')
                    ->badge()
                    ->color('warning')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->label('Solo Attivi')
                    ->toggle(),
                    
                Tables\Filters\Filter::make('has_discount')
                    ->label('Ha Sconto Pagamento Anticipato')
                    ->query(fn (Builder $query): Builder => $query->where('discount_percentage', '>', 0)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListPaymentTerms::route('/'),
            'create' => Pages\CreatePaymentTerm::route('/create'),
            'edit' => Pages\EditPaymentTerm::route('/{record}/edit'),
        ];
    }
}
