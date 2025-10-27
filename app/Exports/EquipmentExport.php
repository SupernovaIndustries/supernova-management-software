<?php

namespace App\Exports;

use App\Models\Equipment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class EquipmentExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    protected $equipment;
    
    public function __construct(Collection $equipment)
    {
        $this->equipment = $equipment;
    }
    
    public function collection()
    {
        return $this->equipment;
    }
    
    public function headings(): array
    {
        return [
            'Codice',
            'Nome',
            'Categoria',
            'Marca',
            'Modello',
            'Numero Seriale',
            'Prezzo Acquisto',
            'Valuta',
            'Valore Attuale',
            'Stato',
            'Ubicazione',
            'Responsabile',
            'Data Acquisto',
            'Scadenza Garanzia',
            'Ultima Manutenzione',
            'Prossima Manutenzione',
            'Richiede Calibrazione',
            'Data Creazione',
        ];
    }
    
    public function map($equipment): array
    {
        return [
            $equipment->code,
            $equipment->name,
            Equipment::getCategoryLabels()[$equipment->category] ?? $equipment->category,
            $equipment->brand,
            $equipment->model,
            $equipment->serial_number,
            $equipment->purchase_price,
            $equipment->currency,
            $equipment->current_value,
            Equipment::getStatusLabels()[$equipment->status] ?? $equipment->status,
            $equipment->location,
            $equipment->responsible_user,
            $equipment->purchase_date?->format('d/m/Y'),
            $equipment->warranty_expiry?->format('d/m/Y'),
            $equipment->last_maintenance?->format('d/m/Y'),
            $equipment->next_maintenance?->format('d/m/Y'),
            $equipment->calibration_required ? 'Sì' : 'No',
            $equipment->created_at?->format('d/m/Y H:i'),
        ];
    }
    
    public function title(): string
    {
        return 'Attrezzature';
    }
}