<?php

namespace App\Filament\Resources\QuotationResource\RelationManagers;

use App\Models\CompanyProfile;
use App\Models\QuotationItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    
    protected static ?string $title = 'Voci Quotazione';
    
    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('item_type')
                    ->label('Tipo Voce')
                    ->options(QuotationItem::getItemTypes())
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (Forms\Set $set, $state, $record) {
                        if (!$record) {
                            $item = new QuotationItem(['item_type' => $state]);
                            $item->populateByItemType();
                            
                            $set('description', $item->description ?? '');
                            $set('hourly_rate', $item->hourly_rate ?? null);
                            $set('quantity', $item->quantity ?? 1);
                            $set('unit_price', $item->unit_price ?? 0);
                            $set('is_from_inventory', $item->is_from_inventory ?? false);
                        }
                    }),
                    
                Forms\Components\TextInput::make('description')
                    ->label('Descrizione')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),

                // Campi per voci orarie (progettazione, assemblaggio)
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('hours')
                            ->label('Ore')
                            ->numeric()
                            ->step(0.25)
                            ->visible(fn (Forms\Get $get) => in_array($get('item_type'), ['design', 'assembly', 'housing_design']))
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $hourlyRate = $get('hourly_rate') ?? 0;
                                if ($state && $hourlyRate) {
                                    $set('unit_price', $state * $hourlyRate);
                                }
                            }),
                            
                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Tariffa Oraria')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->visible(fn (Forms\Get $get) => in_array($get('item_type'), ['design', 'assembly', 'housing_design']))
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $hours = $get('hours') ?? 0;
                                if ($state && $hours) {
                                    $set('unit_price', $state * $hours);
                                }
                            }),
                    ]),

                // Campi per materiali
                Forms\Components\TextInput::make('material_cost')
                    ->label('Costo Materiali')
                    ->numeric()
                    ->prefix('€')
                    ->step(0.01)
                    ->visible(fn (Forms\Get $get) => in_array($get('item_type'), ['electronics_materials', 'housing_materials']))
                    ->reactive()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        if ($state) {
                            $set('unit_price', $state);
                            $set('quantity', 1);
                        }
                    }),
                    
                Forms\Components\Toggle::make('is_from_inventory')
                    ->label('Da Inventario')
                    ->helperText('I costi verranno calcolati automaticamente dall\'inventario')
                    ->visible(fn (Forms\Get $get) => in_array($get('item_type'), ['electronics_materials', 'housing_materials'])),

                // Campi standard
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantità')
                            ->numeric()
                            ->default(1)
                            ->minValue(0.01)
                            ->step(0.01)
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $unitPrice = $get('unit_price') ?? 0;
                                $discountRate = $get('discount_rate') ?? 0;
                                
                                if ($state && $unitPrice) {
                                    $subtotal = $state * $unitPrice;
                                    $discountAmount = $subtotal * ($discountRate / 100);
                                    $set('total', $subtotal - $discountAmount);
                                }
                            }),
                            
                        Forms\Components\TextInput::make('unit_price')
                            ->label('Prezzo Unitario')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $quantity = $get('quantity') ?? 1;
                                $discountRate = $get('discount_rate') ?? 0;
                                
                                if ($state) {
                                    $subtotal = $quantity * $state;
                                    $discountAmount = $subtotal * ($discountRate / 100);
                                    $set('total', $subtotal - $discountAmount);
                                }
                            }),
                            
                        Forms\Components\TextInput::make('discount_rate')
                            ->label('Sconto %')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $quantity = $get('quantity') ?? 1;
                                $unitPrice = $get('unit_price') ?? 0;
                                
                                $subtotal = $quantity * $unitPrice;
                                $discountAmount = $subtotal * (($state ?? 0) / 100);
                                $set('discount_amount', $discountAmount);
                                $set('total', $subtotal - $discountAmount);
                            }),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Importo Sconto')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->disabled()
                            ->dehydrated(),
                            
                        Forms\Components\TextInput::make('total')
                            ->label('Totale')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label('Note')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('item_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => QuotationItem::getItemTypes()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'design', 'housing_design' => 'info',
                        'electronics_materials', 'housing_materials' => 'warning',
                        'pcb_production' => 'success',
                        'assembly' => 'primary',
                        'housing_production' => 'purple',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->limit(50),
                    
                Tables\Columns\TextColumn::make('hours')
                    ->label('Ore')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—')
                    ->visible(fn () => true),
                    
                Tables\Columns\TextColumn::make('hourly_rate')
                    ->label('€/h')
                    ->money('EUR')
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qnt.')
                    ->numeric(decimalPlaces: 2),
                    
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Prezzo Unit.')
                    ->money('EUR'),
                    
                Tables\Columns\TextColumn::make('discount_rate')
                    ->label('Sconto %')
                    ->formatStateUsing(fn ($state) => $state ? $state . '%' : '—')
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('total')
                    ->label('Totale')
                    ->money('EUR')
                    ->weight('bold'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('item_type')
                    ->label('Tipo Voce')
                    ->options(QuotationItem::getItemTypes()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Aggiungi Voce')
                    ->modalHeading('Aggiungi Voce alla Quotazione')
                    ->modalSubmitActionLabel('Aggiungi')
                    ->after(function ($record) {
                        // Ricalcola i totali della quotazione
                        $record->quotation->calculateTotals();
                        $record->quotation->save();
                        
                        Notification::make()
                            ->title('Voce aggiunta')
                            ->success()
                            ->send();
                    }),
                    
                // Quick add buttons per i tipi più comuni
                Tables\Actions\Action::make('quick_add_design')
                    ->label('+ Progettazione')
                    ->icon('heroicon-o-light-bulb')
                    ->color('info')
                    ->action(function () {
                        $profile = CompanyProfile::current();
                        
                        // Crea direttamente l'item con i valori predefiniti
                        $this->ownerRecord->items()->create([
                            'item_type' => 'design',
                            'description' => 'Ore di progettazione elettronica',
                            'hours' => 1,
                            'hourly_rate' => $profile->hourly_rate_design,
                            'quantity' => 1,
                            'unit_price' => $profile->hourly_rate_design,
                            'total' => $profile->hourly_rate_design,
                            'sort_order' => $this->ownerRecord->items()->max('sort_order') + 1,
                        ]);
                        
                        Notification::make()
                            ->title('Progettazione aggiunta')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('quick_add_materials')
                    ->label('+ Materiali')
                    ->icon('heroicon-o-cube')
                    ->color('warning')
                    ->action(function () {
                        $this->ownerRecord->items()->create([
                            'item_type' => 'electronics_materials',
                            'description' => 'Materiali elettronici (da BOM)',
                            'quantity' => 1,
                            'unit_price' => 0,
                            'total' => 0,
                            'is_from_inventory' => true,
                            'sort_order' => $this->ownerRecord->items()->max('sort_order') + 1,
                        ]);
                        
                        Notification::make()
                            ->title('Materiali aggiunti')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('quick_add_pcb')
                    ->label('+ PCB')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('success')
                    ->action(function () {
                        $profile = CompanyProfile::current();
                        
                        $this->ownerRecord->items()->create([
                            'item_type' => 'pcb_production',
                            'description' => "Produzione PCB + spedizione ({$profile->pcb_standard_quantity} schede)",
                            'quantity' => 1,
                            'unit_price' => $profile->pcb_standard_cost,
                            'total' => $profile->pcb_standard_cost,
                            'sort_order' => $this->ownerRecord->items()->max('sort_order') + 1,
                        ]);
                        
                        Notification::make()
                            ->title('PCB aggiunto')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifica Voce')
                    ->modalSubmitActionLabel('Salva')
                    ->after(function ($record) {
                        $record->quotation->calculateTotals();
                        $record->quotation->save();
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        $quotation = $record->quotation;
                        $quotation->calculateTotals();
                        $quotation->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            if ($records->isNotEmpty()) {
                                $quotation = $records->first()->quotation;
                                $quotation->calculateTotals();
                                $quotation->save();
                            }
                        }),
                ]),
            ]);
    }
}