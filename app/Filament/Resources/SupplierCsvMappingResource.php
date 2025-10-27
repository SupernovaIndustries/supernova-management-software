<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierCsvMappingResource\Pages;
use App\Filament\Resources\SupplierCsvMappingResource\RelationManagers;
use App\Models\SupplierCsvMapping;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierCsvMappingResource extends Resource
{
    protected static ?string $model = SupplierCsvMapping::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationGroup = 'Gestione Fornitori';
    
    protected static ?string $navigationLabel = 'Mapping CSV';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configurazione Mapping')
                    ->schema([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Fornitore')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Seleziona un fornitore'),
                            
                        Forms\Components\Select::make('field_name')
                            ->label('Campo Sistema')
                            ->options(SupplierCsvMapping::getAvailableFields())
                            ->searchable()
                            ->required()
                            ->helperText('Seleziona il campo del sistema da mappare'),
                            
                        Forms\Components\TextInput::make('csv_column_name')
                            ->label('Nome Colonna CSV')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('es. Mouser No:')
                            ->helperText('Nome esatto della colonna nel file CSV del fornitore'),
                            
                        Forms\Components\TextInput::make('column_index')
                            ->label('Indice Colonna (Opzionale)')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('es. 0, 1, 2...')
                            ->helperText('Posizione della colonna nel CSV (0 = prima colonna)'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Configurazione Avanzata')
                    ->schema([
                        Forms\Components\Select::make('data_type')
                            ->label('Tipo Dato')
                            ->options(SupplierCsvMapping::getDataTypes())
                            ->default('string')
                            ->required(),
                            
                        Forms\Components\TextInput::make('default_value')
                            ->label('Valore Default')
                            ->maxLength(255)
                            ->placeholder('Valore da usare se la colonna è vuota')
                            ->helperText('Lasciare vuoto se non serve un valore di default'),
                            
                        Forms\Components\Toggle::make('is_required')
                            ->label('Campo Obbligatorio')
                            ->default(false)
                            ->helperText('Se abilitato, i record senza questo campo verranno scartati'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Mapping Attivo')
                            ->default(true)
                            ->helperText('Disabilitare per temporaneamente escludere questo mapping'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Regole di Trasformazione (JSON)')
                    ->schema([
                        Forms\Components\KeyValue::make('transformation_rules')
                            ->label('Regole di Trasformazione')
                            ->keyLabel('Regola')
                            ->valueLabel('Valore')
                            ->helperText('Regole personalizzate per trasformare i dati (es. "replace_text": "old|new")')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornitore')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('field_name')
                    ->label('Campo Sistema')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => 
                        SupplierCsvMapping::getAvailableFields()[$state] ?? $state
                    )
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('csv_column_name')
                    ->label('Colonna CSV')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('column_index')
                    ->label('Indice')
                    ->sortable()
                    ->placeholder('-')
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('data_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => 
                        SupplierCsvMapping::getDataTypes()[$state] ?? $state
                    )
                    ->badge()
                    ->color('secondary'),
                    
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Obbligatorio')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('default_value')
                    ->label('Default')
                    ->limit(20)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Fornitore')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('field_name')
                    ->label('Campo Sistema')
                    ->options(SupplierCsvMapping::getAvailableFields())
                    ->searchable(),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Stato')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo attivi')
                    ->falseLabel('Solo inattivi'),
                    
                Tables\Filters\TernaryFilter::make('is_required')
                    ->label('Obbligatorio')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo obbligatori')
                    ->falseLabel('Solo opzionali'),
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
                ]),
            ])
            ->defaultSort('supplier.name');
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
            'index' => Pages\ListSupplierCsvMappings::route('/'),
            'create' => Pages\CreateSupplierCsvMapping::route('/create'),
            'edit' => Pages\EditSupplierCsvMapping::route('/{record}/edit'),
        ];
    }
}
