<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ObsolescenceAlertResource\Pages;
use App\Models\ObsolescenceAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ObsolescenceAlertResource extends Resource
{
    protected static ?string $model = ObsolescenceAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Obsolescence Alerts';

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
                    
                Forms\Components\Select::make('alert_type')
                    ->options([
                        'eol_warning' => 'EOL Warning',
                        'eol_imminent' => 'EOL Imminent',
                        'last_time_buy' => 'Last Time Buy',
                        'obsolete' => 'Obsolete',
                    ])
                    ->required(),
                    
                Forms\Components\Select::make('severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ])
                    ->required(),
                    
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\Textarea::make('message')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                    
                Forms\Components\DateTimePicker::make('alert_date')
                    ->required()
                    ->default(now()),
                    
                Forms\Components\Toggle::make('is_resolved')
                    ->label('Resolved')
                    ->default(false),
                    
                Forms\Components\DateTimePicker::make('acknowledged_at')
                    ->label('Acknowledged At'),
                    
                Forms\Components\Select::make('acknowledged_by')
                    ->relationship('acknowledgedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Acknowledged By'),
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
                    
                Tables\Columns\TextColumn::make('alert_type_label')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'EOL Warning' => 'warning',
                        'EOL Imminent' => 'danger',
                        'Last Time Buy' => 'danger',
                        'Obsolete' => 'gray',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                    
                Tables\Columns\TextColumn::make('affected_projects_count')
                    ->label('Affected Projects')
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                    
                Tables\Columns\IconColumn::make('is_acknowledged')
                    ->label('Acknowledged')
                    ->boolean()
                    ->getStateUsing(fn (ObsolescenceAlert $record): bool => $record->isAcknowledged()),
                    
                Tables\Columns\IconColumn::make('is_resolved')
                    ->label('Resolved')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('alert_date')
                    ->label('Alert Date')
                    ->dateTime()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('acknowledgedBy.name')
                    ->label('Acknowledged By')
                    ->placeholder('Not acknowledged'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('alert_type')
                    ->options([
                        'eol_warning' => 'EOL Warning',
                        'eol_imminent' => 'EOL Imminent',
                        'last_time_buy' => 'Last Time Buy',
                        'obsolete' => 'Obsolete',
                    ]),
                    
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_acknowledged')
                    ->label('Acknowledged')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('acknowledged_at'),
                        false: fn (Builder $query) => $query->whereNull('acknowledged_at'),
                    ),
                    
                Tables\Filters\TernaryFilter::make('is_resolved')
                    ->label('Resolved'),
                    
                Tables\Filters\Filter::make('critical_alerts')
                    ->label('Critical Alerts Only')
                    ->query(fn (Builder $query) => $query->critical()),
                    
                Tables\Filters\Filter::make('unresolved')
                    ->label('Unresolved Only')
                    ->query(fn (Builder $query) => $query->unresolved()),
            ])
            ->actions([
                Tables\Actions\Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ObsolescenceAlert $record): bool => !$record->isAcknowledged())
                    ->action(function (ObsolescenceAlert $record) {
                        $record->acknowledge(auth()->user());
                        
                        Notification::make()
                            ->title('Alert Acknowledged')
                            ->body('Alert has been acknowledged')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ObsolescenceAlert $record): bool => !$record->is_resolved)
                    ->requiresConfirmation()
                    ->action(function (ObsolescenceAlert $record) {
                        $record->update(['is_resolved' => true]);
                        
                        Notification::make()
                            ->title('Alert Resolved')
                            ->body('Alert has been marked as resolved')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('view_component')
                    ->label('View Component')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ObsolescenceAlert $record): string => 
                        route('filament.admin.resources.components.edit', $record->component_id)),
                        
                Tables\Actions\Action::make('find_alternatives')
                    ->label('Find Alternatives')
                    ->icon('heroicon-o-arrow-path')
                    ->url(fn (ObsolescenceAlert $record): string => 
                        route('filament.admin.resources.component-alternatives.index', ['component' => $record->component_id])),
                        
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('acknowledge_all')
                    ->label('Acknowledge All Visible')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->action(function () {
                        $count = ObsolescenceAlert::unacknowledged()->update([
                            'acknowledged_at' => now(),
                            'acknowledged_by' => auth()->id(),
                        ]);
                        
                        Notification::make()
                            ->title('Bulk Acknowledgment')
                            ->body("{$count} alerts acknowledged")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('acknowledge_selected')
                    ->label('Acknowledge Selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            if (!$record->isAcknowledged()) {
                                $record->acknowledge(auth()->user());
                            }
                        }
                        
                        Notification::make()
                            ->title('Bulk Acknowledgment')
                            ->body('Selected alerts acknowledged')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\BulkAction::make('resolve_selected')
                    ->label('Resolve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            $record->update(['is_resolved' => true]);
                        }
                        
                        Notification::make()
                            ->title('Bulk Resolution')
                            ->body('Selected alerts resolved')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('alert_date', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListObsolescenceAlerts::route('/'),
            'create' => Pages\CreateObsolescenceAlert::route('/create'),
            'edit' => Pages\EditObsolescenceAlert::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::unresolved()->unacknowledged()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getNavigationBadge();
        return $count > 0 ? 'danger' : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['component', 'acknowledgedBy']);
    }
}