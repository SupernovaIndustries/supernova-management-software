<?php

namespace App\Filament\Pages;

use App\Services\InventoryExportService;
use App\Models\Component;
use App\Models\Material;
use App\Models\Equipment;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class InventoryExports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    
    protected static ?string $navigationGroup = 'Inventario';
    
    protected static ?string $navigationLabel = 'Export Magazzino';
    
    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.inventory-exports';
    
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filtri Export')
                    ->schema([
                        Forms\Components\Select::make('date_range')
                            ->label('Periodo')
                            ->options([
                                'current_month' => 'Mese Corrente',
                                'last_month' => 'Mese Scorso',
                                'current_year' => 'Anno Corrente',
                                'last_year' => 'Anno Scorso',
                                'last_3_months' => 'Ultimi 3 Mesi',
                                'last_6_months' => 'Ultimi 6 Mesi',
                                'all_time' => 'Tutto il Periodo',
                                'custom' => 'Personalizzato',
                            ])
                            ->default('current_month')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state !== 'custom') {
                                    $ranges = InventoryExportService::getDateRanges();
                                    if (isset($ranges[$state])) {
                                        $set('date_from', $ranges[$state]['from']);
                                        $set('date_to', $ranges[$state]['to']);
                                    }
                                }
                            }),
                            
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Data Inizio')
                            ->displayFormat('d/m/Y')
                            ->visible(fn ($get) => $get('date_range') === 'custom'),
                            
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Data Fine')
                            ->displayFormat('d/m/Y')
                            ->visible(fn ($get) => $get('date_range') === 'custom'),
                    ])
                    ->columns(3),
                    
                Forms\Components\Section::make('Filtri Componenti Elettronici')
                    ->schema([
                        Forms\Components\Select::make('component_category')
                            ->label('Categoria Componenti')
                            ->options(Category::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Tutte le categorie'),
                            
                        Forms\Components\Select::make('component_status')
                            ->label('Stato Componenti')
                            ->options([
                                'active' => 'Attivo',
                                'inactive' => 'Inattivo',
                                'discontinued' => 'Dismesso',
                            ])
                            ->placeholder('Tutti gli stati'),
                            
                        Forms\Components\TextInput::make('component_supplier')
                            ->label('Fornitore Componenti')
                            ->placeholder('es. Mouser, DigiKey'),
                    ])
                    ->columns(3)
                    ->collapsible(),
                    
                Forms\Components\Section::make('Filtri Materiali')
                    ->schema([
                        Forms\Components\Select::make('material_category')
                            ->label('Categoria Materiali')
                            ->options(Material::getCategoryLabels())
                            ->placeholder('Tutte le categorie'),
                            
                        Forms\Components\Select::make('material_status')
                            ->label('Stato Materiali')
                            ->options(Material::getStatusLabels())
                            ->placeholder('Tutti gli stati'),
                            
                        Forms\Components\TextInput::make('material_supplier')
                            ->label('Fornitore Materiali')
                            ->placeholder('es. Amazon, Prusament'),
                    ])
                    ->columns(3)
                    ->collapsible(),
                    
                Forms\Components\Section::make('Filtri Attrezzature')
                    ->schema([
                        Forms\Components\Select::make('equipment_category')
                            ->label('Categoria Attrezzature')
                            ->options(Equipment::getCategoryLabels())
                            ->placeholder('Tutte le categorie'),
                            
                        Forms\Components\Select::make('equipment_status')
                            ->label('Stato Attrezzature')
                            ->options(Equipment::getStatusLabels())
                            ->placeholder('Tutti gli stati'),
                            
                        Forms\Components\TextInput::make('equipment_location')
                            ->label('Ubicazione')
                            ->placeholder('es. Laboratorio A'),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_components')
                ->label('📱 Export Solo Componenti')
                ->color('primary')
                ->action(function () {
                    $filters = $this->getFiltersForComponents();
                    $exportService = new InventoryExportService();
                    return $exportService->exportComponents($filters);
                }),
                
            Action::make('export_materials')
                ->label('🧪 Export Solo Materiali')
                ->color('success')
                ->action(function () {
                    $filters = $this->getFiltersForMaterials();
                    $exportService = new InventoryExportService();
                    return $exportService->exportMaterials($filters);
                }),
                
            Action::make('export_equipment')
                ->label('🔧 Export Solo Attrezzature')
                ->color('warning')
                ->action(function () {
                    $filters = $this->getFiltersForEquipment();
                    $exportService = new InventoryExportService();
                    return $exportService->exportEquipment($filters);
                }),
                
            Action::make('export_complete')
                ->label('📋 Export Inventario Completo')
                ->color('info')
                ->action(function () {
                    $filters = $this->getDateFilters();
                    $exportService = new InventoryExportService();
                    return $exportService->exportCompleteInventory($filters);
                }),
        ];
    }

    private function getDateFilters(): array
    {
        $data = $this->form->getState();
        $filters = [];
        
        if (!empty($data['date_range']) && $data['date_range'] !== 'all_time') {
            if ($data['date_range'] === 'custom') {
                $filters['date_from'] = $data['date_from'] ?? null;
                $filters['date_to'] = $data['date_to'] ?? null;
            } else {
                $ranges = InventoryExportService::getDateRanges();
                if (isset($ranges[$data['date_range']])) {
                    $filters['date_from'] = $ranges[$data['date_range']]['from'];
                    $filters['date_to'] = $ranges[$data['date_range']]['to'];
                }
            }
        }
        
        return $filters;
    }

    private function getFiltersForComponents(): array
    {
        $data = $this->form->getState();
        $filters = $this->getDateFilters();
        
        if (!empty($data['component_category'])) {
            $filters['category_id'] = $data['component_category'];
        }
        if (!empty($data['component_status'])) {
            $filters['status'] = $data['component_status'];
        }
        if (!empty($data['component_supplier'])) {
            $filters['supplier'] = $data['component_supplier'];
        }
        
        return $filters;
    }

    private function getFiltersForMaterials(): array
    {
        $data = $this->form->getState();
        $filters = $this->getDateFilters();
        
        if (!empty($data['material_category'])) {
            $filters['category'] = $data['material_category'];
        }
        if (!empty($data['material_status'])) {
            $filters['status'] = $data['material_status'];
        }
        if (!empty($data['material_supplier'])) {
            $filters['supplier'] = $data['material_supplier'];
        }
        
        return $filters;
    }

    private function getFiltersForEquipment(): array
    {
        $data = $this->form->getState();
        $filters = $this->getDateFilters();
        
        if (!empty($data['equipment_category'])) {
            $filters['category'] = $data['equipment_category'];
        }
        if (!empty($data['equipment_status'])) {
            $filters['status'] = $data['equipment_status'];
        }
        if (!empty($data['equipment_location'])) {
            $filters['location'] = $data['equipment_location'];
        }
        
        return $filters;
    }
}
