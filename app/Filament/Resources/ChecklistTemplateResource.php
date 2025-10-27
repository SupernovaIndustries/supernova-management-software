<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChecklistTemplateResource\Pages;
use App\Filament\Resources\ChecklistTemplateResource\RelationManagers;
use App\Models\ChecklistTemplate;
use App\Models\SystemVariant;
use App\Models\SystemPhase;
use App\Models\ChecklistTemplateItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ChecklistTemplateResource extends Resource
{
    protected static ?string $model = ChecklistTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Checklist Templates';

    protected static ?string $navigationGroup = 'System Engineering';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Base')
                    ->schema([
                        Forms\Components\Select::make('system_variant_id')
                            ->label('Variante Sistema')
                            ->relationship('systemVariant', 'display_name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $variant = SystemVariant::with('category')->find($state);
                                    if ($variant) {
                                        $set('name', 'Template per ' . $variant->display_name);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('system_phase_id')
                            ->label('Fase Sistema')
                            ->relationship('systemPhase', 'display_name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome Template')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_default')
                                    ->label('Template Predefinito')
                                    ->helperText('Usato automaticamente per nuove istanze'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Attivo')
                                    ->default(true),
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Checklist Items')
                    ->schema([
                        Forms\Components\Repeater::make('checklistTemplateItems')
                            ->label('Voci Checklist')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Titolo')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('description')
                                    ->label('Descrizione')
                                    ->rows(2)
                                    ->columnSpan(3),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Note Tecniche')
                                    ->rows(2)
                                    ->columnSpan(3),

                                Forms\Components\Select::make('priority')
                                    ->label('Priorità')
                                    ->options(ChecklistTemplateItem::getPriorityLabels())
                                    ->default('medium')
                                    ->required(),

                                Forms\Components\Toggle::make('is_blocking')
                                    ->label('Bloccante')
                                    ->helperText('Blocca avanzamento se non completato'),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Ordine')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Attivo')
                                    ->default(true),
                            ])
                            ->columns(6)
                            ->reorderable('sort_order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->addActionLabel('Aggiungi Voce Checklist')
                            ->defaultItems(1),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('systemVariant.category.display_name')
                    ->label('Categoria')
                    ->badge()
                    ->color(fn ($record) => $record->systemVariant->category->color ?? 'primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('systemVariant.display_name')
                    ->label('Variante')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('systemPhase.display_name')
                    ->label('Fase')
                    ->badge()
                    ->color(fn ($record) => $record->systemPhase->color ?? 'blue')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Template')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('checklistTemplateItems_count')
                    ->label('Items')
                    ->counts('checklistTemplateItems')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('critical_items_count')
                    ->label('Critici')
                    ->getStateUsing(fn ($record) => $record->checklistTemplateItems()->where('priority', 'critical')->count())
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('blocking_items_count')
                    ->label('Bloccanti')
                    ->getStateUsing(fn ($record) => $record->checklistTemplateItems()->where('is_blocking', true)->count())
                    ->badge()
                    ->color('warning'),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('system_variant_id')
                    ->label('Variante')
                    ->relationship('systemVariant', 'display_name'),

                Tables\Filters\SelectFilter::make('system_phase_id')
                    ->label('Fase')
                    ->relationship('systemPhase', 'display_name'),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Solo Default'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Solo Attivi'),
            ])
            ->actions([
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplica')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function ($record) {
                        $newTemplate = $record->replicate();
                        $newTemplate->name = $newTemplate->name . ' (Copia)';
                        $newTemplate->is_default = false;
                        $newTemplate->save();

                        // Duplica anche gli items
                        foreach ($record->checklistTemplateItems as $item) {
                            $newItem = $item->replicate();
                            $newItem->checklist_template_id = $newTemplate->id;
                            $newItem->save();
                        }

                        Notification::make()
                            ->title('Template duplicato')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->groups([
                Tables\Grouping\Group::make('systemVariant.category.display_name')
                    ->label('Categoria')
                    ->collapsible(),
                Tables\Grouping\Group::make('systemPhase.display_name')
                    ->label('Fase')
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecklistTemplates::route('/'),
            'create' => Pages\CreateChecklistTemplate::route('/create'),
            'edit' => Pages\EditChecklistTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count() ?: null;
    }
}