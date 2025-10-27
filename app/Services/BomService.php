<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Project;
use App\Models\ProjectBom;
use App\Models\ProjectBomItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class BomService
{
    /**
     * Parse BOM CSV file
     */
    public function parseBomCsv(string $filePath): array
    {
        $items = [];

        try {
            $disk = app('syncthing.paths')->disk('clients');
            
            if (!$disk->exists($filePath)) {
                throw new \Exception("BOM file not found: {$filePath}");
            }

            $content = $disk->get($filePath);
            $csv = Reader::createFromString($content);
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();

            foreach ($records as $record) {
                $item = $this->parseBomRecord($record);
                if ($item) {
                    $items[] = $item;
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to parse BOM CSV', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $items;
    }

    /**
     * Parse single BOM record
     */
    protected function parseBomRecord(array $record): ?array
    {
        // Common column mappings for KiCad BOM
        $reference = $record['Reference'] ?? $record['Ref'] ?? $record['Designator'] ?? null;
        $value = $record['Value'] ?? $record['Val'] ?? null;
        $footprint = $record['Footprint'] ?? $record['Package'] ?? null;
        $quantity = $record['Qty'] ?? $record['Quantity'] ?? 1;
        $manufacturerPart = $record['MPN'] ?? $record['Manufacturer Part Number'] ?? $record['Part Number'] ?? null;

        if (!$reference) {
            return null;
        }

        // Parse multiple references (e.g., "C1, C2, C3" or "C1-C3")
        $references = $this->parseReferences($reference);
        $totalQuantity = count($references) * (int)$quantity;

        return [
            'references' => $references,
            'value' => $value,
            'footprint' => $footprint,
            'manufacturer_part' => $manufacturerPart,
            'quantity' => $totalQuantity,
        ];
    }

    /**
     * Parse reference designators
     */
    protected function parseReferences(string $reference): array
    {
        $references = [];
        
        // Split by comma
        $parts = explode(',', $reference);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            // Check for range (e.g., "C1-C5")
            if (preg_match('/([A-Z]+)(\d+)-([A-Z]+)?(\d+)/', $part, $matches)) {
                $prefix = $matches[1];
                $start = (int)$matches[2];
                $end = (int)$matches[4];
                
                for ($i = $start; $i <= $end; $i++) {
                    $references[] = $prefix . $i;
                }
            } else {
                $references[] = $part;
            }
        }

        return $references;
    }

    /**
     * Import BOM for project
     */
    public function importProjectBom(Project $project, string $bomFilePath): ProjectBom
    {
        DB::beginTransaction();

        try {
            // Parse BOM file
            $items = $this->parseBomCsv($bomFilePath);

            // Get full path from Syncthing disk
            $disk = app('syncthing.paths')->disk('clients');
            $fullPath = $disk->path($bomFilePath);

            // Create BOM record with temporary marker to upload to Nextcloud
            $bom = ProjectBom::create([
                'project_id' => $project->id,
                'file_path' => basename($bomFilePath),
                'folder_path' => dirname($bomFilePath),
                'components_data' => $items,
                'status' => 'pending',
                'uploaded_file_path' => $fullPath, // This triggers Nextcloud upload in observer
            ]);

            // Create BOM items
            foreach ($items as $itemData) {
                foreach ($itemData['references'] as $reference) {
                    ProjectBomItem::create([
                        'project_bom_id' => $bom->id,
                        'reference' => $reference,
                        'value' => $itemData['value'],
                        'footprint' => $itemData['footprint'],
                        'manufacturer_part' => $itemData['manufacturer_part'],
                        'quantity' => 1, // Each reference is 1 unit
                        'allocated' => false,
                    ]);
                }
            }

            DB::commit();
            return $bom;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to import BOM', [
                'project' => $project->code,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Auto-allocate components to BOM items
     *
     * @deprecated Use BomAllocationService::allocateBom() instead
     */
    public function autoAllocateBomComponents(ProjectBom $bom): array
    {
        // Delegate to new BomAllocationService
        $allocationService = app(\App\Services\BomAllocationService::class);
        $results = $allocationService->allocateBom($bom);

        // Transform results to match old format for backward compatibility
        return [
            'allocated' => $results['allocated'],
            'failed' => $results['errors'] + $results['insufficient_stock'] + $results['no_component'],
            'insufficient_stock' => $results['insufficient_stock_items'],
            'not_found' => array_filter($results['details'], fn($d) => ($d['reason'] ?? '') === 'no_component'),
        ];
    }

    /**
     * Find matching component for BOM item
     */
    protected function findMatchingComponent(ProjectBomItem $item): ?Component
    {
        // First try by manufacturer part number
        if ($item->manufacturer_part) {
            $component = Component::where('manufacturer_part_number', $item->manufacturer_part)
                ->where('status', 'active')
                ->first();
            
            if ($component) {
                return $component;
            }
        }

        // Try by value and footprint
        if ($item->value && $item->footprint) {
            $component = Component::where('name', 'LIKE', "%{$item->value}%")
                ->where('package', $item->footprint)
                ->where('status', 'active')
                ->first();
            
            if ($component) {
                return $component;
            }
        }

        // Try fuzzy search by value
        if ($item->value) {
            // Parse common component values
            $parsedValue = $this->parseComponentValue($item->value);
            
            if ($parsedValue) {
                $component = Component::where('specifications->value', $parsedValue['value'])
                    ->where('specifications->unit', $parsedValue['unit'])
                    ->where('status', 'active')
                    ->first();
                
                if ($component) {
                    return $component;
                }
            }
        }

        return null;
    }

    /**
     * Parse component value (e.g., "10k", "100nF", "10uF")
     */
    protected function parseComponentValue(string $value): ?array
    {
        // Remove spaces
        $value = str_replace(' ', '', $value);

        // Common patterns
        $patterns = [
            // Resistors: 10k, 4.7k, 100R
            '/^(\d+(?:\.\d+)?)(k|K|R|M)?(?:Ω|ohm)?$/i' => function($matches) {
                $num = (float)$matches[1];
                $mult = strtoupper($matches[2] ?? '');
                
                return [
                    'value' => $num * ($mult === 'K' ? 1000 : ($mult === 'M' ? 1000000 : 1)),
                    'unit' => 'Ω',
                ];
            },
            // Capacitors: 100nF, 10uF, 22pF
            '/^(\d+(?:\.\d+)?)(p|n|u|µ|m)?F$/i' => function($matches) {
                $num = (float)$matches[1];
                $mult = strtolower($matches[2] ?? '');
                
                $multipliers = [
                    'p' => 1e-12,
                    'n' => 1e-9,
                    'u' => 1e-6,
                    'µ' => 1e-6,
                    'm' => 1e-3,
                ];
                
                return [
                    'value' => $num * ($multipliers[$mult] ?? 1),
                    'unit' => 'F',
                ];
            },
            // Inductors: 10uH, 100mH
            '/^(\d+(?:\.\d+)?)(n|u|µ|m)?H$/i' => function($matches) {
                $num = (float)$matches[1];
                $mult = strtolower($matches[2] ?? '');
                
                $multipliers = [
                    'n' => 1e-9,
                    'u' => 1e-6,
                    'µ' => 1e-6,
                    'm' => 1e-3,
                ];
                
                return [
                    'value' => $num * ($multipliers[$mult] ?? 1),
                    'unit' => 'H',
                ];
            },
        ];

        foreach ($patterns as $pattern => $parser) {
            if (preg_match($pattern, $value, $matches)) {
                return $parser($matches);
            }
        }

        return null;
    }

    /**
     * Generate BOM summary for project
     */
    public function generateBomSummary(ProjectBom $bom): array
    {
        $summary = [
            'total_items' => $bom->items->count(),
            'allocated_items' => $bom->items->where('allocated', true)->count(),
            'total_components' => 0,
            'total_cost' => 0,
            'by_category' => [],
        ];

        foreach ($bom->items as $item) {
            if ($item->allocated && $item->component) {
                $summary['total_components'] += $item->quantity;
                $summary['total_cost'] += $item->quantity * $item->component->unit_price;

                $categoryName = $item->component->category->name ?? 'Uncategorized';
                
                if (!isset($summary['by_category'][$categoryName])) {
                    $summary['by_category'][$categoryName] = [
                        'count' => 0,
                        'cost' => 0,
                    ];
                }

                $summary['by_category'][$categoryName]['count'] += $item->quantity;
                $summary['by_category'][$categoryName]['cost'] += $item->quantity * $item->component->unit_price;
            }
        }

        return $summary;
    }
}