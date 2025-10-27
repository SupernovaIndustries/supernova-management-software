<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceIssuedResource\Pages;
use App\Filament\Resources\InvoiceIssuedResource\RelationManagers;
use App\Models\InvoiceIssued;
use App\Services\PdfGeneratorService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class InvoiceIssuedResource extends Resource
{
    protected static ?string $model = InvoiceIssued::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Fatture Emesse';

    protected static ?string $modelLabel = 'Fattura Emessa';

    protected static ?string $pluralModelLabel = 'Fatture Emesse';

    protected static ?string $navigationGroup = 'Fatturazione';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Invoice Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Informazioni Base')
                            ->schema([
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('Numero Fattura')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Auto-generato: FATT-YYYY-XXX')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('customer_id')
                                    ->label('Cliente')
                                    ->relationship('customer', 'company_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $customer = \App\Models\Customer::find($state);
                                            if ($customer && $customer->payment_term_id) {
                                                $set('payment_term_id', $customer->payment_term_id);
                                            }
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make('project_id')
                                    ->label('Progetto')
                                    ->relationship('project', 'name', fn (Builder $query, Get $get) =>
                                        $get('customer_id')
                                            ? $query->where('customer_id', $get('customer_id'))
                                            : $query
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('quotation_id')
                                    ->label('Preventivo')
                                    ->relationship('quotation', 'number', fn (Builder $query, Get $get) =>
                                        $get('customer_id')
                                            ? $query->where('customer_id', $get('customer_id'))
                                            : $query
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('type')
                                    ->label('Tipo Fattura')
                                    ->options([
                                        'standard' => 'Standard',
                                        'advance_payment' => 'Acconto',
                                        'balance' => 'Saldo',
                                        'credit_note' => 'Nota di Credito',
                                    ])
                                    ->default('standard')
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1),

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

                                Forms\Components\Select::make('payment_term_id')
                                    ->label('Termini di Pagamento')
                                    ->relationship('paymentTerm', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(fn (Set $set) => $set('payment_term_tranche_id', null))
                                    ->columnSpan(1),

                                Forms\Components\Select::make('payment_term_tranche_id')
                                    ->label('Tranche di Pagamento')
                                    ->options(function (Get $get) {
                                        $paymentTermId = $get('payment_term_id');
                                        if (!$paymentTermId) {
                                            return [];
                                        }

                                        $paymentTerm = \App\Models\PaymentTerm::with('tranches')->find($paymentTermId);
                                        if (!$paymentTerm || $paymentTerm->tranches->isEmpty()) {
                                            return [];
                                        }

                                        // Get already invoiced tranches for this project/quotation
                                        $projectId = $get('project_id');
                                        $quotationId = $get('quotation_id');
                                        $recordId = $get('id');

                                        $invoicedTrancheIds = [];
                                        if ($projectId || $quotationId) {
                                            $query = \App\Models\InvoiceIssued::query()
                                                ->whereNotNull('payment_term_tranche_id');

                                            if ($projectId) {
                                                $query->where('project_id', $projectId);
                                            } else {
                                                $query->where('quotation_id', $quotationId);
                                            }

                                            if ($recordId) {
                                                $query->where('id', '!=', $recordId);
                                            }

                                            $invoicedTrancheIds = $query->pluck('payment_term_tranche_id')->toArray();
                                        }

                                        return $paymentTerm->tranches->mapWithKeys(function ($tranche) use ($invoicedTrancheIds) {
                                            $label = "{$tranche->name} ({$tranche->percentage}%)";
                                            if (in_array($tranche->id, $invoicedTrancheIds)) {
                                                $label .= " ✓ Già fatturata";
                                            }
                                            return [$tranche->id => $label];
                                        })->toArray();
                                    })
                                    ->visible(fn (Get $get) => $get('payment_term_id') &&
                                        \App\Models\PaymentTerm::find($get('payment_term_id'))?->tranches()->exists())
                                    ->helperText('Seleziona quale tranche stai fatturando (es. Acconto 30%, Saldo 70%)')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        if (!$state) return;

                                        // Auto-update type based on tranche
                                        $tranche = \App\Models\PaymentTermTranche::find($state);
                                        if ($tranche) {
                                            if (stripos($tranche->name, 'acconto') !== false) {
                                                $set('type', 'advance_payment');
                                            } elseif (stripos($tranche->name, 'saldo') !== false) {
                                                $set('type', 'balance');
                                            }

                                            $set('payment_percentage', $tranche->percentage);
                                        }
                                    })
                                    ->columnSpan(2),

                                Forms\Components\Placeholder::make('tranche_info')
                                    ->label('')
                                    ->content(function (Get $get) {
                                        $trancheId = $get('payment_term_tranche_id');
                                        if (!$trancheId) {
                                            return '';
                                        }

                                        $tranche = \App\Models\PaymentTermTranche::find($trancheId);
                                        if (!$tranche) {
                                            return '';
                                        }

                                        $projectId = $get('project_id');
                                        $quotationId = $get('quotation_id');

                                        if (!$projectId && !$quotationId) {
                                            return "**{$tranche->name}**: {$tranche->percentage}% del totale";
                                        }

                                        // Get project/quotation total
                                        $total = 0;
                                        if ($projectId) {
                                            $project = \App\Models\Project::find($projectId);
                                            $total = $project?->budget ?? 0;
                                        } else {
                                            $quotation = \App\Models\Quotation::find($quotationId);
                                            $total = $quotation?->total ?? 0;
                                        }

                                        $trancheAmount = $total * ($tranche->percentage / 100);

                                        return "**{$tranche->name}**: {$tranche->percentage}% di € " .
                                               number_format($total, 2, ',', '.') .
                                               " = **€ " . number_format($trancheAmount, 2, ',', '.') . "**";
                                    })
                                    ->visible(fn (Get $get) => $get('payment_term_tranche_id') !== null)
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Importi')
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotale')
                                    ->content(fn ($record) => $record ? '€ ' . number_format($record->subtotal ?? 0, 2, ',', '.') : '€ 0,00')
                                    ->helperText('Calcolato automaticamente dalle righe fattura'),

                                Forms\Components\TextInput::make('tax_rate')
                                    ->label('Aliquota IVA (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(22)
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Sconto (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->default(0)
                                    ->reactive()
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('tax_amount_display')
                                    ->label('Importo IVA')
                                    ->content(fn ($record) => $record ? '€ ' . number_format($record->tax_amount ?? 0, 2, ',', '.') : '€ 0,00')
                                    ->helperText('Calcolato automaticamente'),

                                Forms\Components\Placeholder::make('total_display')
                                    ->label('Totale Fattura')
                                    ->content(fn ($record) => $record ? '€ ' . number_format($record->total ?? 0, 2, ',', '.') : '€ 0,00')
                                    ->helperText('Subtotale - Sconto + IVA'),

                                Forms\Components\Select::make('payment_stage')
                                    ->label('Fase Pagamento')
                                    ->options([
                                        'deposit' => 'Acconto',
                                        'balance' => 'Saldo',
                                        'full' => 'Pagamento Completo',
                                    ])
                                    ->visible(fn (Get $get) => in_array($get('type'), ['advance_payment', 'balance']))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('payment_percentage')
                                    ->label('Percentuale Pagamento (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->visible(fn (Get $get) => in_array($get('type'), ['advance_payment', 'balance']))
                                    ->columnSpan(1),

                                Forms\Components\Select::make('related_invoice_id')
                                    ->label('Fattura Correlata')
                                    ->relationship('relatedInvoice', 'invoice_number')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Per collegare acconto e saldo')
                                    ->visible(fn (Get $get) => $get('type') === 'balance')
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Righe Fattura')
                            ->schema([
                                Forms\Components\Placeholder::make('items_help')
                                    ->label('Gestione Righe')
                                    ->content('Le righe fattura possono essere gestite dopo aver salvato la fattura, nella sezione "Righe" in basso.')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Pagamento')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Stato Fattura')
                                    ->options([
                                        'draft' => 'Bozza',
                                        'sent' => 'Inviata',
                                        'paid' => 'Pagata',
                                        'overdue' => 'Scaduta',
                                        'cancelled' => 'Annullata',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->columnSpan(1),

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
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Note')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label('Note Cliente')
                                    ->helperText('Visibili nella fattura PDF')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('internal_notes')
                                    ->label('Note Interne')
                                    ->helperText('Solo uso interno, non visibili al cliente')
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

                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('project.name')
                    ->label('Progetto')
                    ->searchable()
                    ->limit(20)
                    ->toggleable()
                    ->placeholder('-'),

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

                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'warning',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Bozza',
                        'sent' => 'Inviata',
                        'paid' => 'Pagata',
                        'overdue' => 'Scaduta',
                        'cancelled' => 'Annullata',
                        default => $state,
                    }),

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
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'standard' => 'Standard',
                        'advance_payment' => 'Acconto',
                        'balance' => 'Saldo',
                        'credit_note' => 'Nota Credito',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'draft' => 'Bozza',
                        'sent' => 'Inviata',
                        'paid' => 'Pagata',
                        'overdue' => 'Scaduta',
                        'cancelled' => 'Annullata',
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Stato Pagamento')
                    ->options([
                        'unpaid' => 'Non Pagata',
                        'partial' => 'Parziale',
                        'paid' => 'Pagata',
                    ]),

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
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->status === 'draft'),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Segna come Pagata')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->payment_status !== 'paid')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'payment_status' => 'paid',
                            'status' => 'paid',
                            'amount_paid' => $record->total,
                            'paid_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Fattura aggiornata')
                            ->body('La fattura è stata segnata come pagata.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('generate_pdf')
                    ->label('Genera PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (InvoiceIssued $record) {
                        $pdfService = app(PdfGeneratorService::class);

                        try {
                            // Generate PDF and upload to Nextcloud
                            $pdfService->generateInvoiceIssuedPdf($record, uploadToNextcloud: true);

                            Notification::make()
                                ->title('PDF Generato')
                                ->body("PDF generato e caricato su Nextcloud per fattura {$record->invoice_number}")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore Generazione PDF')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Genera PDF Fattura')
                    ->modalDescription('Verrà generato il PDF e caricato automaticamente su Nextcloud.')
                    ->modalSubmitActionLabel('Genera'),

                Tables\Actions\Action::make('download_pdf')
                    ->label('Scarica PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function (InvoiceIssued $record) {
                        $pdfService = app(PdfGeneratorService::class);
                        return $pdfService->downloadInvoiceIssuedPdf($record);
                    })
                    ->visible(fn (InvoiceIssued $record) => $record->pdf_generated_at !== null),

                Tables\Actions\Action::make('send_email')
                    ->label('Invia Email')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status !== 'draft')
                    ->action(function ($record) {
                        // TODO: Implement email sending
                        Notification::make()
                            ->title('Email Sending')
                            ->body('Funzionalità in sviluppo - invio email al cliente')
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('generate_pdfs')
                        ->label('Genera PDF Selezionate')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($records) {
                            Notification::make()
                                ->title('Bulk PDF Generation')
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
            'index' => Pages\ListInvoicesIssued::route('/'),
            'create' => Pages\CreateInvoiceIssued::route('/create'),
            'edit' => Pages\EditInvoiceIssued::route('/{record}/edit'),
        ];
    }
}
