<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Resources\QuotationResource\RelationManagers;
use App\Models\Quotation;
use App\Services\DocumentService;
use App\Services\NextcloudService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Quotation Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\Toggle::make('is_manual_entry')
                                    ->label('Manual Entry Mode')
                                    ->helperText('Enable to manually input quotation number and other auto-generated fields')
                                    ->reactive()
                                    ->columnSpanFull(),
                                    
                                Forms\Components\TextInput::make('number')
                                    ->label('Quotation Number')
                                    ->disabled(fn (Get $get) => !$get('is_manual_entry'))
                                    ->dehydrated(fn (Get $get) => $get('is_manual_entry'))
                                    ->placeholder(fn (Get $get) => $get('is_manual_entry') ? 'Enter manually (e.g., 001-24)' : 'Auto-generated (007-25, 008-25, etc.)')
                                    ->required(fn (Get $get) => $get('is_manual_entry')),
                                    
                                Forms\Components\Select::make('customer_id')
                                    ->relationship('customer', 'company_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Customer Company')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $customer = \App\Models\Customer::find($state);
                                            if ($customer && $customer->payment_term_id) {
                                                $set('payment_term_id', $customer->payment_term_id);
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\Select::make('project_id')
                                    ->relationship('project', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->label('Related Project (DEPRECATED - Use Linked Projects instead)')
                                    ->helperText('⚠️ This field is deprecated. Use "Linked Projects" in Project Management tab.')
                                    ->disabled()
                                    ->dehydrated(false),
                                    
                                Forms\Components\Select::make('user_id')
                                    ->relationship('user', 'name')
                                    ->default(fn () => auth()->id())
                                    ->required()
                                    ->label('Assigned To')
                                    ->disabled(fn (Get $get) => !$get('is_manual_entry'))
                                    ->dehydrated(),
                                    
                                Forms\Components\Select::make('status')
                                    ->required()
                                    ->options([
                                        'draft' => 'Draft',
                                        'sent' => 'Sent',
                                        'accepted' => 'Accepted',
                                        'rejected' => 'Rejected',
                                        'expired' => 'Expired',
                                    ])
                                    ->default('draft'),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Dates & Currency')
                            ->schema([
                                Forms\Components\DatePicker::make('date')
                                    ->label('Quotation Date')
                                    ->required()
                                    ->default(now()),
                                    
                                Forms\Components\DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->required()
                                    ->default(now()->addDays(30)),
                                    
                                Forms\Components\TextInput::make('currency')
                                    ->required()
                                    ->maxLength(3)
                                    ->default('EUR'),
                                    
                                Forms\Components\DateTimePicker::make('sent_at')
                                    ->label('Sent At')
                                    ->nullable(),
                                    
                                Forms\Components\DateTimePicker::make('accepted_at')
                                    ->label('Accepted At')
                                    ->nullable(),
                                    
                                Forms\Components\DateTimePicker::make('rejected_at')
                                    ->label('Rejected At')
                                    ->nullable(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Pricing')
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_info')
                                    ->label('Subtotale')
                                    ->content(fn ($record) => $record ? '€ ' . number_format($record->subtotal ?? 0, 2) : '€ 0,00 - Verrà calcolato dalle voci')
                                    ->helperText('Calcolato automaticamente dalla somma delle voci della quotazione'),
                                    
                                Forms\Components\TextInput::make('discount_rate')
                                    ->label('Sconto %')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->helperText('I totali verranno ricalcolati automaticamente'),
                                    
                                Forms\Components\Placeholder::make('discount_amount_info')
                                    ->label('Importo Sconto')
                                    ->content(fn ($record) => $record ? '€ ' . number_format($record->discount_amount ?? 0, 2) : '€ 0,00')
                                    ->helperText('Calcolato automaticamente dal subtotale e percentuale sconto'),
                                    
                                Forms\Components\TextInput::make('tax_rate')
                                    ->label('IVA %')
                                    ->required()
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(22)
                                    ->helperText('Percentuale IVA da applicare'),
                                    
                                Forms\Components\Placeholder::make('tax_amount_info')
                                    ->label('Importo IVA')
                                    ->content(fn ($record) => $record ? '€ ' . number_format($record->tax_amount ?? 0, 2) : '€ 0,00')
                                    ->helperText('Calcolato automaticamente'),
                                    
                                Forms\Components\Placeholder::make('total_info')
                                    ->label('Totale Complessivo')
                                    ->content(fn ($record) => $record ? '€ ' . number_format($record->total ?? 0, 2) : '€ 0,00')
                                    ->helperText('Subtotale - Sconto + IVA'),
                                    
                                Forms\Components\TextInput::make('boards_quantity')
                                    ->label('Number of Boards to Produce')
                                    ->numeric()
                                    ->helperText('Number of PCB boards for this quotation (optional)'),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Terms & Notes')
                            ->schema([
                                Forms\Components\Select::make('payment_term_id')
                                    ->label('Payment Terms')
                                    ->relationship('paymentTerm', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Net 30, Net 60'),
                                        Forms\Components\Textarea::make('description')
                                            ->placeholder('Detailed description of payment terms'),
                                        Forms\Components\TextInput::make('days')
                                            ->label('Net Days')
                                            ->numeric()
                                            ->default(30)
                                            ->required(),
                                        Forms\Components\TextInput::make('discount_percentage')
                                            ->label('Early Payment Discount %')
                                            ->numeric()
                                            ->step(0.01)
                                            ->default(0),
                                        Forms\Components\TextInput::make('discount_days')
                                            ->label('Discount Days')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Days within which discount applies'),
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $paymentTerm = \App\Models\PaymentTerm::find($state);
                                            if ($paymentTerm) {
                                                $set('payment_terms', $paymentTerm->name);
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\Select::make('projects')
                                    ->label('Linked Projects')
                                    ->relationship('projects', 'name', modifyQueryUsing: fn ($query) => $query->select('projects.id', 'projects.name', 'projects.code'))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Link this quotation to one or more projects for automatic budget calculation'),
                                    
                                Forms\Components\TextInput::make('payment_terms')
                                    ->label('Payment Terms Text')
                                    ->maxLength(255)
                                    ->helperText('Auto-filled from payment term selection above')
                                    ->disabled()
                                    ->dehydrated(),
                                    
                                Forms\Components\Textarea::make('notes')
                                    ->label('Internal Notes')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                    
                                Forms\Components\Textarea::make('terms_conditions')
                                    ->label('Terms & Conditions')
                                    ->rows(4)
                                    ->default('1. Validity: 30 days from quotation date\n2. Payment: As per selected payment terms\n3. Delivery: 2-4 weeks from order confirmation\n4. Prices exclude VAT')
                                    ->columnSpanFull(),
                                    
                                Forms\Components\TextInput::make('rejection_reason')
                                    ->label('Rejection Reason')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get) => $get('status') === 'rejected'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Voci Quotazione')
                            ->schema([
                                Forms\Components\Placeholder::make('items_info')
                                    ->label('Voci della Quotazione')
                                    ->content('
                                        **Aggiungi le voci del preventivo:**
                                        • Puoi aggiungere voci durante la creazione del preventivo
                                        • I totali vengono calcolati automaticamente in tempo reale
                                        • Il PDF viene generato automaticamente al salvataggio
                                    ')
                                    ->columnSpanFull(),

                                Forms\Components\Repeater::make('items')
                                    ->relationship('items')
                                    ->schema([
                                        Forms\Components\Select::make('item_type')
                                            ->label('Tipo Voce')
                                            ->options(\App\Models\QuotationItem::getItemTypes())
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                if ($state) {
                                                    $item = new \App\Models\QuotationItem(['item_type' => $state]);
                                                    $item->populateByItemType();

                                                    $set('description', $item->description ?? '');
                                                    $set('hourly_rate', $item->hourly_rate ?? null);
                                                    $set('quantity', $item->quantity ?? 1);
                                                    $set('unit_price', $item->unit_price ?? 0);
                                                    $set('is_from_inventory', $item->is_from_inventory ?? false);
                                                }
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('description')
                                            ->label('Descrizione')
                                            ->required()
                                            ->maxLength(500)
                                            ->helperText('Descrizione tecnica della voce (auto-popolata)')
                                            ->columnSpan(2),

                                        Forms\Components\Textarea::make('custom_description')
                                            ->label('Descrizione Personalizzata')
                                            ->rows(3)
                                            ->helperText('Descrizione professionale che apparirà nel PDF (se compilata, sostituisce la descrizione tecnica)')
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('hours')
                                            ->label('Ore')
                                            ->numeric()
                                            ->step(0.25)
                                            ->visible(fn (Get $get) => in_array($get('item_type'), ['design', 'assembly', 'housing_design']))
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $hourlyRate = $get('hourly_rate') ?? 0;
                                                if ($state && $hourlyRate) {
                                                    $unitPrice = $state * $hourlyRate;
                                                    $set('unit_price', $unitPrice);
                                                    $quantity = $get('quantity') ?? 1;
                                                    $discountRate = $get('discount_rate') ?? 0;
                                                    $subtotal = $quantity * $unitPrice;
                                                    $discountAmount = $subtotal * ($discountRate / 100);
                                                    $set('total', $subtotal - $discountAmount);
                                                }
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('hourly_rate')
                                            ->label('€/h')
                                            ->numeric()
                                            ->prefix('€')
                                            ->step(0.01)
                                            ->visible(fn (Get $get) => in_array($get('item_type'), ['design', 'assembly', 'housing_design']))
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $hours = $get('hours') ?? 0;
                                                if ($state && $hours) {
                                                    $unitPrice = $state * $hours;
                                                    $set('unit_price', $unitPrice);
                                                    $quantity = $get('quantity') ?? 1;
                                                    $discountRate = $get('discount_rate') ?? 0;
                                                    $subtotal = $quantity * $unitPrice;
                                                    $discountAmount = $subtotal * ($discountRate / 100);
                                                    $set('total', $subtotal - $discountAmount);
                                                }
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('material_cost')
                                            ->label('Costo Materiali')
                                            ->numeric()
                                            ->prefix('€')
                                            ->step(0.01)
                                            ->visible(fn (Get $get) => in_array($get('item_type'), ['electronics_materials', 'housing_materials']))
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                if ($state) {
                                                    $set('unit_price', $state);
                                                    $set('quantity', 1);
                                                    $set('total', $state);
                                                }
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Quantità')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $unitPrice = $get('unit_price') ?? 0;
                                                $discountRate = $get('discount_rate') ?? 0;

                                                if ($state && $unitPrice) {
                                                    $subtotal = $state * $unitPrice;
                                                    $discountAmount = $subtotal * ($discountRate / 100);
                                                    $set('total', $subtotal - $discountAmount);
                                                }
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Prezzo Unit.')
                                            ->numeric()
                                            ->prefix('€')
                                            ->step(0.01)
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $quantity = $get('quantity') ?? 1;
                                                $discountRate = $get('discount_rate') ?? 0;

                                                if ($state) {
                                                    $subtotal = $quantity * $state;
                                                    $discountAmount = $subtotal * ($discountRate / 100);
                                                    $set('total', $subtotal - $discountAmount);
                                                }
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('discount_rate')
                                            ->label('Sconto %')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->default(0)
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $quantity = $get('quantity') ?? 1;
                                                $unitPrice = $get('unit_price') ?? 0;

                                                $subtotal = $quantity * $unitPrice;
                                                $discountAmount = $subtotal * (($state ?? 0) / 100);
                                                $set('discount_amount', $discountAmount);
                                                $set('total', $subtotal - $discountAmount);
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('total')
                                            ->label('Totale')
                                            ->numeric()
                                            ->prefix('€')
                                            ->step(0.01)
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Note')
                                            ->rows(2)
                                            ->columnSpan(3),
                                    ])
                                    ->columns(3)
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable()
                                    ->itemLabel(fn (array $state): ?string => $state['description'] ?? 'Nuova voce')
                                    ->addActionLabel('Aggiungi Voce')
                                    ->defaultItems(0)
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('totals_preview')
                                    ->label('Riepilogo Totali')
                                    ->content(function (Get $get) {
                                        $items = $get('items') ?? [];
                                        $itemsSubtotal = 0;

                                        foreach ($items as $item) {
                                            $itemsSubtotal += $item['total'] ?? 0;
                                        }

                                        $discountRate = $get('discount_rate') ?? 0;
                                        $taxRate = $get('tax_rate') ?? 22;

                                        $discountAmount = $itemsSubtotal * ($discountRate / 100);
                                        $taxableAmount = $itemsSubtotal - $discountAmount;
                                        $taxAmount = $taxableAmount * ($taxRate / 100);
                                        $total = $taxableAmount + $taxAmount;

                                        return "
                                            **Subtotale voci:** € " . number_format($itemsSubtotal, 2, ',', '.') . "
                                            **Sconto ({$discountRate}%):** € " . number_format($discountAmount, 2, ',', '.') . "
                                            **Imponibile:** € " . number_format($taxableAmount, 2, ',', '.') . "
                                            **IVA ({$taxRate}%):** € " . number_format($taxAmount, 2, ',', '.') . "
                                            **TOTALE:** € " . number_format($total, 2, ',', '.') . "
                                        ";
                                    })
                                    ->reactive()
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('PDF & Documents')
                            ->schema([
                                Forms\Components\Placeholder::make('pdf_info')
                                    ->label('Gestione PDF Preventivo')
                                    ->content('
                                        **Opzioni:**
                                        • **Auto-generazione**: Il PDF viene generato automaticamente al salvataggio se hai aggiunto delle voci
                                        • **Upload manuale**: Carica un PDF esistente per preventivi vecchi
                                        • I PDF vengono automaticamente organizzati in cartelle basate sullo stato (Bozze/Inviati/Accettati)
                                        • Quando il preventivo viene accettato, viene automaticamente copiato nella cartella del progetto
                                    ')
                                    ->columnSpanFull(),

                                Forms\Components\FileUpload::make('pdf_path')
                                    ->label('Upload PDF Preventivo (Manuale)')
                                    ->disk('local')
                                    ->directory('quotations/pdfs')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->maxSize(10240) // 10MB
                                    ->helperText('Carica un PDF esistente per preventivi vecchi. Lascia vuoto per auto-generazione.')
                                    ->afterStateUpdated(fn (Set $set) => $set('pdf_uploaded_manually', true))
                                    ->reactive()
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('pdf_status')
                                    ->label('Stato PDF')
                                    ->content(function ($record) {
                                        if (!$record) {
                                            return 'Nessun PDF disponibile';
                                        }

                                        if ($record->pdf_path) {
                                            $status = $record->pdf_uploaded_manually ? '📤 Caricato manualmente' : '🤖 Generato automaticamente';
                                            $date = $record->pdf_generated_at ? ' il ' . $record->pdf_generated_at->format('d/m/Y H:i') : '';
                                            $location = $record->nextcloud_path ?? 'Non ancora su Nextcloud';

                                            return "**{$status}**{$date}\n\n📁 Percorso: `{$location}`";
                                        }

                                        return '⏳ PDF sarà generato automaticamente quando cambi lo stato da "Draft"';
                                    })
                                    ->visible(fn ($record) => $record !== null)
                                    ->columnSpanFull(),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('generate_pdf')
                                        ->label('Genera PDF Ora')
                                        ->icon('heroicon-o-document-arrow-down')
                                        ->color('success')
                                        ->visible(fn ($record) => $record && !$record->pdf_path)
                                        ->requiresConfirmation()
                                        ->action(function ($record) {
                                            try {
                                                $documentService = app(DocumentService::class);
                                                $pdfPath = $documentService->generateQuotationPdf($record);

                                                $nextcloudService = app(\App\Services\NextcloudService::class);
                                                $nextcloudService->uploadQuotation($record, $pdfPath);

                                                $customerPath = $nextcloudService->getCustomerBasePath($record->customer);
                                                $statusFolder = match($record->status) {
                                                    'draft' => 'Bozze',
                                                    'sent' => 'Inviati',
                                                    'accepted' => 'Accettati',
                                                    'rejected' => 'Rifiutati',
                                                    'expired' => 'Scaduti',
                                                    default => 'Bozze',
                                                };

                                                $record->update([
                                                    'pdf_path' => $pdfPath,
                                                    'pdf_generated_at' => now(),
                                                    'nextcloud_path' => "{$customerPath}/01_Preventivi/{$statusFolder}/preventivo-{$record->number}.pdf"
                                                ]);

                                                Notification::make()
                                                    ->title('PDF Generato!')
                                                    ->success()
                                                    ->body("PDF generato e caricato su Nextcloud in {$statusFolder}")
                                                    ->send();
                                            } catch (\Exception $e) {
                                                Notification::make()
                                                    ->title('Errore')
                                                    ->danger()
                                                    ->body('Errore durante la generazione del PDF: ' . $e->getMessage())
                                                    ->send();
                                            }
                                        }),

                                    Forms\Components\Actions\Action::make('download_pdf')
                                        ->label('Scarica PDF')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('primary')
                                        ->visible(fn ($record) => $record && $record->pdf_path && file_exists($record->pdf_path))
                                        ->action(function ($record) {
                                            if ($record->pdf_path && file_exists($record->pdf_path)) {
                                                return response()->download($record->pdf_path, "preventivo-{$record->number}.pdf");
                                            }

                                            Notification::make()
                                                ->title('PDF non trovato')
                                                ->danger()
                                                ->body('Il file PDF non esiste. Genera prima il PDF.')
                                                ->send();
                                        }),
                                ])
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
                Tables\Columns\TextColumn::make('number')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                    
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->placeholder('No project'),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'primary',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'expired' => 'warning',
                        default => 'secondary',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('boards_quantity')
                    ->label('Boards Qty')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('tax_amount')
                    ->label('Tax')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not sent')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Accepted')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_pdf')
                    ->label('Genera/Rigenera PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Genera PDF Preventivo')
                    ->modalDescription('Il PDF verrà generato e caricato su Nextcloud nella cartella corretta in base allo stato del preventivo.')
                    ->action(function (Quotation $record) {
                        $documentService = app(DocumentService::class);
                        $nextcloudService = app(NextcloudService::class);

                        try {
                            // Generate PDF locally
                            $pdfPath = $documentService->generateQuotationPdf($record);

                            if (!$pdfPath || !file_exists($pdfPath)) {
                                throw new \Exception('Failed to generate PDF file');
                            }

                            // Upload to Nextcloud
                            $uploaded = $nextcloudService->uploadQuotation($record, $pdfPath);

                            if ($uploaded) {
                                $customerPath = $nextcloudService->getCustomerBasePath($record->customer);
                                $statusFolder = match($record->status) {
                                    'draft' => 'Bozze',
                                    'sent' => 'Inviati',
                                    'accepted' => 'Accettati',
                                    'rejected' => 'Rifiutati',
                                    'expired' => 'Scaduti',
                                    default => 'Bozze',
                                };

                                $nextcloudPath = "{$customerPath}/01_Preventivi/{$statusFolder}/preventivo-{$record->number}.pdf";

                                $record->update([
                                    'pdf_path' => $pdfPath,
                                    'pdf_generated_at' => now(),
                                    'nextcloud_path' => $nextcloudPath
                                ]);

                                Notification::make()
                                    ->title('PDF Generato e Caricato')
                                    ->body("PDF caricato su Nextcloud: {$nextcloudPath}")
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception('Failed to upload PDF to Nextcloud');
                            }

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore Generazione PDF')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
