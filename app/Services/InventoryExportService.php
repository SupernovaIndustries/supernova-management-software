<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Material;
use App\Models\Equipment;
use App\Exports\ComponentsExport;
use App\Exports\MaterialsExport;
use App\Exports\EquipmentExport;
use App\Exports\MultiSheetInventoryExport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class InventoryExportService
{
    /**
     * Export components to Excel
     */
    public function exportComponents($filters = [])
    {
        $query = Component::with('category');
        
        // Applica filtri temporali
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // Filtri aggiuntivi
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['supplier'])) {
            $query->where('supplier', $filters['supplier']);
        }
        
        $components = $query->get();
        
        return Excel::download(new ComponentsExport($components), 'componenti_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }
    
    /**
     * Export materials to Excel
     */
    public function exportMaterials($filters = [])
    {
        $query = Material::query();
        
        // Applica filtri temporali
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // Filtri aggiuntivi
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['supplier'])) {
            $query->where('supplier', $filters['supplier']);
        }
        
        $materials = $query->get();
        
        return Excel::download(new MaterialsExport($materials), 'materiali_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }
    
    /**
     * Export equipment to Excel
     */
    public function exportEquipment($filters = [])
    {
        $query = Equipment::query();
        
        // Applica filtri temporali
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // Filtri aggiuntivi
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['location'])) {
            $query->where('location', 'like', '%' . $filters['location'] . '%');
        }
        
        $equipment = $query->get();
        
        return Excel::download(new EquipmentExport($equipment), 'attrezzature_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }
    
    /**
     * Export complete inventory to Excel with multiple sheets
     */
    public function exportCompleteInventory($filters = [])
    {
        $exports = [];
        
        // Componenti
        $componentsQuery = Component::with('category');
        if (!empty($filters['date_from'])) {
            $componentsQuery->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $componentsQuery->where('created_at', '<=', $filters['date_to']);
        }
        $exports[] = new ComponentsExport($componentsQuery->get());
        
        // Materiali
        $materialsQuery = Material::query();
        if (!empty($filters['date_from'])) {
            $materialsQuery->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $materialsQuery->where('created_at', '<=', $filters['date_to']);
        }
        $exports[] = new MaterialsExport($materialsQuery->get());
        
        // Attrezzature
        $equipmentQuery = Equipment::query();
        if (!empty($filters['date_from'])) {
            $equipmentQuery->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $equipmentQuery->where('created_at', '<=', $filters['date_to']);
        }
        $exports[] = new EquipmentExport($equipmentQuery->get());
        
        return Excel::download(new MultiSheetInventoryExport($exports), 'inventario_completo_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
    }
    
    /**
     * Get predefined date ranges
     */
    public static function getDateRanges()
    {
        return [
            'current_month' => [
                'label' => 'Mese Corrente',
                'from' => now()->startOfMonth(),
                'to' => now()->endOfMonth(),
            ],
            'last_month' => [
                'label' => 'Mese Scorso',
                'from' => now()->subMonth()->startOfMonth(),
                'to' => now()->subMonth()->endOfMonth(),
            ],
            'current_year' => [
                'label' => 'Anno Corrente',
                'from' => now()->startOfYear(),
                'to' => now()->endOfYear(),
            ],
            'last_year' => [
                'label' => 'Anno Scorso',
                'from' => now()->subYear()->startOfYear(),
                'to' => now()->subYear()->endOfYear(),
            ],
            'last_3_months' => [
                'label' => 'Ultimi 3 Mesi',
                'from' => now()->subMonths(3)->startOfMonth(),
                'to' => now()->endOfMonth(),
            ],
            'last_6_months' => [
                'label' => 'Ultimi 6 Mesi',
                'from' => now()->subMonths(6)->startOfMonth(),
                'to' => now()->endOfMonth(),
            ],
            'all_time' => [
                'label' => 'Tutto il Periodo',
                'from' => null,
                'to' => null,
            ],
        ];
    }
}
