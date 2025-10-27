<?php

namespace App\Filament\Resources\SupplierCsvMappingResource\Pages;

use App\Filament\Resources\SupplierCsvMappingResource;
use App\Models\Supplier;
use App\Models\SupplierCsvMapping;
use App\Services\ComponentImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use League\Csv\Reader;
use Maatwebsite\Excel\Facades\Excel;

class ListSupplierCsvMappings extends ListRecords
{
    protected static string $resource = SupplierCsvMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            
            Actions\Action::make('auto_detect_mapping')
                ->label('Auto-rileva Mapping')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('supplier_id')
                        ->label('Fornitore')
                        ->options(Supplier::pluck('name', 'id'))
                        ->required()
                        ->helperText('Seleziona il fornitore per configurare il mapping'),
                        
                    Forms\Components\FileUpload::make('sample_file')
                        ->label('File di Esempio')
                        ->acceptedFileTypes([
                            'text/csv',
                            'application/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        ])
                        ->required()
                        ->directory('temp-mappings')
                        ->helperText('Carica un file CSV o Excel del fornitore per analizzare automaticamente le colonne'),
                        
                    Forms\Components\Toggle::make('clear_existing')
                        ->label('Cancella mapping esistenti')
                        ->default(false)
                        ->helperText('Se attivo, rimuove tutti i mapping esistenti per questo fornitore prima di creare i nuovi'),
                ])
                ->action(function (array $data) {
                    try {
                        $supplier = Supplier::find($data['supplier_id']);
                        $filePath = storage_path('app/public/' . $data['sample_file']);
                        
                        // Clear existing mappings if requested
                        if ($data['clear_existing']) {
                            SupplierCsvMapping::where('supplier_id', $supplier->id)->delete();
                        }
                        
                        // Detect file type
                        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                        $headers = [];
                        
                        if (in_array($extension, ['xlsx', 'xls'])) {
                            // Handle Excel file
                            $excelData = Excel::toArray(null, $filePath);
                            if (!empty($excelData) && !empty($excelData[0])) {
                                $headers = array_keys($excelData[0][0] ?? []);
                            }
                        } else {
                            // Handle CSV file
                            $csv = Reader::createFromPath($filePath, 'r');
                            $csv->setHeaderOffset(0);
                            $headers = $csv->getHeader();
                        }
                        
                        if (empty($headers)) {
                            throw new \Exception('Impossibile leggere le colonne dal file');
                        }
                        
                        // Get auto-detected mappings
                        $importService = app(ComponentImportService::class);
                        $detectedMappings = $this->detectMappings($headers, strtolower($supplier->name));
                        
                        // Create mappings in database
                        $created = 0;
                        $skipped = 0;
                        
                        foreach ($detectedMappings as $field => $csvColumn) {
                            // Check if mapping already exists
                            $exists = SupplierCsvMapping::where('supplier_id', $supplier->id)
                                ->where('field_name', $field)
                                ->exists();
                                
                            if ($exists && !$data['clear_existing']) {
                                $skipped++;
                                continue;
                            }
                            
                            // Determine data type based on field
                            $dataType = match($field) {
                                'unit_price', 'invoice_total' => 'decimal',
                                'stock_quantity', 'column_index' => 'integer',
                                'purchase_date', 'invoice_date' => 'date',
                                default => 'string'
                            };
                            
                            // Find column index
                            $columnIndex = array_search($csvColumn, $headers);
                            
                            SupplierCsvMapping::create([
                                'supplier_id' => $supplier->id,
                                'field_name' => $field,
                                'csv_column_name' => $csvColumn,
                                'column_index' => $columnIndex !== false ? $columnIndex : null,
                                'data_type' => $dataType,
                                'is_required' => in_array($field, ['manufacturer_part_number', 'description']),
                                'is_active' => true,
                            ]);
                            
                            $created++;
                        }
                        
                        // Show detected columns for reference
                        $message = "✅ Mapping creati: {$created}\n";
                        if ($skipped > 0) {
                            $message .= "⏭️ Mapping saltati (già esistenti): {$skipped}\n";
                        }
                        $message .= "\n📋 Colonne rilevate:\n";
                        $message .= implode(', ', array_slice($headers, 0, 10));
                        if (count($headers) > 10) {
                            $message .= " ... e altre " . (count($headers) - 10) . " colonne";
                        }
                        
                        Notification::make()
                            ->title('Mapping Auto-rilevati con Successo')
                            ->body($message)
                            ->success()
                            ->duration(10000)
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore durante l\'auto-rilevamento')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
    
    /**
     * Detect field mappings based on column headers
     */
    protected function detectMappings(array $headers, string $supplier): array
    {
        $mappings = [];
        
        // Predefined patterns for common suppliers
        $patterns = [
            'manufacturer_part_number' => [
                'patterns' => ['mfr', 'manufacturer.*part', 'mpn', 'p/n.*fabbricante', 'mfr.*no'],
                'exact' => ['Mfr. No:', 'Mfr Part #', 'Manufacturer Part Number']
            ],
            'description' => [
                'patterns' => ['desc', 'description', 'product.*desc'],
                'exact' => ['Desc.:', 'Description', 'Descrizione']
            ],
            'stock_quantity' => [
                'patterns' => ['qty', 'quantity', 'stock', 'giacenza', 'order.*qty'],
                'exact' => ['Order Qty.', 'Quantity', 'Stock']
            ],
            'unit_price' => [
                'patterns' => ['price.*eur', 'price.*usd', 'unit.*price', 'prezzo'],
                'exact' => ['Price (EUR)', 'Unit Price', 'Your Price'],
                'exclude' => ['ext', 'total', 'tariff']
            ],
            'manufacturer' => [
                'patterns' => ['manufacturer', 'mfg', 'fabbricante'],
                'exact' => ['Manufacturer', 'Mfg'],
                'exclude' => ['part', 'no', 'number']
            ],
            'supplier_part_number' => [
                'patterns' => ['mouser.*no', 'digikey.*part', 'supplier.*part'],
                'exact' => ['Mouser No:', 'DigiKey Part Number']
            ],
            'invoice_reference' => [
                'patterns' => ['invoice', 'fattura'],
                'exact' => ['Invoice No.', 'Invoice Number']
            ],
            'purchase_date' => [
                'patterns' => ['order.*date', 'purchase.*date'],
                'exact' => ['Order Date:', 'Purchase Date']
            ],
        ];
        
        foreach ($headers as $header) {
            $normalizedHeader = strtolower(trim($header));
            
            foreach ($patterns as $field => $config) {
                // Skip if already mapped
                if (isset($mappings[$field])) {
                    continue;
                }
                
                // Check exact matches first
                if (isset($config['exact']) && in_array($header, $config['exact'])) {
                    $mappings[$field] = $header;
                    continue;
                }
                
                // Check exclusions
                if (isset($config['exclude'])) {
                    $excluded = false;
                    foreach ($config['exclude'] as $exclude) {
                        if (str_contains($normalizedHeader, $exclude)) {
                            $excluded = true;
                            break;
                        }
                    }
                    if ($excluded) {
                        continue;
                    }
                }
                
                // Check patterns
                foreach ($config['patterns'] as $pattern) {
                    if (preg_match('/' . str_replace('.*', '.*?', $pattern) . '/i', $normalizedHeader)) {
                        $mappings[$field] = $header;
                        break;
                    }
                }
            }
        }
        
        return $mappings;
    }
}
