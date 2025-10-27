<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMilestoneResource\Pages;
use App\Models\PaymentMilestone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class PaymentMilestoneResource extends Resource
{
    protected static ?string $model = PaymentMilestone::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Milestone Pagamenti';

    protected static ?string $modelLabel = 'Milestone Pagamento';

    protected static ?string $pluralModelLabel = 'Milestone Pagamenti';

    protected static ?string $navigationGroup = 'Fatturazione';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Progetto e Preventivo')
                    ->schema([
                        Forms\Components\Select::make('project_id')
                            ->label('Progetto')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->columnSpan(1),

                        Forms\Components\Select::make('quotation_id')
                            ->label('Preventivo')
                            ->relationship('quotation', 'number', fn (Builder $query, Get $get) =>
                                $get('project_id')
                                    ? $query->whereHas('projects', fn ($q) => $q->where('projects.id', $get('project_id')))
                                    : $query
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $quotation = \App\Models\Quotation::find($state);
                                    if ($quotation) {
                                        // Auto-suggest milestone amount based on quotation total
                                        $set('quotation_total', $quotation->total);
                                    }
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make('quotation_total')
                            ->label('Totale Preventivo')
                            ->content(fn (Get $get) => $get('quotation_total') ? '€ ' . number_format($get('quotation_total'), 2, ',', '.') : '-')
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dettagli Milestone')
                    ->schema([
                        Forms\Components\TextInput::make('milestone_name')
                            ->label('Nome Milestone')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('es: Acconto 30%, Saldo 70%')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('percentage')
                            ->label('Percentuale (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                $quotationTotal = $get('quotation_total') ?? 0;
                                if ($quotationTotal > 0 && $state) {
                                    $amount = ($quotationTotal * $state) / 100;
                                    $set('amount', round($amount, 2));
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('amount')
                            ->label('Importo (€)')
                            ->numeric()
                            ->prefix('€')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->label('Stato')
                            ->options([
                                'pending' => 'In Attesa',
                                'invoiced' => 'Fatturato',
                                'paid' => 'Pagato',
                            ])
                            ->default('pending')
                            ->required()
                            ->reactive()
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('expected_date')
                            ->label('Data Prevista')
                            ->columnSpan(1),

                        Forms\Components\Select::make('invoice_id')
                            ->label('Fattura Collegata')
                            ->relationship('invoice', 'invoice_number')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => in_array($get('status'), ['invoiced', 'paid']))
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Progetto')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('milestone_name')
                    ->label('Milestone')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('percentage')
                    ->label('Percentuale')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'invoiced' => 'info',
                        'paid' => 'success',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'In Attesa',
                        'invoiced' => 'Fatturato',
                        'paid' => 'Pagato',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Fattura')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expected_date')
                    ->label('Data Prevista')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quotation.number')
                    ->label('Preventivo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Progetto')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'pending' => 'In Attesa',
                        'invoiced' => 'Fatturato',
                        'paid' => 'Pagato',
                    ]),

                Tables\Filters\Filter::make('expected_date')
                    ->form([
                        Forms\Components\DatePicker::make('expected_from')
                            ->label('Data Prevista Da'),
                        Forms\Components\DatePicker::make('expected_until')
                            ->label('Data Prevista A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['expected_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expected_date', '>=', $date),
                            )
                            ->when(
                                $data['expected_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expected_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),

                Tables\Actions\Action::make('create_invoice')
                    ->label('Crea Fattura')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        // Auto-create invoice from milestone
                        $quotation = $record->quotation;
                        $project = $record->project;

                        $invoice = \App\Models\InvoiceIssued::create([
                            'customer_id' => $quotation->customer_id,
                            'project_id' => $project->id,
                            'quotation_id' => $quotation->id,
                            'type' => $record->percentage < 100 ? 'advance_payment' : 'standard',
                            'issue_date' => now(),
                            'due_date' => now()->addDays(30),
                            'payment_term_id' => $quotation->payment_term_id,
                            'tax_rate' => 22,
                            'payment_stage' => $record->percentage < 100 ? 'deposit' : 'full',
                            'payment_percentage' => $record->percentage,
                            'status' => 'draft',
                            'payment_status' => 'unpaid',
                        ]);

                        // Create invoice item for milestone
                        $invoice->items()->create([
                            'description' => $record->milestone_name,
                            'quantity' => 1,
                            'unit_price' => $record->amount / 1.22, // Remove VAT for unit price
                            'tax_rate' => 22,
                        ]);

                        // Update milestone
                        $record->update([
                            'status' => 'invoiced',
                            'invoice_id' => $invoice->id,
                            'invoiced_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Fattura Creata')
                            ->body("Fattura {$invoice->invoice_number} creata con successo dalla milestone")
                            ->success()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('view')
                                    ->label('Visualizza Fattura')
                                    ->url(route('filament.admin.resources.invoice-issueds.edit', $invoice->id))
                                    ->openUrlInNewTab(),
                            ])
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMilestones::route('/'),
            'create' => Pages\CreatePaymentMilestone::route('/create'),
            'edit' => Pages\EditPaymentMilestone::route('/{record}/edit'),
        ];
    }
}
