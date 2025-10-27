<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ComponentsExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    protected $components;
    
    public function __construct(Collection $components)
    {
        $this->components = $components;
    }
    
    public function collection()
    {
        return $this->components;
    }
    
    public function headings(): array
    {
        return [
            'SKU',
            'Codice Produttore',
            'Nome',
            'Descrizione',
            'Categoria',
            'Produttore',
            'Package',
            'Prezzo Unitario',
            'Valuta',
            'Stock',
            'Min Stock',
            'Ubicazione',
            'Stato',
            'Fornitore',
            'Data Acquisto',
            'Riferimento Fattura',
            'Data Creazione',
        ];
    }
    
    public function map($component): array
    {
        return [
            $component->sku,
            $component->manufacturer_part_number,
            $component->name,
            $component->description,
            $component->category?->name,
            $component->manufacturer,
            $component->package,
            $component->unit_price,
            $component->currency,
            $component->stock_quantity,
            $component->min_stock_level,
            $component->storage_location,
            $component->status,
            $component->supplier,
            $component->purchase_date?->format('d/m/Y'),
            $component->invoice_reference,
            $component->created_at?->format('d/m/Y H:i'),
        ];
    }
    
    public function title(): string
    {
        return 'Componenti Elettronici';
    }
}