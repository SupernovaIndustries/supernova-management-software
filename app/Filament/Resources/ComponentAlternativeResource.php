<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComponentAlternativeResource\Pages;
use App\Models\ComponentAlternative;
use App\Services\ComponentLifecycleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ComponentAlternativeResource extends Resource
{
    protected static ?string $model = ComponentAlternative::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Component Alternatives';

    protected static ?string $navigationGroup = 'Advanced Electronics';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('original_component_id')
                    ->relationship('originalComponent', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Original Component'),
                    
                Forms\Components\Select::make('alternative_component_id')
                    ->relationship('alternativeComponent', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Alternative Component')
                    ->different('original_component_id'),
                    
                Forms\Components\Select::make('alternative_type')
                    ->options([
                        'direct_replacement' => 'Direct Replacement',
                        'functional_equivalent' => 'Functional Equivalent',
                        'pin_compatible' => 'Pin Compatible',
                        'form_factor_compatible' => 'Form Factor Compatible',
                    ])
                    ->required(),
                    
                Forms\Components\TextInput::make('compatibility_score')
                    ->label('Compatibility Score (0-1)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1)
                    ->step(0.01)
                    ->default(0.5)
                    ->required()
                    ->helperText('0 = Incompatible, 1 = Perfect match'),
                    
                Forms\Components\Toggle::make('is_recommended')
                    ->label('Recommended Alternative')
                    ->default(false),
                    
                Forms\Components\Textarea::make('compatibility_notes')
                    ->label('Compatibility Notes')
                    ->rows(3)
                    ->columnSpanFull(),
                    
                Forms\Components\Repeater::make('differences')
                    ->label('Technical Differences')
                    ->schema([
                        Forms\Components\TextInput::make('parameter')
                            ->required(),
                        Forms\Components\TextInput::make('original_value')
                            ->label('Original Value'),
                        Forms\Components\TextInput::make('alternative_value')
                            ->label('Alternative Value'),
                        Forms\Components\TextInput::make('impact')
                            ->placeholder('Design impact description'),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('originalComponent.name')
                    ->label('Original Component')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('alternativeComponent.name')
                    ->label('Alternative Component')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('alternative_type_label')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Direct Replacement' => 'success',
                        'Functional Equivalent' => 'warning',
                        'Pin Compatible' => 'info',
                        'Form Factor Compatible' => 'gray',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('compatibility_percentage')
                    ->label('Compatibility')
                    ->suffix('%')
                    ->color(fn (int $state): string => match (true) {
                        $state >= 95 => 'success',
                        $state >= 85 => 'warning',
                        $state >= 70 => 'danger',
                        default => 'gray',
                    })
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('compatibility_level')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Excellent' => 'success',
                        'Good' => 'warning',
                        'Fair' => 'danger',
                        'Poor' => 'gray',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\IconColumn::make('is_recommended')
                    ->label('Recommended')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('originalComponent.unit_price')
                    ->label('Original Price')
                    ->money('EUR')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('alternativeComponent.unit_price')
                    ->label('Alternative Price')
                    ->money('EUR')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('price_difference')
                    ->label('Price Δ')
                    ->getStateUsing(fn (ComponentAlternative $record): float => 
                        ($record->alternativeComponent->unit_price ?? 0) - ($record->originalComponent->unit_price ?? 0))
                    ->money('EUR')
                    ->color(fn (float $state): string => $state < 0 ? 'success' : ($state > 0 ? 'danger' : 'gray')),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('alternative_type')
                    ->options([
                        'direct_replacement' => 'Direct Replacement',
                        'functional_equivalent' => 'Functional Equivalent',
                        'pin_compatible' => 'Pin Compatible',
                        'form_factor_compatible' => 'Form Factor Compatible',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_recommended')
                    ->label('Recommended Only'),
                    
                Tables\Filters\Filter::make('high_compatibility')
                    ->label('High Compatibility (≥85%)')
                    ->query(fn (Builder $query) => $query->highCompatibility()),
                    
                Tables\Filters\Filter::make('cost_savings')
                    ->label('Cost Savings Available')
                    ->query(fn (Builder $query) => $query->whereHas('alternativeComponent', function ($q) {
                        $q->whereRaw('unit_price < (SELECT unit_price FROM components AS orig WHERE orig.id = component_alternatives.original_component_id)');
                    })),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('calculate_auto_score')
                    ->label('Auto-Calculate Score')
                    ->icon('heroicon-o-calculator')
                    ->action(function (ComponentAlternative $record) {
                        $service = app(ComponentLifecycleService::class);
                        $score = $service->calculateCompatibilityScore(
                            $record->originalComponent, 
                            $record->alternativeComponent
                        );
                        
                        $record->update(['compatibility_score' => $score]);
                        
                        Notification::make()
                            ->title('Score Updated')
                            ->body("Compatibility score updated to {$score}%")
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('view_comparison')
                    ->label('Compare')
                    ->icon('heroicon-o-scale')
                    ->url(fn (ComponentAlternative $record): string => 
                        route('filament.admin.pages.component-comparison', [
                            'original' => $record->original_component_id,
                            'alternative' => $record->alternative_component_id,
                        ])),
            ])
            ->headerActions([
                Tables\Actions\Action::make('suggest_alternatives')
                    ->label('Auto-Suggest Alternatives')
                    ->icon('heroicon-o-sparkles')
                    ->form([
                        Forms\Components\Select::make('component_id')
                            ->label('Component')
                            ->relationship('originalComponent', 'name')
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('min_compatibility')
                            ->label('Minimum Compatibility Score')
                            ->numeric()
                            ->default(0.7)
                            ->minValue(0)
                            ->maxValue(1),
                    ])
                    ->action(function (array $data) {
                        // Auto-suggestion logic would go here
                        Notification::make()
                            ->title('Auto-suggestion feature')
                            ->body('This feature will analyze component specifications to suggest alternatives')
                            ->info()
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
            'index' => Pages\ListComponentAlternatives::route('/'),
            'create' => Pages\CreateComponentAlternative::route('/create'),
            'edit' => Pages\EditComponentAlternative::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::recommended()->count();
    }
}