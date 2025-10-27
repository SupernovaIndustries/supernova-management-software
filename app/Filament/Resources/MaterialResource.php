<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Resources\MaterialResource\RelationManagers;
use App\Models\Material;
use App\Services\InventoryExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    protected static ?string $navigationGroup = 'Inventario';
    
    protected static ?string $navigationLabel = 'Materiali';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Dettagli Materiale')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Informazioni Base')
                            ->schema([
                                Forms\Components\Section::make('Identificazione')
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label('Codice')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('es. FIL-PLA-RED-001'),
                                            
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nome')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('es. Filamento PLA Rosso'),
                                            
                                        Forms\Components\Select::make('category')
                                            ->label('Categoria')
                                            ->options(Material::getCategoryLabels())
                                            ->required()
                                            ->searchable(),
                                            
                                        Forms\Components\Select::make('status')
                                            ->label('Stato')
                                            ->options(Material::getStatusLabels())
                                            ->default('active')
                                            ->required(),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Descrizione')
                                    ->schema([
                                        Forms\Components\Textarea::make('description')
                                            ->label('Descrizione')
                                            ->rows(3)
                                            ->placeholder('Descrizione dettagliata del materiale')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Specifiche')
                            ->schema([
                                Forms\Components\Section::make('Dettagli Prodotto')
                                    ->schema([
                                        Forms\Components\TextInput::make('brand')
                                            ->label('Marca')
                                            ->maxLength(255)
                                            ->placeholder('es. Prusament'),
                                            
                                        Forms\Components\TextInput::make('model')
                                            ->label('Modello')
                                            ->maxLength(255)
                                            ->placeholder('es. PLA Galaxy Silver'),
                                            
                                        Forms\Components\TextInput::make('color')
                                            ->label('Colore')
                                            ->maxLength(255)
                                            ->placeholder('es. Rosso, Blu, Trasparente'),
                                            
                                        Forms\Components\TextInput::make('material_type')
                                            ->label('Tipo Materiale')
                                            ->maxLength(255)
                                            ->placeholder('es. PLA, ABS, PETG, TPU'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Misure e Peso')
                                    ->schema([
                                        Forms\Components\TextInput::make('diameter')
                                            ->label('Diametro (mm)')
                                            ->numeric()
                                            ->step(0.01)
                                            ->placeholder('es. 1.75'),
                                            
                                        Forms\Components\TextInput::make('weight_kg')
                                            ->label('Peso (kg)')
                                            ->numeric()
                                            ->step(0.001)
                                            ->placeholder('es. 1.000'),
                                            
                                        Forms\Components\TextInput::make('length_m')
                                            ->label('Lunghezza (m)')
                                            ->numeric()
                                            ->step(0.01)
                                            ->placeholder('es. 330.00'),
                                            
                                        Forms\Components\Select::make('unit_of_measure')
                                            ->label('Unità di Misura')
                                            ->options(Material::getUnitOfMeasureOptions())
                                            ->default('pcs')
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Inventario')
                            ->schema([
                                Forms\Components\Section::make('Stock e Prezzi')
                                    ->schema([
                                        Forms\Components\TextInput::make('stock_quantity')
                                            ->label('Quantità in Stock')
                                            ->numeric()
                                            ->default(0)
                                            ->required(),
                                            
                                        Forms\Components\TextInput::make('min_stock_level')
                                            ->label('Livello Minimo Stock')
                                            ->numeric()
                                            ->default(0),
                                            
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Prezzo Unitario')
                                            ->numeric()
                                            ->step(0.01)
                                            ->placeholder('es. 25.90'),
                                            
                                        Forms\Components\Select::make('currency')
                                            ->label('Valuta')
                                            ->options([
                                                'EUR' => 'EUR (€)',
                                                'USD' => 'USD ($)',
                                                'GBP' => 'GBP (£)',
                                            ])
                                            ->default('EUR')
                                            ->required(),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Stoccaggio')
                                    ->schema([
                                        Forms\Components\TextInput::make('storage_location')
                                            ->label('Posizione Magazzino')
                                            ->maxLength(255)
                                            ->placeholder('es. Scaffale A2, Cassetto B3'),
                                            
                                        Forms\Components\TextInput::make('temperature_storage_min')
                                            ->label('Temp. Stoccaggio Min (°C)')
                                            ->numeric()
                                            ->step(0.1),
                                            
                                        Forms\Components\TextInput::make('temperature_storage_max')
                                            ->label('Temp. Stoccaggio Max (°C)')
                                            ->numeric()
                                            ->step(0.1),
                                            
                                        Forms\Components\DatePicker::make('expiry_date')
                                            ->label('Data Scadenza')
                                            ->displayFormat('d/m/Y'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Acquisto')
                            ->schema([
                                Forms\Components\Section::make('Informazioni Fornitore')
                                    ->schema([
                                        Forms\Components\TextInput::make('supplier')
                                            ->label('Fornitore')
                                            ->maxLength(255)
                                            ->placeholder('es. Amazon, 3D Filament'),
                                            
                                        Forms\Components\TextInput::make('supplier_code')
                                            ->label('Codice Fornitore')
                                            ->maxLength(255)
                                            ->placeholder('Codice prodotto del fornitore'),
                                            
                                        Forms\Components\DatePicker::make('purchase_date')
                                            ->label('Data Acquisto')
                                            ->displayFormat('d/m/Y'),
                                            
                                        Forms\Components\TextInput::make('invoice_reference')
                                            ->label('Riferimento Fattura')
                                            ->maxLength(255),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('File e Note')
                            ->schema([
                                Forms\Components\Section::make('Documenti')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_path')
                                            ->label('Immagine Materiale')
                                            ->image()
                                            ->maxSize(2048)
                                            ->directory('materials/images'),
                                            
                                        Forms\Components\FileUpload::make('datasheet_path')
                                            ->label('Scheda Tecnica')
                                            ->acceptedFileTypes(['application/pdf'])
                                            ->maxSize(5120)
                                            ->directory('materials/datasheets'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Note Aggiuntive')
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->label('Note')
                                            ->rows(4)
                                            ->placeholder('Note aggiuntive, istruzioni speciali, etc.')
                                            ->columnSpanFull(),
                                            
                                        Forms\Components\KeyValue::make('specifications')
                                            ->label('Specifiche Tecniche')
                                            ->keyLabel('Proprietà')
                                            ->valueLabel('Valore')
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
                    ->defaultImageUrl(url('/images/material-placeholder.png')),
                    
                Tables\Columns\TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->formatStateUsing(fn (string $state): string => Material::getCategoryLabels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'filament' => 'primary',
                        'resin' => 'success',
                        'stationery' => 'warning',
                        'consumable' => 'info',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('brand')
                    ->label('Marca')
                    ->searchable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($record): string => $record->isLowStock() ? 'danger' : 'success'),
                    
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Prezzo')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (string $state): string => Material::getStatusLabels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'discontinued' => 'danger',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('storage_location')
                    ->label('Posizione')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($record): string => $record->isExpired() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(Material::getCategoryLabels())
                    ->searchable(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options(Material::getStatusLabels()),
                    
                Tables\Filters\Filter::make('low_stock')
                    ->label('Stock Basso')
                    ->query(fn (Builder $query): Builder => $query->lowStock())
                    ->toggle(),
                    
                Tables\Filters\Filter::make('expired')
                    ->label('Scaduti')
                    ->query(fn (Builder $query): Builder => $query->expired())
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
                            return $exportService->exportMaterials();
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
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
