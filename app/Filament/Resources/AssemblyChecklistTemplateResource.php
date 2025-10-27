<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssemblyChecklistTemplateResource\Pages;
use App\Filament\Resources\AssemblyChecklistTemplateResource\RelationManagers;
use App\Models\AssemblyChecklistTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AssemblyChecklistTemplateResource extends Resource
{
    protected static ?string $model = AssemblyChecklistTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Assembly Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Standard SMD Assembly Checklist'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Brief description of this template and when to use it'),
                    ])->columns(1),

                Forms\Components\Section::make('Board Configuration')
                    ->schema([
                        Forms\Components\Select::make('board_type')
                            ->required()
                            ->options(AssemblyChecklistTemplate::getBoardTypeOptions())
                            ->default('mixed'),
                        Forms\Components\Select::make('complexity_level')
                            ->required()
                            ->options(AssemblyChecklistTemplate::getComplexityOptions())
                            ->default('medium'),
                        Forms\Components\TextInput::make('estimated_time_minutes')
                            ->numeric()
                            ->suffix('minutes')
                            ->placeholder('Estimated completion time'),
                    ])->columns(3),

                Forms\Components\Section::make('PCB Specifications')
                    ->schema([
                        Forms\Components\KeyValue::make('pcb_specifications')
                            ->label('PCB Specifications')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->addActionLabel('Add specification')
                            ->default([
                                'dimensions' => '',
                                'layer_count' => '',
                                'thickness' => '',
                                'finish' => '',
                            ]),
                    ]),

                Forms\Components\Section::make('Template Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Template')
                            ->helperText('Only active templates can be used for new checklists')
                            ->default(true),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Template')
                            ->helperText('This template will be pre-selected for this board type'),
                        Forms\Components\Textarea::make('requirements')
                            ->label('Special Requirements')
                            ->rows(3)
                            ->placeholder('Any special tools, certifications, or conditions required'),
                    ])->columns(2),

                Forms\Components\Hidden::make('created_by')
                    ->default(auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('board_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'smd' => 'SMD Only',
                        'through_hole' => 'Through-Hole',
                        'mixed' => 'Mixed',
                        'prototype' => 'Prototype',
                        'production' => 'Production',
                        'generic' => 'Generic',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'smd' => 'info',
                        'through_hole' => 'warning',
                        'mixed' => 'success',
                        'prototype' => 'danger',
                        'production' => 'primary',
                        'generic' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('complexity_level')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'simple' => 'success',
                        'medium' => 'warning',
                        'complex' => 'danger',
                        'expert' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('critical_items_count')
                    ->label('Critical')
                    ->getStateUsing(fn ($record) => $record->critical_items_count)
                    ->badge()
                    ->color('danger'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),
                Tables\Columns\TextColumn::make('estimated_time_minutes')
                    ->label('Est. Time')
                    ->formatStateUsing(fn ($state) => $state ? $state . 'min' : 'N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('board_type')
                    ->options(AssemblyChecklistTemplate::getBoardTypeOptions()),
                Tables\Filters\SelectFilter::make('complexity_level')
                    ->options(AssemblyChecklistTemplate::getComplexityOptions()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Templates')
                    ->placeholder('All templates')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Templates'),
            ])
            ->actions([
                Tables\Actions\Action::make('clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->placeholder('New template name'),
                    ])
                    ->action(function (AssemblyChecklistTemplate $record, array $data): void {
                        $record->clone($data['name']);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->before(function (AssemblyChecklistTemplate $record) {
                        if (!$record->canBeDeleted()) {
                            throw new \Exception('Cannot delete template that is being used by existing checklists.');
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if (!$record->canBeDeleted()) {
                                    throw new \Exception("Cannot delete template '{$record->name}' that is being used by existing checklists.");
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssemblyChecklistTemplates::route('/'),
            'create' => Pages\CreateAssemblyChecklistTemplate::route('/create'),
            'edit' => Pages\EditAssemblyChecklistTemplate::route('/{record}/edit'),
        ];
    }
}
