<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemVariantResource\Pages;
use App\Models\SystemVariant;
use App\Models\SystemCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemVariantResource extends Resource
{
    protected static ?string $model = SystemVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Varianti Sistema';

    protected static ?string $navigationGroup = 'System Engineering';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('system_category_id')
                    ->label('Categoria Sistema')
                    ->relationship('category', 'display_name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('name')
                    ->label('Nome Tecnico')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Es: IMU_6AXIS, GPS_L1L5_DUAL'),

                Forms\Components\TextInput::make('display_name')
                    ->label('Nome Visualizzato')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Es: "IMU 6-assi", "GPS L1/L5 Dual-Band"'),

                Forms\Components\Textarea::make('description')
                    ->label('Descrizione')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Section::make('Specifiche Tecniche')
                    ->schema([
                        Forms\Components\KeyValue::make('specifications')
                            ->label('Specifiche')
                            ->addActionLabel('Aggiungi Specifica')
                            ->keyLabel('Parametro')
                            ->valueLabel('Valore')
                            ->helperText('Es: interface → I2C, range → ±16g, frequency → 1575.42 MHz')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),

                Forms\Components\Toggle::make('is_active')
                    ->label('Attivo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.display_name')
                    ->label('Categoria')
                    ->badge()
                    ->color(fn ($record) => $record->category->color ?? 'primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Variante')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Tecnico')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('specifications')
                    ->label('Specifiche')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return '—';
                        $specs = collect($state)->take(2);
                        $display = $specs->map(fn ($value, $key) => "$key: $value")->join(', ');
                        return count($state) > 2 ? $display . '...' : $display;
                    })
                    ->limit(50)
                    ->tooltip(function ($record) {
                        if (empty($record->specifications)) return null;
                        return collect($record->specifications)
                            ->map(fn ($value, $key) => "$key: $value")
                            ->join("\n");
                    }),

                Tables\Columns\TextColumn::make('checklistTemplates_count')
                    ->label('Templates')
                    ->counts('checklistTemplates')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('projectInstances_count')
                    ->label('In Uso')
                    ->counts('projectInstances')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('system_category_id')
                    ->label('Categoria')
                    ->relationship('category', 'display_name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Solo Attive'),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_templates')
                    ->label('Templates')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn ($record) => route('filament.admin.resources.checklist-templates.index', [
                        'tableFilters[system_variant_id][value]' => $record->id
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->groups([
                Tables\Grouping\Group::make('category.display_name')
                    ->label('Categoria')
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemVariants::route('/'),
            'create' => Pages\CreateSystemVariant::route('/create'),
            'edit' => Pages\EditSystemVariant::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count() ?: null;
    }
}