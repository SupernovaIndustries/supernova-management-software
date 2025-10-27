<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerTypeResource\Pages;
use App\Filament\Resources\CustomerTypeResource\RelationManagers;
use App\Models\CustomerType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerTypeResource extends Resource
{
    protected static ?string $model = CustomerType::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    
    protected static ?string $navigationGroup = 'Amministrazione';
    
    protected static ?string $navigationLabel = 'Tipi Cliente';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Tipo Cliente')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nome Tipo Cliente')
                            ->placeholder('es. Azienda, Privato, Ente Pubblico'),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->placeholder('Descrizione del tipo di cliente'),
                            
                        Forms\Components\Select::make('color')
                            ->label('Colore')
                            ->options([
                                'gray' => 'Grigio',
                                'blue' => 'Blu',
                                'green' => 'Verde',
                                'yellow' => 'Giallo',
                                'orange' => 'Arancione',
                                'red' => 'Rosso',
                                'purple' => 'Viola',
                            ])
                            ->default('blue'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ])
                    ->columns(2),
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
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(50)
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('color')
                    ->label('Colore')
                    ->badge()
                    ->color(fn (string $state): string => $state),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('customers_count')
                    ->label('Clienti Associati')
                    ->counts('customers')
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->label('Solo Attivi')
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerTypes::route('/'),
            'create' => Pages\CreateCustomerType::route('/create'),
            'edit' => Pages\EditCustomerType::route('/{record}/edit'),
        ];
    }
}
