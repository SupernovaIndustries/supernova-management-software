<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectProgressResource\Pages;
use App\Filament\Resources\ProjectProgressResource\RelationManagers;
use App\Models\ProjectProgress;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectProgressResource extends Resource
{
    protected static ?string $model = ProjectProgress::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationGroup = 'Gestione Progetti';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $navigationLabel = 'Stato Avanzamento';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Stato Avanzamento')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nome Stato')
                            ->placeholder('es. Non Iniziato, In Corso, Completato'),
                            
                        Forms\Components\TextInput::make('percentage')
                            ->numeric()
                            ->rules(['min:0', 'max:100'])
                            ->default(0)
                            ->suffix('%')
                            ->label('Percentuale Completamento'),
                            
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
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('percentage')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('percentage')
                    ->label('Completamento %')
                    ->sortable()
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state == 0 => 'gray',
                        $state < 25 => 'red',
                        $state < 50 => 'orange',
                        $state < 75 => 'yellow',
                        $state < 100 => 'blue',
                        $state == 100 => 'green',
                        default => 'gray'
                    }),
                    
                Tables\Columns\TextColumn::make('color')
                    ->label('Colore')
                    ->badge()
                    ->color(fn (string $state): string => $state),
                    
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
                Tables\Filters\Filter::make('completed')
                    ->label('Completato (100%)')
                    ->query(fn (Builder $query): Builder => $query->where('percentage', 100)),
                    
                Tables\Filters\Filter::make('in_progress')
                    ->label('In Corso (1-99%)')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('percentage', [1, 99])),
                    
                Tables\Filters\Filter::make('not_started')
                    ->label('Non Iniziato (0%)')
                    ->query(fn (Builder $query): Builder => $query->where('percentage', 0)),
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
            'index' => Pages\ListProjectProgress::route('/'),
            'create' => Pages\CreateProjectProgress::route('/create'),
            'edit' => Pages\EditProjectProgress::route('/{record}/edit'),
        ];
    }
}