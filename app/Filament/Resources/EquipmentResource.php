<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentResource\Pages;
use App\Filament\Resources\EquipmentResource\RelationManagers;
use App\Models\Equipment;
use App\Services\InventoryExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EquipmentResource extends Resource
{
    protected static ?string $model = Equipment::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    
    protected static ?string $navigationGroup = 'Inventario';
    
    protected static ?string $navigationLabel = 'Attrezzature';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Dettagli Attrezzatura')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Informazioni Base')
                            ->schema([
                                Forms\Components\Section::make('Identificazione')
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label('Codice Inventario')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('es. EQ-SOL-001'),
                                            
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nome Attrezzatura')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('es. Stazione Saldante Hakko'),
                                            
                                        Forms\Components\Select::make('category')
                                            ->label('Categoria')
                                            ->options(Equipment::getCategoryLabels())
                                            ->required()
                                            ->searchable(),
                                            
                                        Forms\Components\Select::make('status')
                                            ->label('Stato')
                                            ->options(Equipment::getStatusLabels())
                                            ->default('active')
                                            ->required(),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Descrizione')
                                    ->schema([
                                        Forms\Components\Textarea::make('description')
                                            ->label('Descrizione')
                                            ->rows(3)
                                            ->placeholder('Descrizione dettagliata dell\'attrezzatura')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Specifiche')
                            ->schema([
                                Forms\Components\Section::make('Dettagli Prodotto')
                                    ->schema([
                                        Forms\Components\TextInput::make('brand')
                                            ->label('Marca')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('es. Hakko, Rigol, Prusa'),
                                            
                                        Forms\Components\TextInput::make('model')
                                            ->label('Modello')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('es. FX-888D, DS1054Z, MK3S+'),
                                            
                                        Forms\Components\TextInput::make('serial_number')
                                            ->label('Numero Seriale')
                                            ->maxLength(255)
                                            ->placeholder('Numero seriale del produttore'),
                                            
                                        Forms\Components\TextInput::make('qr_code')
                                            ->label('Codice QR')
                                            ->maxLength(255)
                                            ->placeholder('Codice QR per identificazione rapida'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Specifiche Tecniche')
                                    ->schema([
                                        Forms\Components\Textarea::make('specifications')
                                            ->label('Specifiche')
                                            ->rows(4)
                                            ->placeholder('Specifiche tecniche generali')
                                            ->columnSpanFull(),
                                            
                                        Forms\Components\KeyValue::make('technical_specs')
                                            ->label('Specifiche Dettagliate')
                                            ->keyLabel('Caratteristica')
                                            ->valueLabel('Valore')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Acquisto')
                            ->schema([
                                Forms\Components\Section::make('Informazioni Acquisto')
                                    ->schema([
                                        Forms\Components\TextInput::make('purchase_price')
                                            ->label('Prezzo Acquisto')
                                            ->numeric()
                                            ->step(0.01)
                                            ->placeholder('es. 299.90'),
                                            
                                        Forms\Components\Select::make('currency')
                                            ->label('Valuta')
                                            ->options([
                                                'EUR' => 'EUR (€)',
                                                'USD' => 'USD ($)',
                                                'GBP' => 'GBP (£)',
                                            ])
                                            ->default('EUR')
                                            ->required(),
                                            
                                        Forms\Components\DatePicker::make('purchase_date')
                                            ->label('Data Acquisto')
                                            ->displayFormat('d/m/Y'),
                                            
                                        Forms\Components\TextInput::make('supplier')
                                            ->label('Fornitore')
                                            ->maxLength(255)
                                            ->placeholder('es. Amazon, RS Components'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Documenti Acquisto')
                                    ->schema([
                                        Forms\Components\TextInput::make('invoice_reference')
                                            ->label('Riferimento Fattura')
                                            ->maxLength(255),
                                            
                                        Forms\Components\DatePicker::make('warranty_expiry')
                                            ->label('Scadenza Garanzia')
                                            ->displayFormat('d/m/Y'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Ubicazione e Responsabilità')
                            ->schema([
                                Forms\Components\Section::make('Posizione e Assegnazione')
                                    ->schema([
                                        Forms\Components\TextInput::make('location')
                                            ->label('Ubicazione')
                                            ->maxLength(255)
                                            ->placeholder('es. Laboratorio A, Scrivania 3'),
                                            
                                        Forms\Components\TextInput::make('responsible_user')
                                            ->label('Responsabile/Assegnatario')
                                            ->maxLength(255)
                                            ->placeholder('Nome della persona responsabile'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Manutenzione')
                            ->schema([
                                Forms\Components\Section::make('Programmazione Manutenzione')
                                    ->schema([
                                        Forms\Components\DatePicker::make('last_maintenance')
                                            ->label('Ultima Manutenzione')
                                            ->displayFormat('d/m/Y'),
                                            
                                        Forms\Components\DatePicker::make('next_maintenance')
                                            ->label('Prossima Manutenzione')
                                            ->displayFormat('d/m/Y'),
                                            
                                        Forms\Components\TextInput::make('maintenance_interval_months')
                                            ->label('Intervallo Manutenzione (Mesi)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->placeholder('es. 12'),
                                    ])
                                    ->columns(3),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Calibrazione')
                            ->schema([
                                Forms\Components\Section::make('Gestione Calibrazione')
                                    ->schema([
                                        Forms\Components\Toggle::make('calibration_required')
                                            ->label('Richiede Calibrazione')
                                            ->default(false),
                                            
                                        Forms\Components\DatePicker::make('last_calibration')
                                            ->label('Ultima Calibrazione')
                                            ->displayFormat('d/m/Y'),
                                            
                                        Forms\Components\DatePicker::make('next_calibration')
                                            ->label('Prossima Calibrazione')
                                            ->displayFormat('d/m/Y'),
                                            
                                        Forms\Components\TextInput::make('calibration_interval_months')
                                            ->label('Intervallo Calibrazione (Mesi)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->placeholder('es. 12'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Valore e Ammortamento')
                            ->schema([
                                Forms\Components\Section::make('Gestione Valore')
                                    ->schema([
                                        Forms\Components\TextInput::make('depreciation_rate')
                                            ->label('Tasso Ammortamento (% annuo)')
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->placeholder('es. 20.00'),
                                            
                                        Forms\Components\TextInput::make('current_value')
                                            ->label('Valore Attuale Stimato')
                                            ->numeric()
                                            ->step(0.01)
                                            ->placeholder('Calcolato automaticamente o inserito manualmente'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('File e Note')
                            ->schema([
                                Forms\Components\Section::make('Documenti')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_path')
                                            ->label('Foto Attrezzatura')
                                            ->image()
                                            ->maxSize(2048)
                                            ->directory('equipment/images'),
                                            
                                        Forms\Components\FileUpload::make('manual_path')
                                            ->label('Manuale Utente')
                                            ->acceptedFileTypes(['application/pdf'])
                                            ->maxSize(10240)
                                            ->directory('equipment/manuals'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Note')
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->label('Note Aggiuntive')
                                            ->rows(4)
                                            ->placeholder('Note, istruzioni particolari, storia dell\'attrezzatura, etc.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('')
                    ->size(40)
                    ->circular()
                    ->defaultImageUrl(url('/images/equipment-placeholder.png')),
                    
                Tables\Columns\TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                    
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->formatStateUsing(fn (string $state): string => Equipment::getCategoryLabels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'computer' => 'primary',
                        'soldering' => 'warning',
                        '3d_printer' => 'success',
                        'measurement' => 'info',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('brand')
                    ->label('Marca')
                    ->searchable()
                    ->limit(15),
                    
                Tables\Columns\TextColumn::make('model')
                    ->label('Modello')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (string $state): string => Equipment::getStatusLabels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'broken' => 'danger',
                        'retired' => 'gray',
                        'sold' => 'info',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('location')
                    ->label('Ubicazione')
                    ->limit(20)
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('responsible_user')
                    ->label('Responsabile')
                    ->limit(15)
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('Prezzo Acquisto')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('current_value')
                    ->label('Valore Attuale')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('warranty_expiry')
                    ->label('Scadenza Garanzia')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($record): string => $record->isWarrantyExpired() ? 'danger' : null),
                    
                Tables\Columns\TextColumn::make('next_maintenance')
                    ->label('Prossima Manutenzione')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($record): string => $record->needsMaintenance() ? 'danger' : null),
                    
                Tables\Columns\IconColumn::make('calibration_required')
                    ->label('Cal.')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(Equipment::getCategoryLabels())
                    ->searchable(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options(Equipment::getStatusLabels()),
                    
                Tables\Filters\SelectFilter::make('location')
                    ->label('Ubicazione')
                    ->searchable(),
                    
                Tables\Filters\Filter::make('needs_maintenance')
                    ->label('Necessita Manutenzione')
                    ->query(fn (Builder $query): Builder => $query->needsMaintenance())
                    ->toggle(),
                    
                Tables\Filters\Filter::make('needs_calibration')
                    ->label('Necessita Calibrazione')
                    ->query(fn (Builder $query): Builder => $query->needsCalibration())
                    ->toggle(),
                    
                Tables\Filters\Filter::make('warranty_expired')
                    ->label('Garanzia Scaduta')
                    ->query(fn (Builder $query): Builder => $query->where('warranty_expiry', '<', now()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Modifica'),
                Tables\Actions\DeleteAction::make()
                    ->label('Elimina'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Elimina selezionati'),
                    Tables\Actions\BulkAction::make('export_excel')
                        ->label('📊 Export Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function () {
                            $exportService = new InventoryExportService();
                            return $exportService->exportEquipment();
                        }),
                ]),
            ])
            ->defaultSort('name');
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
            'index' => Pages\ListEquipment::route('/'),
            'create' => Pages\CreateEquipment::route('/create'),
            'edit' => Pages\EditEquipment::route('/{record}/edit'),
        ];
    }
}
