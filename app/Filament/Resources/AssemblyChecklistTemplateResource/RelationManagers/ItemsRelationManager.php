<?php

namespace App\Filament\Resources\AssemblyChecklistTemplateResource\RelationManagers;

use App\Models\AssemblyChecklistItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Item Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Check component orientation'),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->placeholder('Detailed description of what needs to be checked'),
                        Forms\Components\Textarea::make('instructions')
                            ->rows(3)
                            ->placeholder('Step-by-step instructions for completing this item'),
                    ]),

                Forms\Components\Section::make('Item Configuration')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->required()
                            ->options(AssemblyChecklistItem::getTypeOptions())
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => 
                                $set('validation_rules', AssemblyChecklistItem::getDefaultValidationRules($state))
                            ),
                        Forms\Components\Select::make('category')
                            ->options(AssemblyChecklistItem::getCategoryOptions())
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->createOptionUsing(fn (array $data): string => $data['name']),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first'),
                    ])->columns(3),

                Forms\Components\Section::make('Validation & Requirements')
                    ->schema([
                        Forms\Components\Toggle::make('is_required')
                            ->label('Required Item')
                            ->helperText('This item must be completed')
                            ->default(true),
                        Forms\Components\Toggle::make('is_critical')
                            ->label('Critical Item')
                            ->helperText('Failure requires supervisor review'),
                        Forms\Components\TextInput::make('estimated_minutes')
                            ->numeric()
                            ->suffix('minutes')
                            ->placeholder('Expected time to complete'),
                    ])->columns(3),

                Forms\Components\Section::make('Type-Specific Options')
                    ->schema([
                        Forms\Components\KeyValue::make('options')
                            ->label('Options (for multiselect type)')
                            ->keyLabel('Value')
                            ->valueLabel('Label')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'multiselect'),
                        Forms\Components\KeyValue::make('validation_rules')
                            ->label('Validation Rules')
                            ->keyLabel('Rule')
                            ->valueLabel('Value')
                            ->helperText('e.g., min: 0, max: 100, tolerance: 0.1'),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\FileUpload::make('reference_image')
                            ->label('Reference Image')
                            ->image()
                            ->directory('assembly-references')
                            ->helperText('Upload reference image to help with this step'),
                        Forms\Components\Textarea::make('safety_notes')
                            ->rows(2)
                            ->placeholder('Any safety warnings or special precautions')
                            ->helperText('Important safety information for this step'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => 
                        AssemblyChecklistItem::getTypeOptions()[$state] ?? $state
                    )
                    ->icon(fn ($record) => $record->getTypeIcon())
                    ->color('gray'),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_required')
                    ->boolean()
                    ->label('Required'),
                Tables\Columns\IconColumn::make('is_critical')
                    ->boolean()
                    ->label('Critical')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('estimated_minutes')
                    ->label('Est. Time')
                    ->formatStateUsing(fn ($state) => $state ? $state . 'min' : '-')
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(AssemblyChecklistItem::getTypeOptions()),
                Tables\Filters\SelectFilter::make('category')
                    ->options(AssemblyChecklistItem::getCategoryOptions()),
                Tables\Filters\TernaryFilter::make('is_critical')
                    ->label('Critical Items'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['template_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
