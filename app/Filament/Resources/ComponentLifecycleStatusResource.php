<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComponentLifecycleStatusResource\Pages;
use App\Models\ComponentLifecycleStatus;
use App\Services\ComponentLifecycleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ComponentLifecycleStatusResource extends Resource
{
    protected static ?string $model = ComponentLifecycleStatus::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Component Lifecycle';

    protected static ?string $navigationGroup = 'Advanced Electronics';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('component_id')
                    ->relationship('component', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Component'),
                    
                Forms\Components\Select::make('lifecycle_stage')
                    ->options([
                        'active' => 'Active',
                        'nrnd' => 'NRND (Not Recommended for New Designs)',
                        'eol_announced' => 'EOL Announced',
                        'eol' => 'End of Life',
                        'obsolete' => 'Obsolete',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state === 'eol_announced' && !$set->get('eol_announcement_date')) {
                            $set('eol_announcement_date', now());
                        }
                    }),
                    
                Forms\Components\DatePicker::make('eol_announcement_date')
                    ->label('EOL Announcement Date')
                    ->visible(fn (Forms\Get $get) => in_array($get('lifecycle_stage'), ['eol_announced', 'eol', 'obsolete'])),
                    
                Forms\Components\DatePicker::make('eol_date')
                    ->label('End of Life Date')
                    ->visible(fn (Forms\Get $get) => in_array($get('lifecycle_stage'), ['eol_announced', 'eol', 'obsolete']))
                    ->afterOrEqual('eol_announcement_date'),
                    
                Forms\Components\DatePicker::make('last_time_buy_date')
                    ->label('Last Time Buy Date')
                    ->visible(fn (Forms\Get $get) => in_array($get('lifecycle_stage'), ['eol_announced', 'eol']))
                    ->afterOrEqual('eol_announcement_date'),
                    
                Forms\Components\Textarea::make('eol_reason')
                    ->label('EOL Reason')
                    ->rows(3)
                    ->visible(fn (Forms\Get $get) => in_array($get('lifecycle_stage'), ['eol_announced', 'eol', 'obsolete'])),
                    
                Forms\Components\Textarea::make('manufacturer_notes')
                    ->label('Manufacturer Notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('component.name')
                    ->label('Component')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('component.manufacturer')
                    ->label('Manufacturer')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('lifecycle_stage')
                    ->label('Stage')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'nrnd' => 'warning',
                        'eol_announced' => 'danger',
                        'eol' => 'danger',
                        'obsolete' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'nrnd' => 'NRND',
                        'eol_announced' => 'EOL Announced',
                        'eol' => 'EOL',
                        default => ucfirst($state),
                    }),
                    
                Tables\Columns\TextColumn::make('urgency_level')
                    ->label('Urgency')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('eol_date')
                    ->label('EOL Date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('days_until_eol')
                    ->label('Days Until EOL')
                    ->numeric()
                    ->color(fn ($state): string => 
                        $state === null ? 'gray' : 
                        ($state < 0 ? 'danger' : 
                        ($state < 90 ? 'danger' : 
                        ($state < 180 ? 'warning' : 'success'))))
                    ->formatStateUsing(fn ($state): string => $state === null ? 'N/A' : $state . ' days'),
                    
                Tables\Columns\IconColumn::make('is_at_risk')
                    ->label('At Risk')
                    ->boolean()
                    ->getStateUsing(fn (ComponentLifecycleStatus $record): bool => $record->isAtRisk()),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lifecycle_stage')
                    ->options([
                        'active' => 'Active',
                        'nrnd' => 'NRND',
                        'eol_announced' => 'EOL Announced',
                        'eol' => 'End of Life',
                        'obsolete' => 'Obsolete',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_at_risk')
                    ->label('At Risk')
                    ->queries(
                        true: fn (Builder $query) => $query->atRisk(),
                        false: fn (Builder $query) => $query->whereNotIn('lifecycle_stage', ['nrnd', 'eol_announced', 'eol', 'obsolete']),
                    ),
                    
                Tables\Filters\Filter::make('eol_soon')
                    ->label('EOL Within 6 Months')
                    ->query(fn (Builder $query) => $query->urgent()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('find_alternatives')
                    ->label('Find Alternatives')
                    ->icon('heroicon-o-arrow-path')
                    ->url(fn (ComponentLifecycleStatus $record): string => 
                        route('filament.admin.resources.component-alternatives.index', ['component' => $record->component_id])),
                        
                Tables\Actions\Action::make('generate_alert')
                    ->label('Generate Alert')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->action(function (ComponentLifecycleStatus $record) {
                        $service = app(ComponentLifecycleService::class);
                        $alerts = $service->generateAlertsForComponent($record);
                        
                        Notification::make()
                            ->title('Alert Generated')
                            ->body(count($alerts) . ' alert(s) created for ' . $record->component->name)
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('check_all_lifecycle')
                    ->label('Check All Components')
                    ->icon('heroicon-o-magnifying-glass')
                    ->action(function () {
                        $service = app(ComponentLifecycleService::class);
                        $results = $service->checkLifecycleStatus();
                        
                        Notification::make()
                            ->title('Lifecycle Check Complete')
                            ->body("Checked {$results['components_checked']} components, created {$results['alerts_created']} alerts")
                            ->success()
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
            'index' => Pages\ListComponentLifecycleStatuses::route('/'),
            'create' => Pages\CreateComponentLifecycleStatus::route('/create'),
            'edit' => Pages\EditComponentLifecycleStatus::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::atRisk()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getNavigationBadge();
        return $count > 0 ? 'danger' : null;
    }
}