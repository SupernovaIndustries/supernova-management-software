<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemCategoryResource\Pages;
use App\Models\SystemCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemCategoryResource extends Resource
{
    protected static ?string $model = SystemCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Categorie Sistema';

    protected static ?string $navigationGroup = 'System Engineering';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome Tecnico')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Es: IMU_SENSORS, GPS_MODULES'),

                Forms\Components\TextInput::make('display_name')
                    ->label('Nome Visualizzato')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Es: "Sensori IMU", "Moduli GPS"'),

                Forms\Components\Textarea::make('description')
                    ->label('Descrizione')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Select::make('icon')
                    ->label('Icona')
                    ->options([
                        'heroicon-o-radio' => 'Radio',
                        'heroicon-o-signal' => 'Signal',
                        'heroicon-o-battery-100' => 'Battery',
                        'heroicon-o-cpu-chip' => 'CPU Chip',
                        'heroicon-o-rectangle-stack' => 'Rectangle Stack',
                        'heroicon-o-cog' => 'Cog',
                        'heroicon-o-bolt' => 'Bolt',
                        'heroicon-o-globe-alt' => 'Globe',
                    ])
                    ->default('heroicon-o-cog'),

                Forms\Components\Select::make('color')
                    ->label('Colore')
                    ->options([
                        'primary' => 'Primary',
                        'blue' => 'Blue',
                        'green' => 'Green',
                        'yellow' => 'Yellow',
                        'red' => 'Red',
                        'purple' => 'Purple',
                        'gray' => 'Gray',
                        'orange' => 'Orange',
                    ])
                    ->default('primary'),

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
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Nome')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Tecnico')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\IconColumn::make('icon')
                    ->label('Icona'),

                Tables\Columns\TextColumn::make('variants_count')
                    ->label('Varianti')
                    ->counts('variants')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Solo Attive'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemCategories::route('/'),
            'create' => Pages\CreateSystemCategory::route('/create'),
            'edit' => Pages\EditSystemCategory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count() ?: null;
    }
}