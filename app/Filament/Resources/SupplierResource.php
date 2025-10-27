<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    
    protected static ?string $navigationGroup = 'Gestione Fornitori';
    
    protected static ?string $navigationLabel = 'Fornitori';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Generali')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Fornitore')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('es. Mouser Electronics'),
                            
                        Forms\Components\TextInput::make('code')
                            ->label('Codice')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('es. MOUSER')
                            ->helperText('Codice identificativo univoco del fornitore'),
                            
                        Forms\Components\TextInput::make('website')
                            ->label('Sito Web')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://www.fornitore.it'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Contatti')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('ordini@fornitore.it'),
                            
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('+39 02 1234567'),
                            
                        Forms\Components\Textarea::make('address')
                            ->label('Indirizzo')
                            ->rows(3)
                            ->placeholder('Via Example 123, Milano, MI 20100')
                            ->columnSpanFull(),
                            
                        Forms\Components\TextInput::make('vat_number')
                            ->label('Partita IVA')
                            ->maxLength(255)
                            ->placeholder('IT12345678901'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Integrazione API')
                    ->schema([
                        Forms\Components\TextInput::make('api_name')
                            ->label('Nome API')
                            ->maxLength(255)
                            ->placeholder('es. mouser, digikey')
                            ->helperText('Nome identificativo per l\'integrazione API'),
                            
                        Forms\Components\Toggle::make('api_enabled')
                            ->label('API Abilitata')
                            ->default(false),
                            
                        Forms\Components\KeyValue::make('api_credentials')
                            ->label('Credenziali API')
                            ->keyLabel('Chiave')
                            ->valueLabel('Valore')
                            ->helperText('Inserire API Key, Secret, ecc.'),
                            
                        Forms\Components\KeyValue::make('api_settings')
                            ->label('Impostazioni API')
                            ->keyLabel('Parametro')
                            ->valueLabel('Valore')
                            ->helperText('Configurazioni aggiuntive per l\'API'),
                            
                        Forms\Components\DateTimePicker::make('last_api_sync')
                            ->label('Ultima Sincronizzazione')
                            ->displayFormat('d/m/Y H:i:s')
                            ->disabled()
                            ->helperText('Aggiornato automaticamente dal sistema'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('api_name')
                    ->label('API')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
                    
                Tables\Columns\TextColumn::make('website')
                    ->label('Sito Web')
                    ->searchable()
                    ->toggleable()
                    ->limit(30)
                    ->url(fn ($record) => $record->website, true),
                    
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),
                    
                Tables\Columns\IconColumn::make('api_enabled')
                    ->label('API Attiva')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('csvMappings_count')
                    ->label('Mapping CSV')
                    ->counts('csvMappings')
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('last_api_sync')
                    ->label('Ultima Sync')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Mai sincronizzato'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Attivo')
                    ->placeholder('Tutti i fornitori')
                    ->trueLabel('Solo attivi')
                    ->falseLabel('Solo inattivi'),
                    
                Tables\Filters\TernaryFilter::make('api_enabled')
                    ->label('API Abilitata')
                    ->placeholder('Tutti i fornitori')
                    ->trueLabel('Con API')
                    ->falseLabel('Senza API'),
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
