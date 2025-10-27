<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComponentResource\Pages;
use App\Filament\Resources\ComponentResource\RelationManagers;
use App\Models\Component;
use App\Models\Category;
use App\Services\ComponentImportService;
use App\Services\Suppliers\MouserApiService;
use App\Services\Suppliers\DigiKeyApiService;
use App\Services\ArUcoService;
use App\Services\InventoryExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ComponentResource extends Resource
{
    protected static ?string $model = Component::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Component Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('manufacturer_part_number')
                                    ->label('Manufacturer Part Number')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\TextInput::make('manufacturer')
                                    ->maxLength(255),
                            ])
                            ->columns(2),
                            
                        Forms\Components\Tabs\Tab::make('Technical Specifications')
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label('Value (e.g., 10uF, 100R)')
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('tolerance')
                                    ->label('Tolerance (e.g., ±5%)')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('voltage_rating')
                                    ->label('Voltage Rating')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('current_rating')
                                    ->label('Current Rating')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('power_rating')
                                    ->label('Power Rating')
                                    ->maxLength(20),
                                Forms\Components\Select::make('package_type')
                                    ->label('Package Type')
                                    ->options([
                                        '0402' => '0402',
                                        '0603' => '0603',
                                        '0805' => '0805',
                                        '1206' => '1206',
                                        '1210' => '1210',
                                        '2010' => '2010',
                                        '2512' => '2512',
                                        'SOT-23' => 'SOT-23',
                                        'SOT-223' => 'SOT-223',
                                        'TO-220' => 'TO-220',
                                        'SOIC-8' => 'SOIC-8',
                                        'SOIC-16' => 'SOIC-16',
                                        'TQFP-32' => 'TQFP-32',
                                        'TQFP-64' => 'TQFP-64',
                                        'QFN-16' => 'QFN-16',
                                        'QFN-32' => 'QFN-32',
                                    ])
                                    ->searchable(),
                                Forms\Components\Select::make('mounting_type')
                                    ->options([
                                        'SMD' => 'SMD',
                                        'Through Hole' => 'Through Hole',
                                    ]),
                                Forms\Components\TextInput::make('case_style')
                                    ->maxLength(50),
                                Forms\Components\Select::make('dielectric')
                                    ->label('Dielectric (Capacitors)')
                                    ->options([
                                        'X7R' => 'X7R',
                                        'X5R' => 'X5R',
                                        'C0G' => 'C0G/NP0',
                                        'Y5V' => 'Y5V',
                                    ]),
                                Forms\Components\TextInput::make('operating_temperature')
                                    ->label('Operating Temperature Range')
                                    ->placeholder('-40°C ~ +85°C')
                                    ->maxLength(50),
                                Forms\Components\KeyValue::make('technical_attributes')
                                    ->label('Additional Technical Attributes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3),
                            
                        Forms\Components\Tabs\Tab::make('Inventory & Pricing')
                            ->schema([
                                Forms\Components\TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('€'),
                                Forms\Components\TextInput::make('currency')
                                    ->required()
                                    ->maxLength(3)
                                    ->default('EUR'),
                                Forms\Components\TextInput::make('stock_quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('min_stock_level')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('reorder_quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('storage_location')
                                    ->maxLength(255),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'discontinued' => 'Discontinued',
                                        'obsolete' => 'Obsolete',
                                    ])
                                    ->required()
                                    ->default('active'),
                            ])
                            ->columns(2),
                            
                        Forms\Components\Tabs\Tab::make('Links & Documents')
                            ->schema([
                                Forms\Components\TextInput::make('datasheet_url')
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\FileUpload::make('image_url')
                                    ->image(),
                                Forms\Components\KeyValue::make('supplier_links')
                                    ->label('Supplier Links')
                                    ->keyLabel('Supplier')
                                    ->valueLabel('Part Number'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category']))
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->sortable(),
                // Tables\Columns\TextColumn::make('aruco_code')
                //     ->label('ArUco Code')
                //     ->searchable()
                //     ->copyable()
                //     ->badge()
                //     ->color('success'),
                Tables\Columns\TextColumn::make('manufacturer_part_number')
                    ->label('MPN')
                    ->searchable()
                    ->sortable()
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Description')
                    ->searchable()
                    ->limit(30),
                    // ->tooltip(function (Component $record): string {
                    //     return $record->description ?? $record->name;
                    // }),
                    
                // Technical specs columns
                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('package_type')
                    ->label('Package')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('voltage_rating')
                    ->label('Voltage')
                    ->badge()
                    ->color('warning')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('manufacturer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                // Tables\Columns\TextColumn::make('datasheet_url')
                //     ->searchable(),
                // Tables\Columns\ImageColumn::make('image_url'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->numeric()
                    ->sortable(),
                    // ->color(fn (Component $record): string =>
                    //     $record->isLowStock() ? 'danger' : 'success'
                    // ),
                Tables\Columns\TextColumn::make('min_stock_level')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reorder_quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('storage_location')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('mounting_type')
                    ->options([
                        'SMD' => 'SMD',
                        'Through Hole' => 'Through Hole',
                    ]),
                    
                Tables\Filters\SelectFilter::make('package_type')
                    ->options(function () {
                        return Component::whereNotNull('package_type')
                            ->distinct()
                            ->pluck('package_type', 'package_type')
                            ->toArray();
                    })
                    ->searchable(),
                    
                Tables\Filters\Filter::make('value')
                    ->form([
                        Forms\Components\TextInput::make('value_from')
                            ->label('Value from'),
                        Forms\Components\TextInput::make('value_to')
                            ->label('Value to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['value_from'],
                                fn (Builder $query, $value): Builder => $query->where('value', '>=', $value),
                            )
                            ->when(
                                $data['value_to'],
                                fn (Builder $query, $value): Builder => $query->where('value', '<=', $value),
                            );
                    }),
                    
                Tables\Filters\SelectFilter::make('voltage_rating')
                    ->options(function () {
                        return Component::whereNotNull('voltage_rating')
                            ->distinct()
                            ->pluck('voltage_rating', 'voltage_rating')
                            ->toArray();
                    })
                    ->multiple(),
                    
                // Tables\Filters\SelectFilter::make('dielectric')
                //     ->options([
                //         'X7R' => 'X7R',
                //         'X5R' => 'X5R',
                //         'C0G' => 'C0G/NP0',
                //         'Y5V' => 'Y5V',
                //     ])
                //     ->visible(fn (): bool =>
                //         request()->has('tableFilters.category_id.value') &&
                //         str_contains(strtolower(Category::find(request('tableFilters.category_id.value'))?->name ?? ''), 'condensator')
                //     ),
                    
                Tables\Filters\TernaryFilter::make('in_stock')
                    ->label('Stock Status')
                    ->placeholder('All')
                    ->trueLabel('In Stock')
                    ->falseLabel('Out of Stock')
                    ->queries(
                        true: fn (Builder $query) => $query->where('stock_quantity', '>', 0),
                        false: fn (Builder $query) => $query->where('stock_quantity', '=', 0),
                    ),
                    
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock Only')
                    ->query(fn (Builder $query): Builder => $query->lowStock()),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Component $record, $action) {
                        // Check for relationships before deletion
                        $relationships = static::checkComponentRelationships($record);
                        if (!empty($relationships)) {
                            $message = "⚠️ **Impossibile eliminare il componente**\n\n";
                            $message .= "**{$record->name}** ({$record->sku}) è ancora utilizzato in:\n\n";
                            foreach ($relationships as $table => $count) {
                                $labels = [
                                    'inventory_movements' => 'Movimenti Inventario',
                                    'project_bom_items' => 'BOM Progetti', 
                                    'quotation_items' => 'Voci Preventivi',
                                    'component_alternatives' => 'Alternative Componenti',
                                    'aruco_markers' => 'Marker ArUco',
                                ];
                                $label = $labels[$table] ?? $table;
                                $message .= "• **{$label}**: {$count} record\n";
                            }
                            $message .= "\n💡 **Soluzione**: Eliminare prima questi record o contattare l'amministratore.";
                            
                            Notification::make()
                                ->title('Eliminazione Bloccata')
                                ->body($message)
                                ->danger()
                                ->persistent()
                                ->send();
                                
                            $action->halt();
                        }
                    }),
                Tables\Actions\Action::make('generate_aruco')
                    ->label('Generate ArUco')
                    ->icon('heroicon-o-qr-code')
                    ->action(function (Component $record) {
                        $service = app(ArUcoService::class);
                        $code = $service->generateArUcoCode($record);
                        
                        Notification::make()
                            ->title('ArUco Code Generated')
                            ->body("Code: {$code}")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Component $record) => !$record->aruco_code),
                Tables\Actions\Action::make('view_aruco')
                    ->label('View ArUco')
                    ->icon('heroicon-o-eye')
                    ->modalContent(fn (Component $record) => view('filament.components.aruco-modal', ['component' => $record]))
                    ->modalSubmitAction(false)
                    ->visible(fn (Component $record) => $record->aruco_code),
                Tables\Actions\Action::make('order_mouser')
                    ->label('Order from Mouser')
                    ->icon('heroicon-o-shopping-cart')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(1),
                        Forms\Components\TextInput::make('customer_order_number')
                            ->required()
                            ->maxLength(50),
                    ])
                    ->action(function (Component $record, array $data) {
                        $mouserService = app(MouserApiService::class);
                        $webOrderId = $mouserService->createWebOrder([
                            [
                                'part_number' => $record->manufacturer_part_number,
                                'quantity' => $data['quantity']
                            ]
                        ], $data['customer_order_number']);
                        
                        if ($webOrderId) {
                            Notification::make()
                                ->title('Web order created successfully')
                                ->body("Order ID: {$webOrderId}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to create web order')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('order_digikey')
                    ->label('Order from DigiKey')
                    ->icon('heroicon-o-shopping-cart')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(1),
                    ])
                    ->action(function (Component $record, array $data) {
                        $digikeyService = app(DigiKeyApiService::class);
                        $result = $digikeyService->addToWebOrder(
                            $record->manufacturer_part_number,
                            $data['quantity']
                        );
                        
                        if ($result) {
                            Notification::make()
                                ->title('Component added to DigiKey web order')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to add to DigiKey web order')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->striped()
            ->paginated([10, 25, 50, 100, 'all'])
            ->deferLoading()
            ->poll('30s')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records, $action) {
                            $blockedComponents = [];
                            $deletableComponents = [];
                            
                            foreach ($records as $component) {
                                $relationships = static::checkComponentRelationships($component);
                                if (!empty($relationships)) {
                                    $relTypes = array_keys($relationships);
                                    $blockedComponents[] = [
                                        'name' => $component->name . ' (' . $component->sku . ')',
                                        'relationships' => $relTypes
                                    ];
                                } else {
                                    $deletableComponents[] = $component;
                                }
                            }
                            
                            if (!empty($blockedComponents)) {
                                $message = "⚠️ **Alcuni componenti non possono essere eliminati:**\n\n";
                                foreach (array_slice($blockedComponents, 0, 5) as $blocked) {
                                    $message .= "• **{$blocked['name']}**\n";
                                }
                                if (count($blockedComponents) > 5) {
                                    $message .= "• ... e altri " . (count($blockedComponents) - 5) . " componenti\n";
                                }
                                
                                if (!empty($deletableComponents)) {
                                    $message .= "\n✅ **Eliminati con successo:** " . count($deletableComponents) . " componenti\n";
                                    // Delete only the safe components
                                    foreach ($deletableComponents as $component) {
                                        $component->delete();
                                    }
                                }
                                
                                $message .= "\n💡 **Per eliminare i componenti bloccati**, rimuovere prima i record collegati.";
                                
                                Notification::make()
                                    ->title('Eliminazione Parziale Completata')
                                    ->body($message)
                                    ->warning()
                                    ->duration(15000)
                                    ->send();
                            } else {
                                // All components can be deleted safely, proceed with default action
                                foreach ($deletableComponents as $component) {
                                    $component->delete();
                                }
                                
                                Notification::make()
                                    ->title('Eliminazione Completata')
                                    ->body('Tutti i ' . count($deletableComponents) . ' componenti sono stati eliminati con successo.')
                                    ->success()
                                    ->send();
                            }
                        }),
                    Tables\Actions\BulkAction::make('export_excel')
                        ->label('📊 Export Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function () {
                            $exportService = new InventoryExportService();
                            return $exportService->exportComponents();
                        }),
                    Tables\Actions\BulkAction::make('generate_aruco_bulk')
                        ->label('Generate ArUco Codes')
                        ->icon('heroicon-o-qr-code')
                        ->action(function ($records) {
                            $service = app(ArUcoService::class);
                            $count = 0;
                            
                            foreach ($records as $record) {
                                if (!$record->aruco_code) {
                                    $service->generateArUcoCode($record);
                                    $count++;
                                }
                            }
                            
                            Notification::make()
                                ->title('ArUco Codes Generated')
                                ->body("Generated {$count} ArUco codes")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('print_aruco_sheet')
                        ->label('Print ArUco Sheet')
                        ->icon('heroicon-o-printer')
                        ->action(function ($records) {
                            $service = app(ArUcoService::class);
                            $componentIds = $records->pluck('id')->toArray();
                            
                            try {
                                $html = $service->generatePrintSheet($componentIds);
                                
                                // Save to temporary file
                                $filename = 'aruco-sheet-' . time() . '.html';
                                $path = storage_path('app/public/temp/' . $filename);
                                
                                if (!file_exists(dirname($path))) {
                                    mkdir(dirname($path), 0755, true);
                                }
                                
                                file_put_contents($path, $html);
                                
                                return response()->download($path)->deleteFileAfterSend();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Check if component has relationships that prevent deletion
     */
    protected static function checkComponentRelationships(Component $component): array
    {
        $relationships = [];
        
        // Check inventory movements
        $movements = \DB::table('inventory_movements')->where('component_id', $component->id)->count();
        if ($movements > 0) {
            $relationships['inventory_movements'] = $movements;
        }
        
        // Check project BOM items
        $bomItems = \DB::table('project_bom_items')->where('component_id', $component->id)->count();
        if ($bomItems > 0) {
            $relationships['project_bom_items'] = $bomItems;
        }
        
        // Check quotation items
        $quotationItems = \DB::table('quotation_items')->where('component_id', $component->id)->count();
        if ($quotationItems > 0) {
            $relationships['quotation_items'] = $quotationItems;
        }
        
        // Check component alternatives
        $alternatives = \DB::table('component_alternatives')
            ->where('original_component_id', $component->id)
            ->orWhere('alternative_component_id', $component->id)
            ->count();
        if ($alternatives > 0) {
            $relationships['component_alternatives'] = $alternatives;
        }
        
        // Check ArUco markers
        $markers = \DB::table('aruco_markers')->where('component_id', $component->id)->count();
        if ($markers > 0) {
            $relationships['aruco_markers'] = $markers;
        }
        
        // Check other tables if needed
        $certifications = \DB::table('component_certifications')->where('component_id', $component->id)->count();
        if ($certifications > 0) {
            $relationships['component_certifications'] = $certifications;
        }
        
        return $relationships;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComponents::route('/'),
            'create' => Pages\CreateComponent::route('/create'),
            'edit' => Pages\EditComponent::route('/{record}/edit'),
        ];
    }
}
