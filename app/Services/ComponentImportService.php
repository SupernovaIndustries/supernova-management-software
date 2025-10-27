<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Models\SupplierCsvMapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ComponentImportService
{
    /**
     * AI Category Service instance
     */
    protected ?AiCategoryService $aiCategoryService = null;

    /**
     * Currency Exchange Service instance
     */
    protected ?CurrencyExchangeService $currencyService = null;

    /**
     * Datasheet Scraper Service instance
     */
    protected ?DatasheetScraperService $datasheetScraperService = null;

    /**
     * Flag to enable/disable AI category generation
     */
    protected bool $useAiCategories = true;

    /**
     * Flag to enable/disable automatic currency conversion
     */
    protected bool $autoConvertCurrency = true;

    /**
     * Flag to enable/disable datasheet scraping for specification extraction
     */
    protected bool $useDatasheetScraper = true;

    /**
     * Progress callback function
     */
    protected $progressCallback = null;

    /**
     * User ID for inventory movements (used in queued job context)
     */
    protected ?int $userId = null;

    /**
     * Import ID for tracking this import session
     */
    protected ?int $importId = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize AI category service if available
        try {
            $this->aiCategoryService = app(AiCategoryService::class);
        } catch (\Exception $e) {
            Log::warning('AI Category Service not available', ['error' => $e->getMessage()]);
            $this->aiCategoryService = null;
        }

        // Initialize Currency Exchange service
        try {
            $this->currencyService = app(CurrencyExchangeService::class);
        } catch (\Exception $e) {
            Log::warning('Currency Exchange Service not available', ['error' => $e->getMessage()]);
            $this->currencyService = null;
        }

        // Initialize Datasheet Scraper service
        try {
            $this->datasheetScraperService = app(DatasheetScraperService::class);
        } catch (\Exception $e) {
            Log::warning('Datasheet Scraper Service not available', ['error' => $e->getMessage()]);
            $this->datasheetScraperService = null;
        }
    }

    protected array $mouserColumns = [
        'Mouser Part #' => 'mouser_part',
        'Mouser No' => 'mouser_part',
        'Mouser No:' => 'mouser_part',  // Sales order export format
        'n_mouser' => 'mouser_part',  // Excel normalized Italian
        'Codice Mouser' => 'mouser_part',
        'Mfr Part #' => 'manufacturer_part_number',
        'Mfr. Part #' => 'manufacturer_part_number',
        'Mfr. No:' => 'manufacturer_part_number',  // Sales order export format
        'n_produttore' => 'manufacturer_part_number',  // Excel normalized Italian
        'P/N fabbricante' => 'manufacturer_part_number',
        'Manufacturer' => 'manufacturer',
        'Fabbricante' => 'manufacturer',
        'Description' => 'description',
        'Descrizione' => 'description',
        'Desc.:' => 'description',  // Sales order export format
        'desc' => 'description',  // Excel normalized Italian
        'Stock' => 'stock',
        'Giacenza' => 'stock',
        'Qta disponibile' => 'stock',
        // Ordered Quantity (for inventory movements)
        'Qty' => 'ordered_quantity',
        'Quantity' => 'ordered_quantity',
        'Ordered Qty' => 'ordered_quantity',
        'Qty Ordered' => 'ordered_quantity',
        'Order Qty.' => 'ordered_quantity',  // Sales order export format
        'qta_ordine' => 'ordered_quantity',  // Excel normalized Italian
        'Quantità' => 'ordered_quantity',
        'Quantità Ordinata' => 'ordered_quantity',
        'Your Price' => 'unit_price',
        'Il vostro prezzo' => 'unit_price',
        'Prezzo unitario' => 'unit_price',
        'Unit Price' => 'unit_price',
        'Price' => 'unit_price',
        'Price (EUR)' => 'unit_price',  // Sales order export format
        'prezzo_eur' => 'unit_price',  // Excel normalized Italian
        'Prezzo' => 'unit_price',
        'Lifecycle' => 'lifecycle',
        'Package' => 'package',
        'Confezione' => 'package',
        'RoHS' => 'rohs',
    ];

    protected array $digikeyColumns = [
        'Digi-Key Part Number' => 'digikey_part',
        'DigiKey Part Number' => 'digikey_part',
        'DK Part Number' => 'digikey_part',
        'Manufacturer Part Number' => 'manufacturer_part_number',
        'Mfr Part Number' => 'manufacturer_part_number',
        'MPN' => 'manufacturer_part_number',
        'Manufacturer' => 'manufacturer',
        'Mfg' => 'manufacturer',
        'Description' => 'description',
        'Product Description' => 'description',
        'Quantity Available' => 'stock',
        'Available Quantity' => 'stock',
        'Stock' => 'stock',
        'Qty Available' => 'stock',
        'Giacenza' => 'stock',
        'Disponibilità' => 'stock',
        // Ordered Quantity (for inventory movements)
        'Qty' => 'ordered_quantity',
        'Quantity' => 'ordered_quantity',
        'Ordered Qty' => 'ordered_quantity',
        'Qty Ordered' => 'ordered_quantity',
        'Quantità' => 'ordered_quantity',
        'Quantità Ordinata' => 'ordered_quantity',
        'Unit Price' => 'unit_price',
        'Unit Price (USD)' => 'unit_price',
        'Unit Price (EUR)' => 'unit_price',
        'Price' => 'unit_price',
        'Extended Price' => 'extended_price',
        'Packaging' => 'package',
        'Package' => 'package',
        'Series' => 'series',
        'Category' => 'category_name',
    ];

    protected array $farnellColumns = [
        'Order Code' => 'farnell_part',
        'Manufacturer Part Number' => 'manufacturer_part_number',
        'Manufacturer Name' => 'manufacturer',
        'Description' => 'description',
        'Stock Level' => 'stock',
        'Price For' => 'unit_price',
    ];

    /**
     * Get field mapping from database for supplier
     */
    protected function getDatabaseMapping(string $supplier): ?array
    {
        $supplierModel = Supplier::where('name', 'LIKE', '%' . $supplier . '%')->first();
        
        if (!$supplierModel) {
            Log::info('Supplier not found in database, using auto-detection', ['supplier' => $supplier]);
            return null;
        }
        
        $mappings = SupplierCsvMapping::where('supplier_id', $supplierModel->id)
            ->where('is_active', true)
            ->get();
        
        if ($mappings->isEmpty()) {
            Log::info('No mappings found for supplier, using auto-detection', ['supplier' => $supplier]);
            return null;
        }
        
        $fieldMapping = [];
        foreach ($mappings as $mapping) {
            $fieldMapping[$mapping->field_name] = $mapping->csv_column_name;
        }
        
        Log::info('Using database mappings for supplier', [
            'supplier' => $supplier,
            'mapping' => $fieldMapping
        ]);
        
        return $fieldMapping;
    }

    /**
     * Import components from Excel file with intelligent field detection and categorization
     */
    public function importFromExcel(string $filePath, string $supplier, ?array $fieldMapping = null, ?array $invoiceData = null): array
    {
        // Set execution time to 5 minutes for large imports with file uploads
        set_time_limit(300);

        $results = [
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'invoice_saved' => false,
            'movements_created' => 0,
            'imported_details' => [],
            'updated_details' => [],
            'skipped_details' => [],
        ];

        try {
            // Import Excel file as array
            $data = Excel::toArray(new class implements ToArray, WithHeadingRow, SkipsEmptyRows {
                public function array(array $array): array
                {
                    return $array;
                }
            }, $filePath);

            if (empty($data) || empty($data[0])) {
                throw new \Exception("Excel file is empty or cannot be read");
            }

            $rows = $data[0]; // First sheet
            $headers = array_keys($rows[0] ?? []);

            $totalRows = count($rows);

            Log::info('Excel import started', [
                'supplier' => $supplier,
                'total_rows' => $totalRows
            ]);

            // Report initial progress
            $this->reportProgress(0, $totalRows, "Elaborazione {$totalRows} componenti...");

            // Try to get mapping from database first, then fallback to auto-detection
            if (!$fieldMapping) {
                $fieldMapping = $this->getDatabaseMapping($supplier);
                if (!$fieldMapping) {
                    $fieldMapping = $this->autoDetectFieldsFromHeaders($headers, $supplier);
                }
            }

            // Check if we have essential mappings
            if (empty($fieldMapping) || !isset($fieldMapping['manufacturer_part_number'])) {
                throw new \Exception("Cannot detect essential fields in Excel. Missing manufacturer part number mapping. Headers: " . implode(', ', $headers));
            }

            // Optimize: Pre-load existing components to avoid N+1 queries
            $manufacturerPartNumbers = [];
            foreach ($rows as $record) {
                $componentData = $this->mapRecordToComponentWithAutoDetection($record, $fieldMapping, $supplier);
                if (!empty($componentData['manufacturer_part_number'])) {
                    $manufacturerPartNumbers[] = $componentData['manufacturer_part_number'];
                }
            }
            
            $existingComponents = Component::whereIn('manufacturer_part_number', $manufacturerPartNumbers)
                ->pluck('id', 'manufacturer_part_number')
                ->toArray();
            
            // Cache categories to avoid repeated queries
            $categoryCache = [];
            
            // Process in batches for better performance
            $batchSize = 50;
            $batches = array_chunk($rows, $batchSize, true);

            $processedCount = 0;

            foreach ($batches as $batch) {
                $movementsToCreate = [];

                foreach ($batch as $offset => $record) {
                    try {
                        $componentData = $this->mapRecordToComponentWithAutoDetection($record, $fieldMapping, $supplier);

                        // Skip rows without manufacturer part number (summary rows, empty rows, etc.)
                        if (empty($componentData['manufacturer_part_number'])) {
                            $results['skipped']++;
                            $processedCount++;

                            // Log detailed skip reason
                            $description = $componentData['description'] ?? 'N/A';
                            $skipReason = "Riga #{$offset}: Saltato - MPN mancante";
                            if (!empty($description) && $description !== 'N/A') {
                                $skipReason .= " (Descrizione: " . Str::limit($description, 50) . ")";
                            }

                            // Track skipped details
                            $results['skipped_details'][] = [
                                'row' => $offset,
                                'mpn' => 'N/A',
                                'description' => Str::limit($description, 50),
                                'reason' => 'MPN mancante'
                            ];

                            Log::info($skipReason);
                            $this->reportProgress($processedCount, $totalRows, "⏭️ {$skipReason}");

                            continue;
                        }

                        // Validate data
                        $validationErrors = $this->validateComponentData($componentData);
                        if (!empty($validationErrors)) {
                            throw new \Exception('Validation failed: ' . implode(', ', $validationErrors));
                        }
                        
                        // Use cached category detection (AI-powered or fallback)
                        $description = $componentData['description'] ?? '';
                        $manufacturer = $componentData['manufacturer'] ?? null;
                        $cacheKey = md5($description . '|' . $manufacturer);

                        if (!isset($categoryCache[$cacheKey])) {
                            $categoryCache[$cacheKey] = $this->intelligentCategoryDetection($description, $manufacturer);
                        }
                        $categoryId = $categoryCache[$cacheKey];
                        
                        // Extract technical specifications
                        $technicalSpecs = $this->extractTechnicalSpecifications($description);
                        $componentData = array_merge($componentData, $technicalSpecs);
                        
                        // Check if component exists
                        $wasExisting = isset($existingComponents[$componentData['manufacturer_part_number']]);
                        
                        $component = $this->importComponent($componentData, $categoryId, $supplier);
                        if ($component) {
                            // Log detailed success
                            $mpn = $componentData['manufacturer_part_number'];
                            $desc = Str::limit($componentData['description'] ?? '', 50);
                            $categoryName = Category::find($categoryId)?->name ?? 'N/A';

                            if ($wasExisting) {
                                $results['updated']++;
                                $logMsg = "✏️ Aggiornato: {$mpn} - {$desc} (Categoria: {$categoryName})";

                                // Track updated details
                                $results['updated_details'][] = [
                                    'mpn' => $mpn,
                                    'description' => $desc,
                                    'category' => $categoryName,
                                    'sku' => $component->sku
                                ];

                                Log::info($logMsg);
                                $this->reportProgress($processedCount, $totalRows, $logMsg);
                            } else {
                                $results['imported']++;
                                // Update the cache with new component
                                $existingComponents[$componentData['manufacturer_part_number']] = $component->id;
                                $logMsg = "✅ Importato: {$mpn} - {$desc} (Categoria: {$categoryName})";

                                // Track imported details
                                $results['imported_details'][] = [
                                    'mpn' => $mpn,
                                    'description' => $desc,
                                    'category' => $categoryName,
                                    'sku' => $component->sku
                                ];

                                Log::info($logMsg);
                                $this->reportProgress($processedCount, $totalRows, $logMsg);
                            }

                            // Collect inventory movements for batch creation
                            // Use ordered_quantity if present, otherwise fallback to stock
                            $quantityForMovement = $componentData['ordered_quantity'] ?? $componentData['stock'] ?? 0;
                            if ($invoiceData && $quantityForMovement > 0) {
                                // Ensure ordered_quantity is set for movement creation
                                $componentData['ordered_quantity'] = $quantityForMovement;
                                $movementsToCreate[] = [
                                    'component' => $component,
                                    'data' => $componentData,
                                    'invoice' => $invoiceData
                                ];
                            }
                        }

                        // Increment processed count (detailed progress is logged above)
                        $processedCount++;

                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['skipped']++;
                        $results['errors'][] = "Row {$offset}: " . $e->getMessage();

                        // Log detailed error with component info
                        $mpn = $record['manufacturer_part_number'] ?? $record['Mfr. Part #'] ?? $record['P/N fabbricante'] ?? 'N/A';
                        $desc = $record['description'] ?? $record['Description'] ?? $record['Descrizione'] ?? 'N/A';
                        $errorReason = "Riga #{$offset}: ❌ Errore - {$mpn}";
                        if ($desc !== 'N/A') {
                            $errorReason .= " (" . Str::limit($desc, 40) . ")";
                        }
                        $errorReason .= " - Motivo: {$e->getMessage()}";

                        // Track skipped details with error
                        $results['skipped_details'][] = [
                            'row' => $offset,
                            'mpn' => $mpn,
                            'description' => Str::limit($desc, 50),
                            'reason' => $e->getMessage()
                        ];

                        Log::error($errorReason);
                        $this->reportProgress($processedCount, $totalRows, "❌ {$errorReason}");

                        $this->logImportError($offset, $record, $e, $supplier);
                        $processedCount++;
                    }
                }

                // Batch create inventory movements
                if (!empty($movementsToCreate)) {
                    $this->createInventoryMovementsBatch($movementsToCreate);
                    $results['movements_created'] += count($movementsToCreate);
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = 'Failed to read Excel file: ' . $e->getMessage();
            Log::error('Excel import failed', ['error' => $e->getMessage()]);
        }

        // Mark invoice as saved if movements were created
        if ($invoiceData && $results['movements_created'] > 0) {
            $results['invoice_saved'] = true;
        }

        return $results;
    }

    /**
     * Import components from CSV file with intelligent field detection and categorization
     */
    public function importFromCsv(string $filePath, string $supplier, ?array $fieldMapping = null, ?array $invoiceData = null): array
    {
        // Set execution time to 5 minutes for large imports with file uploads
        set_time_limit(300);

        $results = [
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'invoice_saved' => false,
            'movements_created' => 0,
            'imported_details' => [],
            'updated_details' => [],
            'skipped_details' => [],
        ];

        try {
            $csv = Reader::createFromPath($filePath, 'r');
            
            // Auto-detect delimiter and encoding
            $this->configureCSV($csv, $supplier);
            
            // Try to get mapping from database first, then fallback to auto-detection
            if (!$fieldMapping) {
                $fieldMapping = $this->getDatabaseMapping($supplier);
                if (!$fieldMapping) {
                    $fieldMapping = $this->autoDetectFields($csv, $supplier);
                }
            }
            
            // Check if we have essential mappings
            if (empty($fieldMapping) || !isset($fieldMapping['manufacturer_part_number'])) {
                throw new \Exception("Cannot detect essential fields in CSV. Missing manufacturer part number mapping.");
            }
            
            $records = $csv->getRecords();

            // Convert records to array for optimization
            $recordsArray = iterator_to_array($records);

            $totalRows = count($recordsArray);

            Log::info('CSV import started', [
                'supplier' => $supplier,
                'total_rows' => $totalRows
            ]);

            // Report initial progress
            $this->reportProgress(0, $totalRows, "Elaborazione {$totalRows} componenti...");
            
            // Optimize: Pre-load existing components to avoid N+1 queries
            $manufacturerPartNumbers = [];
            foreach ($recordsArray as $record) {
                $componentData = $this->mapRecordToComponentWithAutoDetection($record, $fieldMapping, $supplier);
                if (!empty($componentData['manufacturer_part_number'])) {
                    $manufacturerPartNumbers[] = $componentData['manufacturer_part_number'];
                }
            }
            
            $existingComponents = Component::whereIn('manufacturer_part_number', $manufacturerPartNumbers)
                ->pluck('id', 'manufacturer_part_number')
                ->toArray();
            
            // Cache categories to avoid repeated queries
            $categoryCache = [];
            
            // Process in batches for better performance
            $batchSize = 50;
            $batches = array_chunk($recordsArray, $batchSize, true);

            $processedCount = 0;

            foreach ($batches as $batch) {
                $movementsToCreate = [];

                foreach ($batch as $offset => $record) {
                    try {
                        $componentData = $this->mapRecordToComponentWithAutoDetection($record, $fieldMapping, $supplier);

                        // Skip rows without manufacturer part number (summary rows, empty rows, etc.)
                        if (empty($componentData['manufacturer_part_number'])) {
                            $results['skipped']++;
                            $processedCount++;

                            // Log detailed skip reason
                            $description = $componentData['description'] ?? 'N/A';
                            $skipReason = "Riga #{$offset}: Saltato - MPN mancante";
                            if (!empty($description) && $description !== 'N/A') {
                                $skipReason .= " (Descrizione: " . Str::limit($description, 50) . ")";
                            }

                            // Track skipped details
                            $results['skipped_details'][] = [
                                'row' => $offset,
                                'mpn' => 'N/A',
                                'description' => Str::limit($description, 50),
                                'reason' => 'MPN mancante'
                            ];

                            Log::info($skipReason);
                            $this->reportProgress($processedCount, $totalRows, "⏭️ {$skipReason}");

                            continue;
                        }

                        // Use cached category detection (AI-powered or fallback)
                        $description = $componentData['description'] ?? '';
                        $manufacturer = $componentData['manufacturer'] ?? null;
                        $cacheKey = md5($description . '|' . $manufacturer);

                        if (!isset($categoryCache[$cacheKey])) {
                            $categoryCache[$cacheKey] = $this->intelligentCategoryDetection($description, $manufacturer);
                        }
                        $categoryId = $categoryCache[$cacheKey];
                        
                        // Extract technical specifications
                        $technicalSpecs = $this->extractTechnicalSpecifications($description);
                        $componentData = array_merge($componentData, $technicalSpecs);
                        
                        // Check if component exists
                        $wasExisting = isset($existingComponents[$componentData['manufacturer_part_number']]);
                        
                        $component = $this->importComponent($componentData, $categoryId, $supplier);
                        if ($component) {
                            // Log detailed success
                            $mpn = $componentData['manufacturer_part_number'];
                            $desc = Str::limit($componentData['description'] ?? '', 50);
                            $categoryName = Category::find($categoryId)?->name ?? 'N/A';

                            if ($wasExisting) {
                                $results['updated']++;
                                $logMsg = "✏️ Aggiornato: {$mpn} - {$desc} (Categoria: {$categoryName})";

                                // Track updated details
                                $results['updated_details'][] = [
                                    'mpn' => $mpn,
                                    'description' => $desc,
                                    'category' => $categoryName,
                                    'sku' => $component->sku
                                ];

                                Log::info($logMsg);
                                $this->reportProgress($processedCount, $totalRows, $logMsg);
                            } else {
                                $results['imported']++;
                                // Update the cache with new component
                                $existingComponents[$componentData['manufacturer_part_number']] = $component->id;
                                $logMsg = "✅ Importato: {$mpn} - {$desc} (Categoria: {$categoryName})";

                                // Track imported details
                                $results['imported_details'][] = [
                                    'mpn' => $mpn,
                                    'description' => $desc,
                                    'category' => $categoryName,
                                    'sku' => $component->sku
                                ];

                                Log::info($logMsg);
                                $this->reportProgress($processedCount, $totalRows, $logMsg);
                            }

                            // Collect inventory movements for batch creation
                            // Use ordered_quantity if present, otherwise fallback to stock
                            $quantityForMovement = $componentData['ordered_quantity'] ?? $componentData['stock'] ?? 0;
                            if ($invoiceData && $quantityForMovement > 0) {
                                // Ensure ordered_quantity is set for movement creation
                                $componentData['ordered_quantity'] = $quantityForMovement;
                                $movementsToCreate[] = [
                                    'component' => $component,
                                    'data' => $componentData,
                                    'invoice' => $invoiceData
                                ];
                            }
                        }

                        // Increment processed count (detailed progress is logged above)
                        $processedCount++;

                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['skipped']++;
                        $results['errors'][] = "Row {$offset}: " . $e->getMessage();

                        // Log detailed error with component info
                        $mpn = $record['manufacturer_part_number'] ?? $record['Mfr. Part #'] ?? $record['P/N fabbricante'] ?? 'N/A';
                        $desc = $record['description'] ?? $record['Description'] ?? $record['Descrizione'] ?? 'N/A';
                        $errorReason = "Riga #{$offset}: ❌ Errore - {$mpn}";
                        if ($desc !== 'N/A') {
                            $errorReason .= " (" . Str::limit($desc, 40) . ")";
                        }
                        $errorReason .= " - Motivo: {$e->getMessage()}";

                        // Track skipped details with error
                        $results['skipped_details'][] = [
                            'row' => $offset,
                            'mpn' => $mpn,
                            'description' => Str::limit($desc, 50),
                            'reason' => $e->getMessage()
                        ];

                        Log::error($errorReason);
                        $this->reportProgress($processedCount, $totalRows, "❌ {$errorReason}");

                        $this->logImportError($offset, $record, $e, $supplier);
                        $processedCount++;
                    }
                }

                // Batch create inventory movements
                if (!empty($movementsToCreate)) {
                    $this->createInventoryMovementsBatch($movementsToCreate);
                    $results['movements_created'] += count($movementsToCreate);
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = 'Failed to read CSV file: ' . $e->getMessage();
            Log::error('CSV import failed', ['error' => $e->getMessage()]);
        }

        // Mark invoice as saved if movements were created
        if ($invoiceData && $results['movements_created'] > 0) {
            $results['invoice_saved'] = true;
        }

        return $results;
    }

    /**
     * Get column mapping for supplier
     */
    protected function getColumnMap(string $supplier): array
    {
        return match (strtolower($supplier)) {
            'mouser' => $this->mouserColumns,
            'digikey' => $this->digikeyColumns,
            'farnell' => $this->farnellColumns,
            default => throw new \InvalidArgumentException("Unknown supplier: {$supplier}"),
        };
    }

    /**
     * Map CSV record to component data
     */
    protected function mapRecordToComponent(array $record, array $columnMap, string $supplier): array
    {
        $data = [];
        
        foreach ($columnMap as $csvColumn => $componentField) {
            $value = $record[$csvColumn] ?? null;
            
            if ($value !== null) {
                // Clean and transform values
                switch ($componentField) {
                    case 'unit_price':
                        $data[$componentField] = $this->parsePrice($value);
                        break;
                    case 'stock':
                        $data[$componentField] = $this->parseStock($value);
                        break;
                    default:
                        $data[$componentField] = trim($value);
                }
            }
        }

        // Add supplier-specific part number
        $supplierPartField = strtolower($supplier) . '_part';
        if (isset($data[$supplierPartField])) {
            $data['supplier_part_number'] = $data[$supplierPartField];
        }

        return $data;
    }

    /**
     * Import single component
     */
    protected function importComponent(array $data, int $categoryId, string $supplier): ?Component
    {
        try {
            if (empty($data['manufacturer_part_number'])) {
                throw new \Exception('Missing manufacturer part number');
            }

            $sku = $this->generateSku($supplier, $data['supplier_part_number'] ?? $data['manufacturer_part_number']);
            
            // Check if component exists
            $existingComponent = Component::where('manufacturer_part_number', $data['manufacturer_part_number'])->first();

            $updateData = [
                'sku' => $sku,
                'name' => $data['description'] ?? $data['manufacturer_part_number'],
                'description' => $data['description'] ?? '',
                'category_id' => $categoryId,
                'manufacturer' => $data['manufacturer'] ?? 'Unknown',
                'package' => $data['package'] ?? null,
                'unit_price' => $data['unit_price'] ?? 0,
                'status' => 'active',
                'supplier_links' => $this->addSupplierLink(
                    $existingComponent->supplier_links ?? [],
                    $supplier,
                    $data['supplier_part_number'] ?? null
                ),
            ];

            // Track import ID for new components only (don't override for updates)
            if (!$existingComponent && $this->importId) {
                $updateData['import_id'] = $this->importId;
            }

            // IMPORTANT: stock_quantity is NOT updated for existing components
            // It's managed exclusively by inventory movements
            // For new components, it will default to 0 (database default)
            
            // Add technical specifications if present
            foreach (['value', 'tolerance', 'voltage_rating', 'current_rating', 'power_rating', 
                     'package_type', 'mounting_type', 'case_style', 'dielectric', 
                     'temperature_coefficient', 'operating_temperature'] as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            $component = Component::updateOrCreate(
                ['manufacturer_part_number' => $data['manufacturer_part_number']],
                $updateData
            );

            // Enrich component with datasheet specifications if scraper is enabled
            if ($this->useDatasheetScraper && $this->datasheetScraperService && $this->datasheetScraperService->isEnabled()) {
                try {
                    $additionalSpecs = $this->datasheetScraperService->extractSpecifications($component);
                    if (!empty($additionalSpecs)) {
                        // Only update fields that are empty
                        $specsToUpdate = [];
                        foreach ($additionalSpecs as $field => $value) {
                            if (empty($component->$field) && !empty($value)) {
                                $specsToUpdate[$field] = $value;
                            }
                        }

                        if (!empty($specsToUpdate)) {
                            $component->update($specsToUpdate);
                            Log::info('Component enriched with datasheet specs', [
                                'sku' => $component->sku,
                                'mpn' => $component->manufacturer_part_number,
                                'fields_enriched' => array_keys($specsToUpdate)
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to enrich component with datasheet specs', [
                        'error' => $e->getMessage(),
                        'sku' => $component->sku
                    ]);
                }
            }

            return $component;
        } catch (\Exception $e) {
            Log::error('Failed to import component', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Create inventory movements in batch for better performance
     */
    protected function createInventoryMovementsBatch(array $movementsData): void
    {
        try {
            $movements = [];
            
            foreach ($movementsData as $movementData) {
                $component = $movementData['component'];
                $componentData = $movementData['data'];
                $invoiceData = $movementData['invoice'];

                // Use ordered_quantity if present, otherwise fallback to stock
                $quantity = (int) ($componentData['ordered_quantity'] ?? $componentData['stock'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                // Refresh component to get current stock from database (may be NULL for new components)
                $component->refresh();
                $currentStock = $component->stock_quantity ?? 0;
                $afterStock = $currentStock + $quantity;

                Log::info('Creating inventory movement', [
                    'component_sku' => $component->sku,
                    'quantity' => $quantity,
                    'current_stock' => $currentStock,
                    'after_stock' => $afterStock,
                    'source' => isset($componentData['ordered_quantity']) ? 'ordered_quantity' : 'stock',
                    'invoice_number' => $invoiceData['invoice_number'] ?? 'N/A'
                ]);

                $movements[] = [
                    'component_id' => $component->id,
                    'import_id' => $this->importId,
                    'type' => 'in',
                    'quantity' => $quantity,
                    'quantity_before' => $currentStock,
                    'quantity_after' => $afterStock,
                    'unit_cost' => $componentData['unit_price'] ?? 0,
                    'reference_type' => 'import',
                    'reference_id' => null,
                    'reason' => 'Import from ' . ucfirst($invoiceData['supplier']),
                    'notes' => $invoiceData['notes'] ?? "Imported from {$invoiceData['supplier']} file",
                    'user_id' => $this->userId ?? Auth::id(),
                    'invoice_number' => $invoiceData['invoice_number'] ?? null,
                    'invoice_path' => $invoiceData['invoice_path'] ?? null,
                    'invoice_date' => $invoiceData['invoice_date'] ?? null,
                    'invoice_total' => $invoiceData['invoice_total'] ?? null,
                    'supplier' => $invoiceData['supplier'],
                    'destination_project_id' => $invoiceData['project_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            if (!empty($movements)) {
                // Batch insert all movements
                InventoryMovement::insert($movements);

                // Update component stock quantities based on movements
                $stockUpdates = [];
                foreach ($movements as $movement) {
                    $componentId = $movement['component_id'];
                    $stockUpdates[$componentId] = $movement['quantity_after'];
                }

                foreach ($stockUpdates as $componentId => $newStock) {
                    Component::where('id', $componentId)->update(['stock_quantity' => $newStock]);
                }

                Log::info('Batch inventory movements created and stock updated', [
                    'movements_count' => count($movements),
                    'components_updated' => count($stockUpdates),
                    'invoice_number' => $invoiceData['invoice_number'] ?? 'N/A'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to create batch inventory movements', [
                'error' => $e->getMessage(),
                'count' => count($movementsData)
            ]);
        }
    }

    /**
     * Parse price from various formats and convert to EUR if needed
     */
    protected function parsePrice(string $value): float
    {
        if (empty($value) || trim($value) === '') {
            return 0.0;
        }

        $originalValue = $value;

        // Detect currency before removing symbols
        $detectedCurrency = null;
        if ($this->autoConvertCurrency && $this->currencyService) {
            $detectedCurrency = $this->currencyService->detectCurrency($originalValue);
        }

        // Remove common currency symbols and text (including corrupted € symbol)
        $value = preg_replace('/[€$£¥₹\?]/', '', $value);
        $value = preg_replace('/\b(USD|EUR|GBP|JPY|INR)\b/i', '', $value);
        $value = trim($value);

        // Handle special cases for corrupted currency symbols
        // "? 0,447" or "� 0,447" becomes "0,447"
        if (preg_match('/^[\?\�]\s*(.+)$/', $originalValue, $matches)) {
            $value = trim($matches[1]);
        }

        // Also handle cases where ? appears anywhere: "0,447 ?" becomes "0,447"
        $value = preg_replace('/\s*[\?\�]\s*/', '', $value);

        // Handle different decimal separators
        // European format: 1.234,56 or 1 234,56
        if (preg_match('/^[\d\s\.]+,\d{1,4}$/', $value)) {
            $value = str_replace([' ', '.'], '', $value);
            $value = str_replace(',', '.', $value);
        }
        // US format: 1,234.56
        elseif (preg_match('/^\d{1,3}(,\d{3})*\.\d{1,4}$/', $value)) {
            $value = str_replace(',', '', $value);
        }
        // Simple decimal: 12.34 or 12,34
        elseif (preg_match('/^\d+[,\.]\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        }
        // Integer only
        elseif (preg_match('/^\d+$/', $value)) {
            // Keep as is
        }
        else {
            // Last resort: remove everything except numbers, dots and commas
            $value = preg_replace('/[^0-9.,]/', '', $value);

            // If we have both comma and dot, assume European format if comma is last
            if (str_contains($value, ',') && str_contains($value, '.')) {
                $lastComma = strrpos($value, ',');
                $lastDot = strrpos($value, '.');

                if ($lastComma > $lastDot) {
                    // European: 1.234,56
                    $value = str_replace('.', '', $value);
                    $value = str_replace(',', '.', $value);
                } else {
                    // US: 1,234.56
                    $value = str_replace(',', '', $value);
                }
            } elseif (str_contains($value, ',')) {
                // Only comma - could be decimal separator or thousands
                if (preg_match('/,\d{1,2}$/', $value)) {
                    // Likely decimal separator
                    $value = str_replace(',', '.', $value);
                } else {
                    // Likely thousands separator
                    $value = str_replace(',', '', $value);
                }
            }
        }

        $result = (float) $value;

        // Convert currency if needed
        if ($detectedCurrency && $detectedCurrency !== 'EUR' && $result > 0) {
            $originalAmount = $result;
            $convertedAmount = $this->currencyService->convertToEur($result, $detectedCurrency);

            if ($convertedAmount !== null) {
                $result = $convertedAmount;

                Log::info('Price automatically converted', [
                    'original' => $originalValue,
                    'original_amount' => $originalAmount,
                    'from_currency' => $detectedCurrency,
                    'converted_amount' => $result,
                    'to_currency' => 'EUR'
                ]);
            } else {
                Log::warning('Currency conversion failed, using original value', [
                    'original' => $originalValue,
                    'amount' => $originalAmount,
                    'from_currency' => $detectedCurrency
                ]);
            }
        }

        // Log for debugging price parsing
        Log::debug('Price parsing', [
            'original' => $originalValue,
            'detected_currency' => $detectedCurrency,
            'processed' => $value,
            'result' => $result
        ]);

        if ($result === 0.0 && !empty($originalValue)) {
            Log::warning('Price parsing resulted in 0', [
                'original' => $originalValue,
                'processed' => $value,
                'result' => $result
            ]);
        }

        return $result;
    }

    /**
     * Parse stock quantity - Enhanced for DigiKey and other formats
     */
    protected function parseStock(string $value): int
    {
        if (empty($value) || trim($value) === '') {
            return 0;
        }

        $originalValue = $value;
        
        // Remove common text patterns
        $cleanPatterns = [
            '/\b(in stock|available|on hand|pcs?|pieces?|units?)\b/i',
            '/\b(immediately|ready|ship|ships)\b/i',
            '/\([^)]*\)/', // Remove parentheses content
        ];
        
        foreach ($cleanPatterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        $value = trim($value);
        
        // Handle different number formats
        // Remove thousands separators but preserve actual numbers
        if (preg_match('/^[\d,]+$/', $value)) {
            // Only digits and commas - likely thousands separator
            $value = str_replace(',', '', $value);
        } elseif (preg_match('/^[\d\s.,]+$/', $value)) {
            // Digits, spaces, dots, commas - clean more carefully
            $value = preg_replace('/[^\d]/', '', $value);
        } else {
            // Complex string - extract first number sequence
            if (preg_match('/(\d+(?:[,\.]\d+)*)/', $value, $matches)) {
                $value = str_replace([',', '.'], '', $matches[1]);
            } else {
                $value = preg_replace('/[^0-9]/', '', $value);
            }
        }
        
        $result = (int) $value;
        
        // Log for debugging stock parsing
        Log::debug('Stock parsing', [
            'original' => $originalValue,
            'processed' => $value,
            'result' => $result
        ]);
        
        return $result;
    }

    /**
     * Generate SKU
     */
    protected function generateSku(string $supplier, string $partNumber): string
    {
        $prefix = match (strtolower($supplier)) {
            'mouser' => 'MOU',
            'digikey' => 'DK',
            'farnell' => 'FAR',
            default => 'IMP',
        };

        return $prefix . '-' . Str::upper(Str::slug($partNumber));
    }

    /**
     * Add supplier link to existing links
     */
    protected function addSupplierLink(array $existingLinks, string $supplier, ?string $partNumber): array
    {
        if ($partNumber) {
            $existingLinks[strtolower($supplier)] = [
                'part_number' => $partNumber,
                'url' => $this->getSupplierUrl($supplier, $partNumber),
            ];
        }

        return $existingLinks;
    }

    /**
     * Get supplier URL for part
     */
    protected function getSupplierUrl(string $supplier, string $partNumber): string
    {
        return match (strtolower($supplier)) {
            'mouser' => "https://www.mouser.it/ProductDetail/{$partNumber}",
            'digikey' => "https://www.digikey.it/product-detail/en/{$partNumber}",
            'farnell' => "https://it.farnell.com/{$partNumber}",
            default => '',
        };
    }

    /**
     * Auto-detect CSV field mapping based on headers
     */
    protected function autoDetectFields($csv, string $supplier): array
    {
        $headers = $csv->getHeader();
        $columnMap = $this->getColumnMap($supplier);
        $mapping = [];
        
        // First try exact matches from predefined mappings
        foreach ($headers as $header) {
            if (isset($columnMap[$header])) {
                $mapping[$columnMap[$header]] = $header;
            }
        }
        
        // If exact matches found, use them
        if (!empty($mapping)) {
            Log::info('CSV auto-detection used exact matches', [
                'supplier' => $supplier,
                'headers' => $headers,
                'mapping' => $mapping
            ]);
            return $mapping;
        }
        
        // Fallback to pattern matching
        foreach ($headers as $header) {
            $normalizedHeader = strtolower(trim($header));
            
            // Supplier part number patterns
            if ((str_contains($normalizedHeader, 'mouser') && (str_contains($normalizedHeader, 'part') || str_contains($normalizedHeader, 'no'))) ||
                (str_contains($normalizedHeader, 'digikey') && (str_contains($normalizedHeader, 'part') || str_contains($normalizedHeader, 'no'))) ||
                (str_contains($normalizedHeader, 'digi-key') && (str_contains($normalizedHeader, 'part') || str_contains($normalizedHeader, 'no')))) {
                $mapping['supplier_part'] = $header;
            }
            // Manufacturer part number patterns
            elseif ((str_contains($normalizedHeader, 'mfr') || str_contains($normalizedHeader, 'manufacturer')) && 
                   (str_contains($normalizedHeader, 'part') || str_contains($normalizedHeader, 'no') || str_contains($normalizedHeader, 'mpn'))) {
                $mapping['manufacturer_part_number'] = $header;
            }
            // Manufacturer patterns
            elseif (str_contains($normalizedHeader, 'manufacturer') && !str_contains($normalizedHeader, 'part')) {
                $mapping['manufacturer'] = $header;
            }
            // Description patterns
            elseif (str_contains($normalizedHeader, 'desc') || str_contains($normalizedHeader, 'product')) {
                $mapping['description'] = $header;
            }
            // Ordered Quantity patterns (priority for inventory movements)
            elseif ((str_contains($normalizedHeader, 'qty') || str_contains($normalizedHeader, 'quantity')) &&
                   !str_contains($normalizedHeader, 'available') && !str_contains($normalizedHeader, 'stock')) {
                $mapping['ordered_quantity'] = $header;
            }
            // Stock/Available patterns (supplier availability)
            elseif (str_contains($normalizedHeader, 'stock') || str_contains($normalizedHeader, 'available') ||
                   str_contains($normalizedHeader, 'giacenza') || str_contains($normalizedHeader, 'disponibil')) {
                $mapping['stock'] = $header;
            }
            // Price patterns (avoid extended price and tariff price)
            elseif ((str_contains($normalizedHeader, 'price') || str_contains($normalizedHeader, 'prezzo')) && 
                   !str_contains($normalizedHeader, 'ext') && !str_contains($normalizedHeader, 'total') && 
                   !str_contains($normalizedHeader, 'tariff')) {
                // Only set if not already set or if this is a better match
                if (!isset($mapping['unit_price']) || !str_contains($normalizedHeader, 'tariff')) {
                    $mapping['unit_price'] = $header;
                }
            }
            // Package patterns
            elseif (str_contains($normalizedHeader, 'package') || str_contains($normalizedHeader, 'packaging')) {
                $mapping['package'] = $header;
            }
        }
        
        Log::info('CSV auto-detection results', [
            'supplier' => $supplier,
            'headers' => $headers,
            'mapping' => $mapping
        ]);
        
        return $mapping;
    }

    /**
     * Auto-detect CSV field mapping from headers array (for Excel)
     */
    protected function autoDetectFieldsFromHeaders(array $headers, string $supplier): array
    {
        $columnMap = $this->getColumnMap($supplier);
        $mapping = [];
        
        // First try exact matches from predefined mappings
        foreach ($headers as $header) {
            if (isset($columnMap[$header])) {
                $mapping[$columnMap[$header]] = $header;
            }
        }
        
        // If exact matches found, use them
        if (!empty($mapping)) {
            Log::info('Excel auto-detection used exact matches', [
                'supplier' => $supplier,
                'headers' => $headers,
                'mapping' => $mapping
            ]);
            return $mapping;
        }
        
        // Fallback to pattern matching
        foreach ($headers as $header) {
            $normalizedHeader = strtolower(trim($header));
            
            // Supplier part number patterns
            if ((str_contains($normalizedHeader, 'mouser') && (str_contains($normalizedHeader, 'part') || str_contains($normalizedHeader, 'no'))) ||
                (str_contains($normalizedHeader, 'digikey') && (str_contains($normalizedHeader, 'part') || str_contains($normalizedHeader, 'no'))) ||
                (str_contains($normalizedHeader, 'digi-key') && (str_contains($normalizedHeader, 'part') || str_contains($normalizedHeader, 'no')))) {
                $mapping['supplier_part'] = $header;
            }
            // Manufacturer part number patterns
            elseif ((str_contains($normalizedHeader, 'mfr') || str_contains($normalizedHeader, 'manufacturer')) && 
                   (str_contains($normalizedHeader, 'part') || str_contains($normalizedHeader, 'no') || str_contains($normalizedHeader, 'mpn'))) {
                $mapping['manufacturer_part_number'] = $header;
            }
            // Manufacturer patterns
            elseif (str_contains($normalizedHeader, 'manufacturer') && !str_contains($normalizedHeader, 'part')) {
                $mapping['manufacturer'] = $header;
            }
            // Description patterns
            elseif (str_contains($normalizedHeader, 'desc') || str_contains($normalizedHeader, 'product')) {
                $mapping['description'] = $header;
            }
            // Ordered Quantity patterns (priority for inventory movements)
            elseif ((str_contains($normalizedHeader, 'qty') || str_contains($normalizedHeader, 'quantity')) &&
                   !str_contains($normalizedHeader, 'available') && !str_contains($normalizedHeader, 'stock')) {
                $mapping['ordered_quantity'] = $header;
            }
            // Stock/Available patterns (supplier availability)
            elseif (str_contains($normalizedHeader, 'stock') || str_contains($normalizedHeader, 'available') ||
                   str_contains($normalizedHeader, 'giacenza') || str_contains($normalizedHeader, 'disponibil')) {
                $mapping['stock'] = $header;
            }
            // Price patterns (avoid extended price and tariff price)
            elseif ((str_contains($normalizedHeader, 'price') || str_contains($normalizedHeader, 'prezzo')) && 
                   !str_contains($normalizedHeader, 'ext') && !str_contains($normalizedHeader, 'total') && 
                   !str_contains($normalizedHeader, 'tariff')) {
                // Only set if not already set or if this is a better match
                if (!isset($mapping['unit_price']) || !str_contains($normalizedHeader, 'tariff')) {
                    $mapping['unit_price'] = $header;
                }
            }
            // Package patterns
            elseif (str_contains($normalizedHeader, 'package') || str_contains($normalizedHeader, 'packaging')) {
                $mapping['package'] = $header;
            }
        }
        
        Log::info('Excel auto-detection results', [
            'supplier' => $supplier,
            'headers' => $headers,
            'mapping' => $mapping
        ]);
        
        return $mapping;
    }

    /**
     * Map CSV record using auto-detected fields
     */
    protected function mapRecordToComponentWithAutoDetection(array $record, array $fieldMapping, string $supplier): array
    {
        $data = [];
        
        foreach ($fieldMapping as $field => $csvColumn) {
            $value = $record[$csvColumn] ?? null;
            
            if ($value !== null && trim($value) !== '') {
                switch ($field) {
                    case 'unit_price':
                        $data[$field] = $this->parsePrice($value);
                        break;
                    case 'stock':
                        $data[$field] = $this->parseStock($value);
                        break;
                    default:
                        $data[$field] = trim($value);
                }
            }
        }

        return $data;
    }

    /**
     * Intelligent category detection based on component description
     * Uses AI-powered category generation if available, otherwise falls back to keyword matching
     *
     * @param string $description Component description
     * @param string|null $manufacturer Component manufacturer
     * @return int Category ID
     */
    protected function intelligentCategoryDetection(string $description, ?string $manufacturer = null): int
    {
        // Try AI-powered category generation first
        if ($this->useAiCategories && $this->aiCategoryService) {
            try {
                $category = $this->aiCategoryService->generateCategoryFromDescription($description, $manufacturer);

                if ($category) {
                    Log::debug('AI category generated', [
                        'description' => substr($description, 0, 100),
                        'category' => $category->name,
                        'category_id' => $category->id
                    ]);
                    return $category->id;
                }
            } catch (\Exception $e) {
                Log::warning('AI category generation failed, using fallback', [
                    'error' => $e->getMessage(),
                    'description' => substr($description, 0, 100)
                ]);
            }
        }

        // Fallback to original keyword-based detection
        return $this->keywordBasedCategoryDetection($description);
    }

    /**
     * Original keyword-based category detection (fallback method)
     *
     * @param string $description Component description
     * @return int Category ID
     */
    protected function keywordBasedCategoryDetection(string $description): int
    {
        $description = strtolower($description);
        
        // Define category patterns with priorities - Enhanced detection
        $categoryPatterns = [
            // FERRITE E EMI - Priorità massima per parti specifiche
            'Ferrite e EMI' => [
                'patterns' => ['ferrite', 'bead', 'emi filter', 'common mode', 'noise filter'],
                'required' => [],
                'priority' => 15
            ],
            
            // CONDENSATORI - Alta priorità con migliore detection
            'Condensatori Ceramici' => [
                'patterns' => ['mlcc', 'ceramic capacitor', 'x7r', 'x5r', 'c0g', 'np0', 'y5v', 'cap cer', 'ceramic cap', 'cer cap'],
                'required' => [],
                'contains_any' => ['capacitor', 'cap cer', 'ceramic'],
                'priority' => 12
            ],
            'Condensatori Elettrolitici' => [
                'patterns' => ['electrolytic', 'aluminum electrolytic', 'tantalum', 'elec cap'],
                'required' => [],
                'contains_any' => ['capacitor', 'electrolytic'],
                'priority' => 12
            ],
            'Condensatori Film' => [
                'patterns' => ['film capacitor', 'polyester', 'polypropylene', 'mylar'],
                'required' => [],
                'contains_any' => ['capacitor', 'film'],
                'priority' => 12
            ],
            
            // RESISTORI
            'Resistori SMD' => [
                'patterns' => ['smd resistor', 'chip resistor', '0603', '0805', '1206'],
                'required' => ['resistor'],
                'priority' => 10
            ],
            'Resistori THT' => [
                'patterns' => ['through hole resistor', 'carbon film', 'metal film', '1/4w', '1/2w'],
                'required' => ['resistor'],
                'priority' => 10
            ],
            
            // INDUTTORI
            'Induttori di Potenza' => [
                'patterns' => ['power inductor', 'smd inductor', 'shielded inductor'],
                'required' => ['inductor'],
                'priority' => 10
            ],
            
            // SEMICONDUTTORI
            'Diodi' => [
                'patterns' => ['diode', 'rectifier', 'schottky', 'zener', 'tvs'],
                'required' => [],
                'priority' => 9
            ],
            'Transistor Bipolari' => [
                'patterns' => ['bjt', 'npn', 'pnp', 'transistor'],
                'required' => [],
                'priority' => 9
            ],
            'MOSFETs' => [
                'patterns' => ['mosfet', 'n-channel', 'p-channel', 'bvdss', 'rdson'],
                'required' => [],
                'priority' => 9
            ],
            
            // CIRCUITI INTEGRATI
            'Microcontrollori' => [
                'patterns' => ['mcu', 'microcontroller', 'arm cortex', 'stm32', 'esp32', 'atmega'],
                'required' => [],
                'priority' => 9
            ],
            'Memorie' => [
                'patterns' => ['memory', 'eeprom', 'flash', 'sram', 'dram', 'sdram'],
                'required' => [],
                'priority' => 9
            ],
            'Amplificatori Operazionali' => [
                'patterns' => ['op amp', 'operational amplifier', 'comparator'],
                'required' => [],
                'priority' => 9
            ],
            'Regolatori di Tensione' => [
                'patterns' => ['voltage regulator', 'ldo', 'buck', 'boost', 'dc-dc'],
                'required' => [],
                'priority' => 9
            ],
            'Convertitori ADC/DAC' => [
                'patterns' => ['adc', 'dac', 'analog to digital', 'digital to analog'],
                'required' => [],
                'priority' => 9
            ],
            'Driver e Interfacce' => [
                'patterns' => ['driver', 'transceiver', 'rs485', 'rs232', 'can', 'i2c', 'spi'],
                'required' => [],
                'priority' => 8
            ],
            
            // CONNETTORI
            'Connettori USB' => [
                'patterns' => ['usb', 'type-c', 'micro-usb', 'mini-usb'],
                'required' => ['connector'],
                'priority' => 9
            ],
            'Connettori RF' => [
                'patterns' => ['rf connector', 'sma', 'u.fl', 'mcx', 'bnc', 'n-type'],
                'required' => [],
                'priority' => 9
            ],
            'Connettori Board-to-Board' => [
                'patterns' => ['board to board', 'mezzanine', 'header', 'pin header'],
                'required' => [],
                'priority' => 8
            ],
            'Connettori FFC/FPC' => [
                'patterns' => ['ffc', 'fpc', 'flat flex', 'ribbon'],
                'required' => [],
                'priority' => 8
            ],
            'Connettori di Alimentazione' => [
                'patterns' => ['power connector', 'barrel jack', 'terminal block'],
                'required' => [],
                'priority' => 8
            ],
            'Connettori Memory Card' => [
                'patterns' => ['sd card', 'micro sd', 'memory card'],
                'required' => [],
                'priority' => 8
            ],
            
            // SENSORI
            'Sensori IMU' => [
                'patterns' => ['imu', 'inertial', 'accelerometer', 'gyroscope', '6dof', '9dof'],
                'required' => [],
                'priority' => 9
            ],
            'Sensori di Temperatura' => [
                'patterns' => ['temperature sensor', 'thermistor', 'rtd', 'thermocouple'],
                'required' => [],
                'priority' => 9
            ],
            'Sensori di Pressione' => [
                'patterns' => ['pressure sensor', 'barometer', 'altimeter'],
                'required' => [],
                'priority' => 9
            ],
            
            // ANTENNE - Priorità massima con migliore detection
            'Antenne GPS/GNSS' => [
                'patterns' => ['gps', 'gnss', 'galileo', 'glonass', '1.575', 'l1', 'l5', 'gps ant', 'gnss ant'],
                'required' => [],
                'contains_any' => ['antenna', 'ant'],
                'priority' => 15
            ],
            'Antenne LTE/Cellular' => [
                'patterns' => ['lte', '4g', '5g', 'cellular', 'gsm', 'mobile'],
                'required' => [],
                'contains_any' => ['antenna', 'ant'],
                'priority' => 15
            ],
            'Antenne WiFi/Bluetooth' => [
                'patterns' => ['wifi', 'bluetooth', 'ble', '2.4ghz', '5ghz', 'wlan'],
                'required' => [],
                'contains_any' => ['antenna', 'ant'],
                'priority' => 15
            ],
            
            // CRISTALLI E OSCILLATORI
            'Cristalli' => [
                'patterns' => ['crystal', 'xtal', 'mhz crystal', 'khz crystal'],
                'required' => [],
                'priority' => 9
            ],
            'Oscillatori' => [
                'patterns' => ['oscillator', 'tcxo', 'vcxo', 'clock generator'],
                'required' => [],
                'priority' => 9
            ],
            
            // MODULI
            'Moduli Camera' => [
                'patterns' => ['camera module', 'image sensor', 'cmos sensor'],
                'required' => [],
                'priority' => 9
            ],
            'Moduli Display' => [
                'patterns' => ['display', 'lcd', 'oled', 'tft', 'e-ink'],
                'required' => [],
                'priority' => 9
            ],
            'System-on-Module' => [
                'patterns' => ['som', 'system on module', 'compute module'],
                'required' => [],
                'priority' => 9
            ],
        ];
        
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($categoryPatterns as $categoryName => $rules) {
            $score = 0;
            $requiredFound = true;
            $containsAnyFound = false;
            
            // Check required patterns (must all be present)
            if (isset($rules['required']) && !empty($rules['required'])) {
                foreach ($rules['required'] as $required) {
                    if (!str_contains($description, $required)) {
                        $requiredFound = false;
                        break;
                    }
                }
            }
            
            if (!$requiredFound) {
                continue;
            }
            
            // Check contains_any patterns (at least one must be present)
            if (isset($rules['contains_any']) && !empty($rules['contains_any'])) {
                foreach ($rules['contains_any'] as $containsPattern) {
                    if (str_contains($description, $containsPattern)) {
                        $containsAnyFound = true;
                        break;
                    }
                }
                
                if (!$containsAnyFound) {
                    continue;
                }
            }
            
            // Count matching patterns for scoring
            foreach ($rules['patterns'] as $pattern) {
                if (str_contains($description, $pattern)) {
                    $score += $rules['priority'];
                }
            }
            
            // If contains_any was required and found, add bonus points
            if (isset($rules['contains_any']) && $containsAnyFound) {
                $score += $rules['priority'] * 2; // Bonus for matching contains_any
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $categoryName;
            }
        }
        
        // Create or find category
        $categoryName = $bestMatch ?? 'Componenti Generici';
        $category = Category::firstOrCreate(['name' => $categoryName]);
        
        return $category->id;
    }

    /**
     * Extract technical specifications from description
     */
    protected function extractTechnicalSpecifications(string $description): array
    {
        $specs = [];
        $description = strtolower($description);
        
        // Extract capacitance values (uF, nF, pF)
        if (preg_match('/(\d+\.?\d*)\s*(uf|nf|pf)/i', $description, $matches)) {
            $specs['value'] = $matches[1] . strtoupper($matches[2]);
        }
        
        // Extract resistance values (R, K, M)
        if (preg_match('/(\d+\.?\d*)\s*(r|k|m)?\s*ohm/i', $description, $matches)) {
            $value = $matches[1];
            $multiplier = strtoupper($matches[2] ?? '');
            $specs['value'] = $value . ($multiplier ?: 'R');
        }
        
        // Extract inductance values (uH, mH, nH)
        if (preg_match('/(\d+\.?\d*)\s*(uh|mh|nh)/i', $description, $matches)) {
            $specs['value'] = $matches[1] . strtoupper($matches[2]);
        }
        
        // Extract voltage ratings
        if (preg_match('/(\d+\.?\d*)\s*v(dc)?/i', $description, $matches)) {
            $specs['voltage_rating'] = $matches[1] . 'V';
        }
        
        // Extract current ratings
        if (preg_match('/(\d+\.?\d*)\s*(ma|a)/i', $description, $matches)) {
            $specs['current_rating'] = $matches[1] . strtoupper($matches[2]);
        }
        
        // Extract power ratings
        if (preg_match('/(\d+\/\d+|\d+\.?\d*)\s*w/i', $description, $matches)) {
            $specs['power_rating'] = $matches[1] . 'W';
        }
        
        // Extract tolerance (simplified to avoid regex issues)
        if (preg_match('/(\d+)\s*%/', $description, $matches)) {
            $specs['tolerance'] = '±' . $matches[1] . '%';
        }
        
        // Extract package types
        if (preg_match('/(0201|0402|0603|0805|1206|1210|2010|2512)/i', $description, $matches)) {
            $specs['package_type'] = strtoupper($matches[1]);
            $specs['mounting_type'] = 'SMD';
        }
        
        // Extract case styles
        if (preg_match('/(sot-?23|sot-?223|to-?220|soic-?\d+|tqfp-?\d+|qfn-?\d+)/i', $description, $matches)) {
            $specs['case_style'] = strtoupper($matches[1]);
        }
        
        // Extract dielectric for capacitors
        if (preg_match('/(x7r|x5r|c0g|np0|y5v)/i', $description, $matches)) {
            $specs['dielectric'] = strtoupper($matches[1]);
        }
        
        // Extract temperature range
        if (preg_match('/(-?\d+)\s*[°ºC]?\s*~\s*[\+\-]?(\d+)\s*[°ºC]?/i', $description, $matches)) {
            $specs['operating_temperature'] = $matches[1] . '°C ~ +' . $matches[2] . '°C';
        }
        
        // Detect mounting type
        if (str_contains($description, 'smd') || str_contains($description, 'smt')) {
            $specs['mounting_type'] = 'SMD';
        } elseif (str_contains($description, 'through hole') || str_contains($description, 'tht')) {
            $specs['mounting_type'] = 'Through Hole';
        }
        
        return $specs;
    }

    /**
     * Configure CSV reader settings based on supplier and content
     */
    protected function configureCSV($csv, string $supplier): void
    {
        // Try to auto-detect delimiter
        $sample = file_get_contents($csv->getPathname(), false, null, 0, 2048);
        
        $delimiters = [';', ',', '\t', '|'];
        $maxCount = 0;
        $bestDelimiter = ';'; // Default for Mouser
        
        foreach ($delimiters as $delimiter) {
            $count = substr_count($sample, $delimiter);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delimiter;
            }
        }
        
        $csv->setDelimiter($bestDelimiter);
        $csv->setHeaderOffset(0);
        
        // Set encoding (try to detect)
        if (mb_detect_encoding($sample, 'UTF-8', true) === false) {
            $csv->addStreamFilter('convert.iconv.ISO-8859-1/UTF-8');
        }
        
        Log::info('CSV configuration detected', [
            'supplier' => $supplier,
            'delimiter' => $bestDelimiter,
            'encoding_sample' => mb_substr($sample, 0, 100)
        ]);
    }

    /**
     * Enhanced error reporting with more details
     */
    protected function logImportError(int $rowNumber, array $record, \Exception $e, string $supplier): void
    {
        Log::error('CSV import row failed', [
            'supplier' => $supplier,
            'row' => $rowNumber,
            'error' => $e->getMessage(),
            'record_sample' => array_slice($record, 0, 5, true), // First 5 fields for debugging
            'record_count' => count($record)
        ]);
    }

    /**
     * Validate component data before import
     */
    protected function validateComponentData(array $data): array
    {
        $errors = [];
        
        if (empty($data['manufacturer_part_number'])) {
            $errors[] = 'Missing manufacturer part number';
        }
        
        if (empty($data['description']) && empty($data['name'])) {
            $errors[] = 'Missing description or name';
        }
        
        if (isset($data['unit_price']) && !is_numeric($data['unit_price'])) {
            $errors[] = 'Invalid unit price format';
        }
        
        if (isset($data['stock']) && !is_numeric($data['stock'])) {
            $errors[] = 'Invalid stock quantity format';
        }
        
        return $errors;
    }

    /**
     * Enable or disable AI-powered category generation
     *
     * @param bool $enabled
     * @return self
     */
    public function setUseAiCategories(bool $enabled): self
    {
        $this->useAiCategories = $enabled;
        return $this;
    }

    /**
     * Check if AI category generation is enabled and available
     *
     * @return bool
     */
    public function isAiCategoriesAvailable(): bool
    {
        return $this->useAiCategories && $this->aiCategoryService !== null;
    }

    /**
     * Enable or disable automatic currency conversion
     *
     * @param bool $enabled
     * @return self
     */
    public function setAutoConvertCurrency(bool $enabled): self
    {
        $this->autoConvertCurrency = $enabled;
        return $this;
    }

    /**
     * Check if automatic currency conversion is enabled and available
     *
     * @return bool
     */
    public function isCurrencyConversionAvailable(): bool
    {
        return $this->autoConvertCurrency && $this->currencyService !== null;
    }

    /**
     * Enable or disable datasheet scraping for specification extraction
     *
     * @param bool $enabled
     * @return self
     */
    public function setUseDatasheetScraper(bool $enabled): self
    {
        $this->useDatasheetScraper = $enabled;
        return $this;
    }

    /**
     * Check if datasheet scraping is enabled and available
     *
     * @return bool
     */
    public function isDatasheetScraperAvailable(): bool
    {
        return $this->useDatasheetScraper && $this->datasheetScraperService !== null;
    }

    /**
     * Set progress callback for real-time updates
     *
     * @param callable $callback
     * @return self
     */
    public function setProgressCallback(callable $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Set user ID for inventory movements (used in queued job context)
     *
     * @param int|null $userId
     * @return self
     */
    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Set import ID for tracking this import session
     *
     * @param int|null $importId
     * @return self
     */
    public function setImportId(?int $importId): self
    {
        $this->importId = $importId;
        return $this;
    }

    /**
     * Call progress callback if set
     *
     * @param int $current
     * @param int $total
     * @param string $message
     * @return void
     */
    protected function reportProgress(int $current, int $total, string $message = ''): void
    {
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, $current, $total, $message);
        }
    }

    /**
     * Create inventory movement for imported component
     */
    protected function createInventoryMovement(Component $component, array $componentData, array $invoiceData): void
    {
        try {
            // Use ordered_quantity if present, otherwise fallback to stock
            $quantity = (int) ($componentData['ordered_quantity'] ?? $componentData['stock'] ?? 0);
            if ($quantity <= 0) {
                return;
            }

            // Refresh component to get current stock from database (may be NULL for new components)
            $component->refresh();
            $currentStock = $component->stock_quantity ?? 0;
            $afterStock = $currentStock + $quantity;

            InventoryMovement::create([
                'component_id' => $component->id,
                'import_id' => $this->importId,
                'type' => 'in',
                'quantity' => $quantity,
                'quantity_before' => $currentStock,
                'quantity_after' => $afterStock,
                'unit_cost' => $componentData['unit_price'] ?? 0,
                'reference_type' => 'import',
                'reference_id' => null,
                'reason' => 'Import from ' . ucfirst($invoiceData['supplier']),
                'notes' => $invoiceData['notes'] ?? "Imported from {$invoiceData['supplier']} file",
                'user_id' => $this->userId ?? Auth::id(),
                'invoice_number' => $invoiceData['invoice_number'],
                'invoice_path' => $invoiceData['invoice_path'],
                'invoice_date' => $invoiceData['invoice_date'],
                'invoice_total' => $invoiceData['invoice_total'],
                'supplier' => $invoiceData['supplier'],
                'destination_project_id' => $invoiceData['project_id'] ?? null,
            ]);

            // Update component stock quantity
            $component->update(['stock_quantity' => $afterStock]);

            Log::info('Inventory movement created for import', [
                'component_id' => $component->id,
                'component_sku' => $component->sku,
                'quantity' => $quantity,
                'stock_after' => $afterStock,
                'invoice_number' => $invoiceData['invoice_number']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create inventory movement for import', [
                'error' => $e->getMessage(),
                'component_id' => $component->id,
                'invoice_data' => $invoiceData
            ]);
        }
    }
}