<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DatasheetTemplateResource\Pages;
use App\Models\DatasheetTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class DatasheetTemplateResource extends Resource
{
    protected static ?string $model = DatasheetTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Template Datasheet';

    protected static ?string $navigationGroup = 'Document Automation';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Base')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Template')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipo Template')
                            ->options(DatasheetTemplate::getTypes())
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $defaultSections = DatasheetTemplate::getDefaultSections($state);
                                    $set('sections', $defaultSections);
                                }
                            }),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('output_format')
                            ->label('Formato Output')
                            ->options(DatasheetTemplate::getOutputFormats())
                            ->default('pdf')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configurazione Sezioni')
                    ->schema([
                        Forms\Components\Repeater::make('sections')
                            ->label('Sezioni Datasheet')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome Sezione')
                                    ->required()
                                    ->helperText('Nome tecnico della sezione (es: overview, specifications)'),

                                Forms\Components\TextInput::make('title')
                                    ->label('Titolo Visualizzato')
                                    ->required()
                                    ->helperText('Titolo che apparirà nel datasheet'),

                                Forms\Components\Toggle::make('enabled')
                                    ->label('Abilitata')
                                    ->default(true),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Ordine')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(4)
                            ->reorderable('sort_order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['name'] ?? null)
                            ->addActionLabel('Aggiungi Sezione')
                            ->defaultItems(0),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Opzioni Layout')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo Aziendale')
                            ->image()
                            ->directory('datasheets/logos')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml']),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('include_company_info')
                                    ->label('Includi Info Azienda')
                                    ->default(true),

                                Forms\Components\Toggle::make('include_toc')
                                    ->label('Includi Indice')
                                    ->default(true)
                                    ->helperText('Genera automaticamente indice per documenti lunghi'),
                            ]),

                        Forms\Components\KeyValue::make('styles')
                            ->label('Stili CSS Personalizzati')
                            ->addActionLabel('Aggiungi Stile')
                            ->keyLabel('Classe CSS')
                            ->valueLabel('Proprietà CSS')
                            ->helperText('Stili CSS aggiuntivi per personalizzare l\'aspetto')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Impostazioni')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_default')
                                    ->label('Template Predefinito')
                                    ->helperText('Usato automaticamente per questo tipo'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Attivo')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => DatasheetTemplate::getTypes()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'project' => 'success',
                        'component' => 'warning',
                        'system' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('output_format')
                    ->label('Formato')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->badge()
                    ->color('purple'),

                Tables\Columns\TextColumn::make('sections_count')
                    ->label('Sezioni')
                    ->getStateUsing(fn ($record) => is_array($record->sections) ? count(array_filter($record->sections, fn($s) => $s['enabled'] ?? true)) : 0)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('generatedDatasheets_count')
                    ->label('Generati')
                    ->counts('generatedDatasheets')
                    ->badge()
                    ->color('success'),

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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(DatasheetTemplate::getTypes()),

                Tables\Filters\SelectFilter::make('output_format')
                    ->label('Formato')
                    ->options(DatasheetTemplate::getOutputFormats()),

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

                        Notification::make()
                            ->title('Template duplicato')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('preview')
                    ->label('Anteprima')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->action(function ($record) {
                        // TODO: Implementare anteprima template
                        Notification::make()
                            ->title('Anteprima')
                            ->body('Funzionalità di anteprima in sviluppo')
                            ->info()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatasheetTemplates::route('/'),
            'create' => Pages\CreateDatasheetTemplate::route('/create'),
            'edit' => Pages\EditDatasheetTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count() ?: null;
    }
}