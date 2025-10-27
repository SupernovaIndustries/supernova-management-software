<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectPriorityResource\Pages;
use App\Filament\Resources\ProjectPriorityResource\RelationManagers;
use App\Models\ProjectPriority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectPriorityResource extends Resource
{
    protected static ?string $model = ProjectPriority::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up';
    
    protected static ?string $navigationGroup = 'Gestione Progetti';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $navigationLabel = 'Priorità Progetti';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Priorità')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nome Priorità')
                            ->placeholder('es. Bassa, Media, Alta, Critica'),
                            
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
                            ->required()
                            ->default('blue'),
                            
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(1)
                            ->label('Ordine Visualizzazione')
                            ->helperText('Numeri più bassi appaiono prima'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('color')
                    ->label('Colore')
                    ->badge()
                    ->color(fn (string $state): string => $state),
                    
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Usato in Progetti')
                    ->counts('projects')
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            'index' => Pages\ListProjectPriorities::route('/'),
            'create' => Pages\CreateProjectPriority::route('/create'),
            'edit' => Pages\EditProjectPriority::route('/{record}/edit'),
        ];
    }
}
