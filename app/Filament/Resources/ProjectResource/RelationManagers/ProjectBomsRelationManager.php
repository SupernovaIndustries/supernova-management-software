<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectBom;
use App\Services\BomAllocationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ProjectBomsRelationManager extends RelationManager
{
    protected static string $relationship = 'boms';

    protected static ?string $recordTitleAttribute = 'file_path';

    protected static ?string $title = 'Bill of Materials (BOM)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('bom_file')
                    ->label('Upload BOM File')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(10240) // 10MB
                    ->disk('local')
                    ->directory('temp/bom-uploads')
                    ->helperText('Upload a CSV or Excel BOM file. Will be saved to Nextcloud.')
                    ->hiddenOn('edit')
                    ->columnSpan(2),

                Forms\Components\TextInput::make('file_path')
                    ->label('BOM File Path (or use upload above)')
                    ->maxLength(255)
                    ->helperText('Path to BOM file in Syncthing (leave empty if uploading)')
                    ->hiddenOn('edit'),

                Forms\Components\TextInput::make('folder_path')
                    ->label('Folder Path')
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'partially_allocated' => 'Partially Allocated',
                        'allocated' => 'Allocated',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                    ])
                    ->default('pending')
                    ->required(),

                Forms\Components\Placeholder::make('allocation_info')
                    ->label('Allocation Status')
                    ->content(function ($record) {
                        if (!$record) return 'N/A';

                        $total = $record->total_items_count;
                        $allocated = $record->allocated_items_count;
                        $percentage = $record->allocation_percentage;

                        return "{$allocated} / {$total} items allocated ({$percentage}%)";
                    })
                    ->visible(fn ($record) => $record !== null),

                Forms\Components\Placeholder::make('cost_info')
                    ->label('Cost Information')
                    ->content(function ($record) {
                        if (!$record) return 'N/A';

                        $estimated = number_format($record->total_estimated_cost ?? 0, 2);
                        $actual = number_format($record->total_actual_cost ?? 0, 2);

                        return "Estimated: €{$estimated} | Actual: €{$actual}";
                    })
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_path')
            ->columns([
                Tables\Columns\TextColumn::make('file_path')
                    ->label('BOM File')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->description(fn ($record) => $record->nextcloud_path ? '☁️ Nextcloud' : '')
                    ->icon(fn ($record) => $record->nextcloud_path ? 'heroicon-o-cloud' : null)
                    ->iconColor('success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'partially_allocated' => 'warning',
                        'allocated' => 'success',
                        'processing' => 'info',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('allocation_status')
                    ->label('Allocation')
                    ->formatStateUsing(fn ($record) =>
                        "{$record->allocated_items_count}/{$record->total_items_count}"
                    )
                    ->description(fn ($record) => $record->allocation_percentage . '%')
                    ->badge()
                    ->color(fn ($record) => match(true) {
                        $record->allocation_percentage === 100.0 => 'success',
                        $record->allocation_percentage >= 75 => 'info',
                        $record->allocation_percentage >= 50 => 'warning',
                        $record->allocation_percentage > 0 => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_estimated_cost')
                    ->label('Est. Cost')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_actual_cost')
                    ->label('Actual Cost')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('processedBy.name')
                    ->label('By')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'partially_allocated' => 'Partially Allocated',
                        'allocated' => 'Allocated',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        // If a file was uploaded, store it temporarily
                        // The observer will handle Nextcloud upload
                        if (!empty($data['bom_file'])) {
                            $data['uploaded_file_path'] = $data['bom_file'];
                            unset($data['bom_file']); // Remove from BOM data
                        }

                        return $data;
                    }),

                Tables\Actions\Action::make('import_bom')
                    ->label('Import BOM from CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('file_path')
                            ->label('BOM File Path (relative to project folder)')
                            ->required()
                            ->helperText('Path to CSV BOM file in Syncthing'),
                    ])
                    ->action(function (array $data, RelationManager $livewire) {
                        try {
                            $bomService = app(\App\Services\BomService::class);
                            $project = $livewire->getOwnerRecord();

                            $bom = $bomService->importProjectBom($project, $data['file_path']);

                            Notification::make()
                                ->title('BOM Imported Successfully')
                                ->body("Imported {$bom->items()->count()} items from BOM")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Import Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('allocate_components')
                    ->label('Allocate Components')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Allocate Components to BOM')
                    ->modalDescription(function ($record) {
                        $boardsOrdered = $record->project->total_boards_ordered ?? 1;
                        return "This will allocate {$record->total_items_count} components from inventory to this project. " .
                            "Boards to produce: {$boardsOrdered}";
                    })
                    ->modalIcon('heroicon-o-shopping-cart')
                    ->form([
                        Forms\Components\TextInput::make('boards_count')
                            ->label('Number of Boards to Produce')
                            ->numeric()
                            ->default(fn ($record) => $record->project->total_boards_ordered ?? 1)
                            ->required()
                            ->minValue(1)
                            ->helperText('Quantity will be multiplied by this number'),
                    ])
                    ->action(function (ProjectBom $record, array $data) {
                        try {
                            $allocationService = app(BomAllocationService::class);
                            $results = $allocationService->allocateBom($record, $data['boards_count']);

                            $message = "Allocated: {$results['allocated']}, " .
                                      "Already Allocated: {$results['already_allocated']}, " .
                                      "Insufficient Stock: {$results['insufficient_stock']}, " .
                                      "Errors: {$results['errors']}";

                            if ($results['allocated'] > 0 || $results['already_allocated'] === $results['total_items']) {
                                Notification::make()
                                    ->title('Components Allocated')
                                    ->body($message)
                                    ->success()
                                    ->duration(10000)
                                    ->send();

                                // Show detailed insufficient stock info
                                if (!empty($results['insufficient_stock_items'])) {
                                    $stockList = collect($results['insufficient_stock_items'])
                                        ->map(fn ($item) =>
                                            "{$item['reference']}: {$item['component']} (need {$item['required']}, have {$item['available']})"
                                        )
                                        ->take(5)
                                        ->join(', ');

                                    Notification::make()
                                        ->title('Some Components Missing')
                                        ->body("Insufficient stock: {$stockList}")
                                        ->warning()
                                        ->duration(15000)
                                        ->send();
                                }
                            } else {
                                Notification::make()
                                    ->title('Allocation Failed')
                                    ->body($message)
                                    ->warning()
                                    ->duration(10000)
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Allocation Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->status !== 'allocated'),

                Tables\Actions\Action::make('deallocate_components')
                    ->label('Deallocate')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Deallocate Components')
                    ->modalDescription('This will return all allocated components to inventory')
                    ->action(function (ProjectBom $record) {
                        try {
                            $allocationService = app(BomAllocationService::class);
                            $results = $allocationService->deallocateBom($record);

                            Notification::make()
                                ->title('Components Deallocated')
                                ->body("Deallocated: {$results['deallocated']} items")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Deallocation Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->allocated_items_count > 0),

                Tables\Actions\Action::make('update_costs')
                    ->label('Update Costs')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->action(function (ProjectBom $record) {
                        try {
                            $record->updateAllItemCosts();

                            Notification::make()
                                ->title('Costs Updated')
                                ->body("Total cost: €" . number_format($record->total_actual_cost, 2))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Update Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view_on_nextcloud')
                    ->label('View on Nextcloud')
                    ->icon('heroicon-o-cloud')
                    ->color('info')
                    ->visible(fn ($record) => $record->nextcloud_path)
                    ->url(fn ($record) => config('nextcloud.base_url') . '/' . $record->nextcloud_path, shouldOpenInNewTab: true),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('This will deallocate all components and delete the BOM'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No BOMs')
            ->emptyStateDescription('Import a BOM from CSV or create one manually')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
