<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MilestoneResource\Pages;
use App\Filament\Resources\MilestoneResource\RelationManagers;
use App\Models\Milestone;
use App\Models\CompanyProfile;
use App\Services\ClaudeAiService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MilestoneResource extends Resource
{
    protected static ?string $model = Milestone::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';
    
    protected static ?string $navigationGroup = 'Gestione Progetti';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationLabel = 'Milestone';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Milestone')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nome Milestone'),
                            
                        Forms\Components\Group::make([
                            Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->label('Descrizione')
                                ->columnSpanFull(),
                            
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('improve_milestone_with_claude')
                                    ->label('🤖 Migliora con Claude AI')
                                    ->color('info')
                                    ->visible(fn () => CompanyProfile::current()->isClaudeEnabled())
                                    ->action(function (Set $set, Get $get, ClaudeAiService $claudeService) {
                                        $milestoneName = $get('name');
                                        $currentDescription = $get('description') ?? '';
                                        
                                        if (empty($milestoneName)) {
                                            Notification::make()
                                                ->title('Nome Milestone Richiesto')
                                                ->body('Inserisci prima il nome della milestone.')
                                                ->warning()
                                                ->send();
                                            return;
                                        }
                                        
                                        try {
                                            $context = [
                                                'deadline' => $get('deadline')?->format('d/m/Y'),
                                                'category' => $get('category'),
                                            ];
                                            
                                            $improvedDescription = $claudeService->improveMilestoneDescription(
                                                $milestoneName,
                                                'Progetto Generico', // TODO: get from context if editing
                                                $currentDescription,
                                                $context
                                            );
                                            
                                            if ($improvedDescription) {
                                                $set('description', $improvedDescription);
                                                
                                                Notification::make()
                                                    ->title('Descrizione Milestone Migliorata')
                                                    ->body('La descrizione è stata migliorata da Claude AI.')
                                                    ->success()
                                                    ->send();
                                            } else {
                                                throw new \Exception('Nessuna risposta da Claude AI');
                                            }
                                        } catch (\Exception $e) {
                                            Notification::make()
                                                ->title('Errore Claude AI')
                                                ->body('Errore durante il miglioramento: ' . $e->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    }),
                            ])
                            ->alignEnd()
                            ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                            
                        Forms\Components\Select::make('category')
                            ->label('Categoria')
                            ->options([
                                'design' => 'Progettazione',
                                'prototype' => 'Prototipo',
                                'testing' => 'Test',
                                'production' => 'Produzione',
                                'delivery' => 'Consegna',
                                'documentation' => 'Documentazione',
                                'approval' => 'Approvazione',
                                'other' => 'Altro',
                            ])
                            ->default('other'),
                            
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
                            
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(1)
                            ->label('Ordine Visualizzazione'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Scadenza e Notifiche')
                    ->schema([
                        Forms\Components\DatePicker::make('deadline')
                            ->label('Data Scadenza')
                            ->helperText('Data di scadenza per questa milestone'),
                            
                        Forms\Components\Toggle::make('email_notifications')
                            ->label('Notifiche Email')
                            ->helperText('Abilita notifiche email automatiche per la scadenza')
                            ->default(true),
                            
                        Forms\Components\TextInput::make('notification_days_before')
                            ->label('Giorni Prima Notifica')
                            ->numeric()
                            ->default(3)
                            ->minValue(1)
                            ->maxValue(15)
                            ->helperText('Numero di giorni prima della scadenza per inviare la notifica')
                            ->visible(fn ($get) => $get('email_notifications')),
                            
                        Forms\Components\Placeholder::make('last_notification_info')
                            ->label('Ultima Notifica')
                            ->content(fn ($record) => $record?->last_notification_sent 
                                ? $record->last_notification_sent->format('d/m/Y H:i')
                                : 'Nessuna notifica inviata')
                            ->visible(fn ($record) => $record?->exists),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'design' => 'Progettazione',
                        'prototype' => 'Prototipo',
                        'testing' => 'Test',
                        'production' => 'Produzione',
                        'delivery' => 'Consegna',
                        'documentation' => 'Documentazione',
                        'approval' => 'Approvazione',
                        'other' => 'Altro',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'design' => 'info',
                        'prototype' => 'warning',
                        'testing' => 'primary',
                        'production' => 'success',
                        'delivery' => 'success',
                        'documentation' => 'gray',
                        'approval' => 'danger',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(50)
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Progetti')
                    ->counts('projects')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => $state . ' progetti'),
                    
                Tables\Columns\TextColumn::make('deadline')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->placeholder('Nessuna')
                    ->color(function ($record) {
                        if (!$record->deadline) return null;
                        $daysLeft = now()->diffInDays($record->deadline, false);
                        if ($daysLeft < 0) return 'danger';
                        if ($daysLeft <= 3) return 'warning';
                        return null;
                    })
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options([
                        'design' => 'Progettazione',
                        'prototype' => 'Prototipo',
                        'testing' => 'Test',
                        'production' => 'Produzione',
                        'delivery' => 'Consegna',
                        'documentation' => 'Documentazione',
                        'approval' => 'Approvazione',
                        'other' => 'Altro',
                    ]),
                    
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
            'index' => Pages\ListMilestones::route('/'),
            'create' => Pages\CreateMilestone::route('/create'),
            'edit' => Pages\EditMilestone::route('/{record}/edit'),
        ];
    }
}
