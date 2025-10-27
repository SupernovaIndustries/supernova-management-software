<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceReceivedResource\Pages;
use App\Filament\Resources\InvoiceReceivedResource\RelationManagers;
use App\Models\InvoiceReceived;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class InvoiceReceivedResource extends Resource
{
    protected static ?string $model = InvoiceReceived::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationLabel = 'Fatture Ricevute';

    protected static ?string $modelLabel = 'Fattura Ricevuta';

    protected static ?string $pluralModelLabel = 'Fatture Ricevute';

    protected static ?string $navigationGroup = 'Fatturazione';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Invoice Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Informazioni Fornitore')
                            ->schema([
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('Numero Fattura Fornitore')
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('supplier_id')
                                    ->label('Fornitore')
                                    ->relationship('supplier', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $supplier = \App\Models\Supplier::find($state);
                                            if ($supplier) {
                                                $set('supplier_name', $supplier->name);
                                                $set('supplier_vat', $supplier->vat_number);
                                            }
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('supplier_name')
                                    ->label('Nome Fornitore')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('supplier_vat')
                                    ->label('P.IVA Fornitore')
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('type')
                                    ->label('Tipo Fattura')
                                    ->options([
                                        'purchase' => 'Acquisto Componenti',
                                        'customs' => 'Dogana',
                                        'equipment' => 'Attrezzatura',
                                        'general' => 'Generale',
                                        'restock' => 'Rifornimento Magazzino',
                                    ])
                                    ->default('purchase')
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('category')
                                    ->label('Categoria')
                                    ->options([
                                        'components' => 'Componenti Elettronici',
                                        'equipment' => 'Attrezzature',
                                        'services' => 'Servizi',
                                        'customs' => 'Spese Doganali',
                                        'general' => 'Spese Generali',
                                    ])
                                    ->default('components')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('description')
                                    ->label('Descrizione Specifica')
                                    ->placeholder('es. Biglietti aerei Milano-Tokyo, Spese doganali componenti...')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Dati Fattura')
                            ->schema([
                                Forms\Components\DatePicker::make('issue_date')
                                    ->label('Data Emissione')
                                    ->required()
                                    ->default(now())
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Data Scadenza')
                                    ->required()
                                    ->default(fn () => now()->addDays(30))
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('received_date')
                                    ->label('Data Ricezione Fattura')
                                    ->default(now())
                                    ->columnSpan(1),

                                Forms\Components\Select::make('currency')
                                    ->label('Valuta')
                                    ->options([
                                        'EUR' => 'Euro (EUR)',
                                        'USD' => 'Dollaro USA (USD)',
                                        'GBP' => 'Sterlina (GBP)',
                                    ])
                                    ->default('EUR')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotale (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                        $taxAmount = $get('tax_amount') ?? 0;
                                        $set('total', ($state ?? 0) + $taxAmount);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('tax_amount')
                                    ->label('Importo IVA (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                        $subtotal = $get('subtotal') ?? 0;
                                        $set('total', $subtotal + ($state ?? 0));
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('total')
                                    ->label('Totale Fattura (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Progetto/Cliente')
                            ->schema([
                                Forms\Components\Select::make('project_id')
                                    ->label('Progetto (Opzionale)')
                                    ->relationship('project', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Se la fattura è per un progetto specifico')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $project = \App\Models\Project::find($state);
                                            if ($project) {
                                                $set('customer_id', $project->customer_id);
                                            }
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make('customer_id')
                                    ->label('Cliente Finale (Opzionale)')
                                    ->relationship('customer', 'company_name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Se la fattura è per un progetto cliente')
                                    ->disabled(fn (Get $get) => $get('project_id') !== null)
                                    ->columnSpan(1),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Righe Fattura')
                            ->schema([
                                Forms\Components\Placeholder::make('items_help')
                                    ->label('Gestione Righe')
                                    ->content('Le righe fattura possono essere gestite dopo aver salvato, nella sezione "Righe" in basso.')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Pagamento')
                            ->schema([
                                Forms\Components\Select::make('payment_status')
                                    ->label('Stato Pagamento')
                                    ->options([
                                        'unpaid' => 'Non Pagata',
                                        'partial' => 'Parzialmente Pagata',
                                        'paid' => 'Pagata',
                                    ])
                                    ->default('unpaid')
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('amount_paid')
                                    ->label('Importo Pagato (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->default(0)
                                    ->visible(fn (Get $get) => in_array($get('payment_status'), ['partial', 'paid']))
                                    ->columnSpan(1),

                                Forms\Components\DateTimePicker::make('paid_at')
                                    ->label('Data/Ora Pagamento')
                                    ->visible(fn (Get $get) => in_array($get('payment_status'), ['partial', 'paid']))
                                    ->columnSpan(1),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Metodo di Pagamento')
                                    ->options([
                                        'bank_transfer' => 'Bonifico Bancario',
                                        'credit_card' => 'Carta di Credito',
                                        'cash' => 'Contanti',
                                        'check' => 'Assegno',
                                    ])
                                    ->visible(fn (Get $get) => in_array($get('payment_status'), ['partial', 'paid']))
                                    ->columnSpan(1),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Tracciabilità')
                            ->schema([
                                Forms\Components\Placeholder::make('tracking_help')
                                    ->label('Collegamento Componenti')
                                    ->content(function ($record) {
                                        if (!$record) {
                                            return 'Salvare la fattura per collegare i componenti';
                                        }

                                        $mappingsCount = $record->componentMappings()->count();
                                        $movementsCount = $record->inventoryMovements()->count();

                                        return "Componenti collegati: {$mappingsCount} | Movimenti magazzino: {$movementsCount}";
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Note')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Numero')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornitore')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'purchase' => 'success',
                        'customs' => 'warning',
                        'equipment' => 'info',
                        'general' => 'gray',
                        'restock' => 'primary',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'purchase' => 'Acquisto',
                        'customs' => 'Dogana',
                        'equipment' => 'Attrezzatura',
                        'general' => 'Generale',
                        'restock' => 'Rifornimento',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'components' => 'Componenti',
                        'equipment' => 'Attrezzature',
                        'services' => 'Servizi',
                        'customs' => 'Dogana',
                        'general' => 'Generale',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Data Emissione')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : 'gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Totale')
                    ->money('EUR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unpaid' => 'Non Pagata',
                        'partial' => 'Parziale',
                        'paid' => 'Pagata',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('project.name')
                    ->label('Progetto')
                    ->searchable()
                    ->limit(20)
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Fornitore')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'purchase' => 'Acquisto',
                        'customs' => 'Dogana',
                        'equipment' => 'Attrezzatura',
                        'general' => 'Generale',
                        'restock' => 'Rifornimento',
                    ]),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options([
                        'components' => 'Componenti',
                        'equipment' => 'Attrezzature',
                        'services' => 'Servizi',
                        'customs' => 'Dogana',
                        'general' => 'Generale',
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Stato Pagamento')
                    ->options([
                        'unpaid' => 'Non Pagata',
                        'partial' => 'Parziale',
                        'paid' => 'Pagata',
                    ]),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Progetto')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('overdue')
                    ->label('Solo Scadute')
                    ->query(fn (Builder $query): Builder => $query->overdue()),

                Tables\Filters\Filter::make('issue_date')
                    ->form([
                        Forms\Components\DatePicker::make('issue_from')
                            ->label('Data Emissione Da'),
                        Forms\Components\DatePicker::make('issue_until')
                            ->label('Data Emissione A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issue_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['issue_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Segna come Pagata')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->payment_status !== 'paid')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'payment_status' => 'paid',
                            'amount_paid' => $record->total,
                            'paid_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Fattura aggiornata')
                            ->body('La fattura è stata segnata come pagata.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('upload_pdf')
                    ->label('Upload PDF')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->action(function ($record) {
                        // TODO: Implement PDF upload to Nextcloud
                        Notification::make()
                            ->title('Upload PDF')
                            ->body('Funzionalità in sviluppo - upload su Nextcloud')
                            ->info()
                            ->send();
                    }),

                Tables\Actions\Action::make('link_components')
                    ->label('Collega Componenti')
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->type, ['purchase', 'restock']))
                    ->action(function ($record) {
                        // TODO: Implement component linking
                        Notification::make()
                            ->title('Link Componenti')
                            ->body('Funzionalità in sviluppo - collegamento componenti')
                            ->info()
                            ->send();
                    }),

                Tables\Actions\Action::make('create_inventory_movement')
                    ->label('Crea Movimento Magazzino')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('primary')
                    ->visible(fn ($record) => in_array($record->type, ['purchase', 'restock']))
                    ->action(function ($record) {
                        // TODO: Implement inventory movement creation
                        Notification::make()
                            ->title('Movimento Magazzino')
                            ->body('Funzionalità in sviluppo - creazione movimento IN')
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('mark_paid_bulk')
                        ->label('Segna come Pagate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update([
                                'payment_status' => 'paid',
                                'amount_paid' => fn ($record) => $record->total,
                                'paid_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Fatture aggiornate')
                                ->body('Le fatture selezionate sono state segnate come pagate.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Esporta CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            // TODO: Implement CSV export
                            Notification::make()
                                ->title('Esportazione CSV')
                                ->body('Funzionalità in sviluppo')
                                ->info()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoicesReceived::route('/'),
            'create' => Pages\CreateInvoiceReceived::route('/create'),
            'edit' => Pages\EditInvoiceReceived::route('/{record}/edit'),
        ];
    }
}
