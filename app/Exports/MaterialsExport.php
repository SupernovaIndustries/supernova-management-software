<?php

namespace App\Exports;

use App\Models\Material;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaterialsExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    protected $materials;
    
    public function __construct(Collection $materials)
    {
        $this->materials = $materials;
    }
    
    public function collection()
    {
        return $this->materials;
    }
    
    public function headings(): array
    {
        return [
            'Codice',
            'Nome',
            'Categoria',
            'Marca',
            'Modello',
            'Colore',
            'Tipo Materiale',
            'Diametro (mm)',
            'Peso (kg)',
            'Prezzo Unitario',
            'Valuta',
            'Stock',
            'Min Stock',
            'Unità Misura',
            'Ubicazione',
            'Stato',
            'Fornitore',
            'Data Acquisto',
            'Data Scadenza',
            'Data Creazione',
        ];
    }
    
    public function map($material): array
    {
        return [
            $material->code,
            $material->name,
            Material::getCategoryLabels()[$material->category] ?? $material->category,
            $material->brand,
            $material->model,
            $material->color,
            $material->material_type,
            $material->diameter,
            $material->weight_kg,
            $material->unit_price,
            $material->currency,
            $material->stock_quantity,
            $material->min_stock_level,
            Material::getUnitOfMeasureOptions()[$material->unit_of_measure] ?? $material->unit_of_measure,
            $material->storage_location,
            Material::getStatusLabels()[$material->status] ?? $material->status,
            $material->supplier,
            $material->purchase_date?->format('d/m/Y'),
            $material->expiry_date?->format('d/m/Y'),
            $material->created_at?->format('d/m/Y H:i'),
        ];
    }
    
    public function title(): string
    {
        return 'Materiali';
    }
}