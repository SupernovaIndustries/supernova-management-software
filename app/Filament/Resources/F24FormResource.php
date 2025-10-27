<?php

namespace App\Filament\Resources;

use App\Filament\Resources\F24FormResource\Pages;
use App\Models\F24Form;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class F24FormResource extends Resource
{
    protected static ?string $model = F24Form::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';

    protected static ?string $navigationLabel = 'Moduli F24';

    protected static ?string $modelLabel = 'Modulo F24';

    protected static ?string $pluralModelLabel = 'Moduli F24';

    protected static ?string $navigationGroup = 'Fatturazione';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni F24')
                    ->schema([
                        Forms\Components\TextInput::make('form_number')
                            ->label('Numero Modulo')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generato: F24-YYYY-XXX')
                            ->columnSpan(1),

                        Forms\Components\Select::make('type')
                            ->label('Tipo F24')
                            ->options([
                                'imu' => 'IMU',
                                'tasi' => 'TASI',
                                'iva' => 'IVA',
                                'inps' => 'INPS',
                                'inail' => 'INAIL',
                                'irpef' => 'IRPEF',
                                'other' => 'Altro',
                            ])
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('reference_month')
                            ->label('Mese di Riferimento')
                            ->options([
                                1 => 'Gennaio',
                                2 => 'Febbraio',
                                3 => 'Marzo',
                                4 => 'Aprile',
                                5 => 'Maggio',
                                6 => 'Giugno',
                                7 => 'Luglio',
                                8 => 'Agosto',
                                9 => 'Settembre',
                                10 => 'Ottobre',
                                11 => 'Novembre',
                                12 => 'Dicembre',
                            ])
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('reference_year')
                            ->label('Anno di Riferimento')
                            ->numeric()
                            ->required()
                            ->default(date('Y'))
                            ->minValue(2020)
                            ->maxValue(2050)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Importo Totale (€)')
                            ->numeric()
                            ->prefix('€')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data Pagamento')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Data Scadenza')
                            ->columnSpan(1),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente (Opzionale)')
                            ->relationship('customer', 'company_name')
                            ->searchable()
                            ->preload()
                            ->helperText('Se il modulo F24 è per un cliente specifico')
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('notes')
                            ->label('Note')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('form_number')
                    ->label('Numero')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'imu' => 'warning',
                        'tasi' => 'warning',
                        'iva' => 'danger',
                        'inps' => 'info',
                        'inail' => 'info',
                        'irpef' => 'success',
                        'other' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                Tables\Columns\TextColumn::make('reference_period')
                    ->label('Periodo')
                    ->state(function ($record) {
                        $month = $record->reference_month
                            ? date('m', mktime(0, 0, 0, $record->reference_month, 1))
                            : '--';
                        return $month . '/' . $record->reference_year;
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data Pagamento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(20)
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'imu' => 'IMU',
                        'tasi' => 'TASI',
                        'iva' => 'IVA',
                        'inps' => 'INPS',
                        'inail' => 'INAIL',
                        'irpef' => 'IRPEF',
                        'other' => 'Altro',
                    ]),

                Tables\Filters\SelectFilter::make('reference_year')
                    ->label('Anno')
                    ->options(function () {
                        $years = range(date('Y'), 2020);
                        return array_combine($years, $years);
                    }),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

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

                Tables\Actions\Action::make('generate_from_template')
                    ->label('Genera da Template')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->action(function ($record) {
                        // TODO: Implement template generation
                        Notification::make()
                            ->title('Generazione Template')
                            ->body('Funzionalità in sviluppo - generazione da template')
                            ->info()
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
            'index' => Pages\ListF24Forms::route('/'),
            'create' => Pages\CreateF24Form::route('/create'),
            'edit' => Pages\EditF24Form::route('/{record}/edit'),
        ];
    }
}
