<?php

namespace App\Services;

use App\Models\Component;
use App\Services\Suppliers\MouserApiService;
use App\Services\Suppliers\DigiKeyApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * DatasheetScraperService
 *
 * Comprehensive service for extracting technical specifications from components.
 *
 * Strategy cascade (most reliable to fallback):
 * 1. Supplier APIs (Mouser/DigiKey) - Fast, structured data
 * 2. PDF Datasheet parsing with AI - Slower, good quality
 * 3. Description text extraction - Fast fallback
 *
 * Usage:
 * $scraperService = app(DatasheetScraperService::class);
 * $specs = $scraperService->extractSpecifications($component);
 * if (!empty($specs)) {
 *     $component->update($specs);
 * }
 */
class DatasheetScraperService
{
    /**
     * Ollama Service instance for AI extraction
     */
    protected ?OllamaService $ollamaService = null;

    /**
     * Mouser API Service
     */
    protected ?MouserApiService $mouserService = null;

    /**
     * DigiKey API Service
     */
    protected ?DigiKeyApiService $digikeyService = null;

    /**
     * HTTP request timeout in seconds
     */
    protected int $timeout = 30;

    /**
     * PDF download timeout in seconds
     */
    protected int $pdfDownloadTimeout = 60;

    /**
     * AI extraction timeout in seconds
     */
    protected int $aiTimeout = 120;

    /**
     * Enable/disable datasheet scraping
     */
    protected bool $enabled = true;

    /**
     * Cache duration for API responses (24 hours)
     */
    protected int $apiCacheDuration = 86400;

    /**
     * Cache duration for PDF downloads (7 days)
     */
    protected int $pdfCacheDuration = 604800;

    /**
     * Minimum confidence threshold for completeness check
     */
    protected int $minCompletenessThreshold = 60;

    /**
     * Constructor
     */
    public function __construct()
    {
        try {
            $this->ollamaService = app(OllamaService::class);
        } catch (\Exception $e) {
            Log::warning('OllamaService not available for datasheet extraction', [
                'error' => $e->getMessage()
            ]);
            $this->ollamaService = null;
        }

        // Mouser API Service - check if configured before using
        try {
            $mouserSupplier = \App\Models\Supplier::where('api_name', 'mouser')->first();
            if ($mouserSupplier && !empty($mouserSupplier->api_credentials)) {
                $this->mouserService = app(MouserApiService::class);
            } else {
                Log::debug('MouserApiService not configured');
                $this->mouserService = null;
            }
        } catch (\Exception $e) {
            Log::debug('MouserApiService not available', ['error' => $e->getMessage()]);
            $this->mouserService = null;
        }

        // DigiKey API Service - check if configured before using
        try {
            $digikeySupplier = \App\Models\Supplier::where('api_name', 'digikey')->first();
            if ($digikeySupplier && !empty($digikeySupplier->api_credentials)) {
                $this->digikeyService = app(DigiKeyApiService::class);
            } else {
                Log::debug('DigiKeyApiService not configured');
                $this->digikeyService = null;
            }
        } catch (\Exception $e) {
            Log::debug('DigiKeyApiService not available', ['error' => $e->getMessage()]);
            $this->digikeyService = null;
        }
    }

    /**
     * Extract specifications from component using all available methods
     *
     * @param Component $component
     * @return array Extracted specifications
     */
    public function extractSpecifications(Component $component): array
    {
        if (!$this->enabled) {
            Log::debug('Datasheet scraper is disabled');
            return [];
        }

        Log::info('Starting specification extraction', [
            'component_id' => $component->id,
            'sku' => $component->sku,
            'mpn' => $component->manufacturer_part_number,
        ]);

        $specs = [];

        // Priority 1: Try Supplier APIs (most reliable)
        $specs = $this->extractFromSupplierApi($component);

        if ($this->isComplete($specs)) {
            Log::info('Specifications complete from API', [
                'component_id' => $component->id,
                'fields_found' => array_keys(array_filter($specs))
            ]);
            return $specs;
        }

        // Priority 2: Try PDF Datasheet with AI (if URL available)
        if (!empty($component->datasheet_url) || $this->findDatasheetUrl($component)) {
            $pdfSpecs = $this->extractFromDatasheetPdf($component);
            $specs = $this->mergeSpecifications($specs, $pdfSpecs);

            if ($this->isComplete($specs)) {
                Log::info('Specifications complete from PDF', [
                    'component_id' => $component->id,
                    'fields_found' => array_keys(array_filter($specs))
                ]);
                return $specs;
            }
        }

        // Priority 3: Try AI extraction from description (if Ollama available)
        if (!empty($component->description) && $this->ollamaService) {
            $aiSpecs = $this->extractFromDescriptionWithAI($component);
            $specs = $this->mergeSpecifications($specs, $aiSpecs);
        }

        // Priority 4: Fallback to regex description parsing
        if (!empty($component->description)) {
            $descSpecs = $this->extractFromDescription($component->description);
            $specs = $this->mergeSpecifications($specs, $descSpecs);
        }

        // Post-processing: Auto-deduce mounting_type from package_type if missing
        if (!empty($specs['package_type']) && empty($specs['mounting_type'])) {
            $specs['mounting_type'] = $this->detectMountingType($specs['package_type']);
            Log::debug('Auto-deduced mounting_type from package_type', [
                'package_type' => $specs['package_type'],
                'mounting_type' => $specs['mounting_type']
            ]);
        }

        Log::info('Specification extraction completed', [
            'component_id' => $component->id,
            'completeness' => $this->calculateCompleteness($specs),
            'fields_found' => array_keys(array_filter($specs))
        ]);

        return $specs;
    }

    /**
     * Extract specifications from Supplier APIs (Mouser/DigiKey)
     *
     * @param Component $component
     * @return array
     */
    protected function extractFromSupplierApi(Component $component): array
    {
        $specs = [];
        $supplierLinks = $component->supplier_links ?? [];

        // If supplier_links is empty, try to reconstruct from SKU prefix
        if (empty($supplierLinks) && $component->manufacturer_part_number) {
            if (str_starts_with($component->sku, 'MOU-')) {
                $supplierLinks['mouser'] = $component->manufacturer_part_number;
                Log::debug('Reconstructed Mouser link from SKU', [
                    'sku' => $component->sku,
                    'mpn' => $component->manufacturer_part_number
                ]);
            } elseif (str_starts_with($component->sku, 'DK-')) {
                $supplierLinks['digikey'] = $component->manufacturer_part_number;
                Log::debug('Reconstructed DigiKey link from SKU', [
                    'sku' => $component->sku,
                    'mpn' => $component->manufacturer_part_number
                ]);
            }
        }

        // Try Mouser API first
        if (isset($supplierLinks['mouser']) && $this->mouserService) {
            $mouserPart = is_array($supplierLinks['mouser'])
                ? ($supplierLinks['mouser']['part_number'] ?? null)
                : $supplierLinks['mouser'];

            if ($mouserPart) {
                $specs = $this->extractFromMouserApi($mouserPart, $component);
                if (!empty($specs)) {
                    Log::info('Specifications extracted from Mouser API', [
                        'component_id' => $component->id,
                        'mouser_part' => $mouserPart,
                        'fields' => array_keys(array_filter($specs))
                    ]);
                    return $specs;
                }
            }
        }

        // Try DigiKey API second
        if (isset($supplierLinks['digikey']) && $this->digikeyService) {
            $digikeyPart = is_array($supplierLinks['digikey'])
                ? ($supplierLinks['digikey']['part_number'] ?? null)
                : $supplierLinks['digikey'];

            if ($digikeyPart) {
                $specs = $this->extractFromDigiKeyApi($digikeyPart, $component);
                if (!empty($specs)) {
                    Log::info('Specifications extracted from DigiKey API', [
                        'component_id' => $component->id,
                        'digikey_part' => $digikeyPart,
                        'fields' => array_keys(array_filter($specs))
                    ]);
                    return $specs;
                }
            }
        }

        return $specs;
    }

    /**
     * Extract specifications from Mouser API
     *
     * @param string $mouserPartNumber
     * @param Component $component
     * @return array
     */
    protected function extractFromMouserApi(string $mouserPartNumber, Component $component): array
    {
        try {
            $cacheKey = "mouser_specs_{$mouserPartNumber}";

            return Cache::remember($cacheKey, $this->apiCacheDuration, function () use ($mouserPartNumber, $component) {
                Log::debug('Fetching specs from Mouser API', ['part' => $mouserPartNumber]);

                $partData = $this->mouserService->getPartDetails($mouserPartNumber);

                if (!$partData) {
                    return [];
                }

                // Map Mouser attributes to our schema
                return $this->mapMouserAttributesToSpecs($partData, $component);
            });
        } catch (\Exception $e) {
            Log::error('Error extracting specs from Mouser API', [
                'error' => $e->getMessage(),
                'mouser_part' => $mouserPartNumber
            ]);
            return [];
        }
    }

    /**
     * Extract specifications from DigiKey API
     *
     * @param string $digikeyPartNumber
     * @param Component $component
     * @return array
     */
    protected function extractFromDigiKeyApi(string $digikeyPartNumber, Component $component): array
    {
        try {
            $cacheKey = "digikey_specs_{$digikeyPartNumber}";

            return Cache::remember($cacheKey, $this->apiCacheDuration, function () use ($digikeyPartNumber, $component) {
                Log::debug('Fetching specs from DigiKey API', ['part' => $digikeyPartNumber]);

                $partData = $this->digikeyService->getPartDetails($digikeyPartNumber);

                if (!$partData) {
                    return [];
                }

                // Map DigiKey parameters to our schema
                return $this->mapDigiKeyParametersToSpecs($partData, $component);
            });
        } catch (\Exception $e) {
            Log::error('Error extracting specs from DigiKey API', [
                'error' => $e->getMessage(),
                'digikey_part' => $digikeyPartNumber
            ]);
            return [];
        }
    }

    /**
     * Map Mouser product attributes to our specification schema
     *
     * @param array $partData
     * @param Component $component
     * @return array
     */
    protected function mapMouserAttributesToSpecs(array $partData, Component $component): array
    {
        $specs = [];
        $attributes = $partData['attributes'] ?? [];

        // Common attribute mappings
        $mappings = [
            'Capacitance' => 'value',
            'Resistance' => 'value',
            'Inductance' => 'value',
            'Tolerance' => 'tolerance',
            'Voltage Rating' => 'voltage_rating',
            'Voltage Rating - DC' => 'voltage_rating',
            'DC Voltage Rating' => 'voltage_rating',
            'Current Rating' => 'current_rating',
            'Current - Max' => 'current_rating',
            'Power Rating' => 'power_rating',
            'Power' => 'power_rating',
            'Package / Case' => 'package_type',
            'Case/Package' => 'package_type',
            'Mounting Style' => 'mounting_type',
            'Mounting Type' => 'mounting_type',
            'Case Style' => 'case_style',
            'Dielectric' => 'dielectric',
            'Dielectric Material' => 'dielectric',
            'Temperature Coefficient' => 'temperature_coefficient',
            'Operating Temperature' => 'operating_temperature',
            'Operating Temperature Range' => 'operating_temperature',
        ];

        foreach ($mappings as $mouserKey => $ourField) {
            if (isset($attributes[$mouserKey])) {
                $value = $this->normalizeValue($attributes[$mouserKey], $ourField);
                if ($value !== null) {
                    $specs[$ourField] = $value;
                }
            }
        }

        // Detect mounting type from package if not explicitly set
        if (!isset($specs['mounting_type']) && isset($specs['package_type'])) {
            $specs['mounting_type'] = $this->detectMountingType($specs['package_type']);
        }

        return $specs;
    }

    /**
     * Map DigiKey parameters to our specification schema
     *
     * @param array $partData
     * @param Component $component
     * @return array
     */
    protected function mapDigiKeyParametersToSpecs(array $partData, Component $component): array
    {
        $specs = [];
        $parameters = $partData['attributes'] ?? [];

        // Common parameter mappings
        $mappings = [
            'Capacitance' => 'value',
            'Resistance' => 'value',
            'Resistance (Ohms)' => 'value',
            'Inductance' => 'value',
            'Tolerance' => 'tolerance',
            'Voltage - Rated' => 'voltage_rating',
            'Voltage Rating - DC' => 'voltage_rating',
            'Current Rating' => 'current_rating',
            'Current - Max' => 'current_rating',
            'Power (Watts)' => 'power_rating',
            'Power Rating' => 'power_rating',
            'Package / Case' => 'package_type',
            'Supplier Device Package' => 'package_type',
            'Mounting Type' => 'mounting_type',
            'Temperature Coefficient' => 'temperature_coefficient',
            'Operating Temperature' => 'operating_temperature',
        ];

        foreach ($mappings as $digikeyKey => $ourField) {
            if (isset($parameters[$digikeyKey])) {
                $value = $this->normalizeValue($parameters[$digikeyKey], $ourField);
                if ($value !== null) {
                    $specs[$ourField] = $value;
                }
            }
        }

        // Handle packaging from partData root
        if (!isset($specs['mounting_type']) && isset($partData['packaging'])) {
            if (stripos($partData['packaging'], 'tape') !== false || stripos($partData['packaging'], 'reel') !== false) {
                $specs['mounting_type'] = 'SMD';
            } elseif (stripos($partData['packaging'], 'bulk') !== false || stripos($partData['packaging'], 'tube') !== false) {
                $specs['mounting_type'] = 'Through Hole';
            }
        }

        // Detect mounting type from package if not explicitly set
        if (!isset($specs['mounting_type']) && isset($specs['package_type'])) {
            $specs['mounting_type'] = $this->detectMountingType($specs['package_type']);
        }

        return $specs;
    }

    /**
     * Extract specifications from PDF datasheet using AI
     *
     * @param Component $component
     * @return array
     */
    protected function extractFromDatasheetPdf(Component $component): array
    {
        if (!$this->ollamaService || !$this->ollamaService->isAvailable()) {
            Log::debug('AI service not available for PDF extraction');
            return [];
        }

        $datasheetUrl = $component->datasheet_url;
        if (empty($datasheetUrl)) {
            $datasheetUrl = $this->findDatasheetUrl($component);
            if (!$datasheetUrl) {
                return [];
            }
            // Update component with found URL
            $component->update(['datasheet_url' => $datasheetUrl]);
        }

        try {
            Log::info('Extracting specifications from PDF datasheet', [
                'component_id' => $component->id,
                'url' => $datasheetUrl
            ]);

            // Download and parse PDF
            $pdfText = $this->downloadAndParsePdf($datasheetUrl);

            if (empty($pdfText)) {
                Log::warning('Failed to extract text from PDF', ['url' => $datasheetUrl]);
                return [];
            }

            // Extract specs using AI
            return $this->extractSpecsWithAI($pdfText, $component);

        } catch (\Exception $e) {
            Log::error('Error extracting specs from PDF', [
                'error' => $e->getMessage(),
                'component_id' => $component->id,
                'url' => $datasheetUrl
            ]);
            return [];
        }
    }

    /**
     * Download and parse PDF to extract text
     *
     * @param string $url
     * @return string|null
     */
    protected function downloadAndParsePdf(string $url): ?string
    {
        try {
            $cacheKey = 'pdf_text_' . md5($url);

            return Cache::remember($cacheKey, $this->pdfCacheDuration, function () use ($url) {
                Log::debug('Downloading PDF', ['url' => $url]);

                // Download PDF to temp file
                $tempPath = storage_path('app/temp/datasheet_' . md5($url) . '.pdf');
                $tempDir = dirname($tempPath);

                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $response = Http::timeout($this->pdfDownloadTimeout)->get($url);

                if (!$response->successful()) {
                    Log::warning('Failed to download PDF', [
                        'url' => $url,
                        'status' => $response->status()
                    ]);
                    return null;
                }

                file_put_contents($tempPath, $response->body());

                // Parse PDF
                $parser = new PdfParser();
                $pdf = $parser->parseFile($tempPath);

                // Extract text from first 5 pages (specifications usually at the beginning)
                $text = '';
                $pages = $pdf->getPages();
                $maxPages = min(5, count($pages));

                for ($i = 0; $i < $maxPages; $i++) {
                    $text .= $pages[$i]->getText() . "\n\n";
                }

                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

                // Limit text to first 8000 characters for AI processing
                $excerpt = substr($text, 0, 8000);

                Log::debug('PDF text extracted', [
                    'url' => $url,
                    'text_length' => strlen($excerpt),
                    'pages_processed' => $maxPages
                ]);

                return $excerpt;
            });

        } catch (\Exception $e) {
            Log::error('Error parsing PDF', [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
            return null;
        }
    }

    /**
     * Extract specifications from datasheet text using AI
     *
     * @param string $datasheetText
     * @param Component $component
     * @return array
     */
    protected function extractSpecsWithAI(string $datasheetText, Component $component): array
    {
        try {
            $mpn = $component->manufacturer_part_number;
            $manufacturer = $component->manufacturer;

            $prompt = <<<PROMPT
You are a technical specification extractor for electronic components.

Component: {$manufacturer} {$mpn}

Extract ONLY the following specifications from this datasheet text. Return ONLY a JSON object with these keys, use null if not found:

{
  "value": "component value (e.g., 10uF, 100R, 1nH, 3.3V)",
  "tolerance": "tolerance (e.g., ±5%, ±1%, ±10%)",
  "voltage_rating": "voltage rating (e.g., 16V, 50V, 3.3V)",
  "current_rating": "current rating (e.g., 2A, 500mA)",
  "power_rating": "power rating (e.g., 0.25W, 1/4W, 100mW)",
  "package_type": "package (e.g., 0805, 0603, SOT-23, SOIC-8)",
  "mounting_type": "SMD or Through Hole",
  "case_style": "case style if specified",
  "dielectric": "dielectric type for capacitors ONLY (X7R, X5R, C0G, Y5V, NP0)",
  "operating_temperature": "temperature range (e.g., -40°C ~ +85°C)"
}

RULES:
- Return ONLY valid JSON, no explanation
- Use null for missing values, do NOT guess
- Normalize units: use V not Volts, A not Amperes, W not Watts
- For mounting: only "SMD" or "Through Hole"
- Temperature format: "-40°C ~ +85°C"
- For capacitors, include dielectric if mentioned (X7R, X5R, C0G, NP0, Y5V)
- For resistors, value should be like "100R", "1K", "10K"
- For capacitors, value should be like "10uF", "100nF", "1pF"

Datasheet excerpt:
{$datasheetText}
PROMPT;

            Log::debug('Requesting AI extraction', [
                'component_id' => $component->id,
                'text_length' => strlen($datasheetText)
            ]);

            $response = Http::timeout($this->aiTimeout)->post(
                $this->ollamaService ? 'http://localhost:11434/api/generate' : config('services.ollama.url', 'http://localhost:11434') . '/api/generate',
                [
                    'model' => config('services.ollama.model', 'llama3.1:8b'),
                    'prompt' => $prompt,
                    'stream' => false,
                ]
            );

            if (!$response->successful()) {
                Log::warning('AI extraction request failed', [
                    'status' => $response->status()
                ]);
                return [];
            }

            $aiResponse = $response->json()['response'] ?? null;

            if (!$aiResponse) {
                return [];
            }

            // Parse JSON from AI response
            $specs = $this->parseAiJsonResponse($aiResponse);

            if ($specs) {
                Log::info('AI extraction successful', [
                    'component_id' => $component->id,
                    'fields_extracted' => array_keys(array_filter($specs))
                ]);
                return $specs;
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Error during AI extraction', [
                'error' => $e->getMessage(),
                'component_id' => $component->id
            ]);
            return [];
        }
    }

    /**
     * Extract specifications from component description using AI
     *
     * @param Component $component
     * @return array
     */
    protected function extractFromDescriptionWithAI(Component $component): array
    {
        try {
            $mpn = $component->manufacturer_part_number;
            $manufacturer = $component->manufacturer;
            $description = $component->description;

            $prompt = <<<PROMPT
You are a technical specification extractor for electronic components.

Component: {$manufacturer} {$mpn}
Description: {$description}

Extract ONLY the following specifications from the component description. Return ONLY a JSON object with these keys, use null if not found:

{
  "value": "component value (e.g., 10uF, 100R, 1nH, 3.3V, 850nm for IR)",
  "tolerance": "tolerance (e.g., ±5%, ±1%, ±10%)",
  "voltage_rating": "voltage rating (e.g., 16V, 50V, 3.3V)",
  "current_rating": "current rating (e.g., 2A, 500mA, 100mA)",
  "power_rating": "power rating (e.g., 0.25W, 1/4W, 100mW)",
  "package_type": "package (e.g., 0805, 0603, SOT-23, SOIC-8, 1616)",
  "mounting_type": "SMD or Through Hole",
  "case_style": "case style if specified",
  "dielectric": "dielectric type for capacitors ONLY (X7R, X5R, C0G, Y5V, NP0)",
  "operating_temperature": "temperature range (e.g., -40°C ~ +85°C)"
}

RULES:
- Return ONLY valid JSON, no explanation
- Use null for missing values, do NOT guess
- For IR LEDs/transmitters, value is wavelength (e.g., "850nm", "940nm")
- Normalize units: use V not Volts, A not Amperes, W not Watts
- For mounting: only "SMD" or "Through Hole"
- Temperature format: "-40°C ~ +85°C"
- Extract package size like "1616" from descriptions like "OSLON P1616"
- For LED/IR components, extract wavelength as value

Examples:
- "Infrared 850nm OSLON P1616" → {"value": "850nm", "package_type": "1616", "mounting_type": "SMD"}
- "CAP CER 10uF 16V X7R 0805" → {"value": "10uF", "voltage_rating": "16V", "dielectric": "X7R", "package_type": "0805"}
- "RES 100R 1% 1/4W 0603" → {"value": "100R", "tolerance": "±1%", "power_rating": "0.25W", "package_type": "0603"}
PROMPT;

            Log::debug('Requesting AI extraction from description', [
                'component_id' => $component->id,
                'description_length' => strlen($description)
            ]);

            $response = Http::timeout($this->aiTimeout)->post(
                config('services.ollama.url', 'http://localhost:11434') . '/api/generate',
                [
                    'model' => config('services.ollama.model', 'llama3.1:8b'),
                    'prompt' => $prompt,
                    'stream' => false,
                ]
            );

            if (!$response->successful()) {
                Log::warning('AI description extraction request failed', [
                    'status' => $response->status()
                ]);
                return [];
            }

            $aiResponse = $response->json()['response'] ?? null;

            if (!$aiResponse) {
                return [];
            }

            // Parse JSON from AI response
            $specs = $this->parseAiJsonResponse($aiResponse);

            if ($specs) {
                Log::info('AI description extraction successful', [
                    'component_id' => $component->id,
                    'fields_extracted' => array_keys(array_filter($specs))
                ]);
                return $specs;
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Error during AI description extraction', [
                'error' => $e->getMessage(),
                'component_id' => $component->id
            ]);
            return [];
        }
    }

    /**
     * Parse JSON response from AI (handles various response formats)
     *
     * @param string $response
     * @return array|null
     */
    protected function parseAiJsonResponse(string $response): ?array
    {
        // Try direct JSON decode
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Try to extract JSON object from text
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        Log::warning('Failed to parse AI JSON response', [
            'response_preview' => substr($response, 0, 200)
        ]);

        return null;
    }

    /**
     * Extract specifications from component description (fallback)
     *
     * @param string $description
     * @return array
     */
    protected function extractFromDescription(string $description): array
    {
        $specs = [];
        $description = strtolower($description);

        // Extract capacitance values (uF, nF, pF)
        if (preg_match('/(\d+\.?\d*)\s*(uf|µf|nf|pf)/i', $description, $matches)) {
            $specs['value'] = $matches[1] . strtoupper(str_replace('µ', 'u', $matches[2]));
        }

        // Extract resistance values (R, K, M)
        if (preg_match('/(\d+\.?\d*)\s*(r|k|m)?\s*ohm/i', $description, $matches)) {
            $value = $matches[1];
            $multiplier = strtoupper($matches[2] ?? '');
            $specs['value'] = $value . ($multiplier ?: 'R');
        }

        // Extract inductance values (uH, mH, nH)
        if (preg_match('/(\d+\.?\d*)\s*(uh|µh|mh|nh)/i', $description, $matches)) {
            $specs['value'] = $matches[1] . strtoupper(str_replace('µ', 'u', $matches[2]));
        }

        // Extract voltage ratings
        if (preg_match('/(\d+\.?\d*)\s*v(dc|ac)?/i', $description, $matches)) {
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

        // Extract tolerance
        if (preg_match('/±\s*(\d+)\s*%/', $description, $matches)) {
            $specs['tolerance'] = '±' . $matches[1] . '%';
        } elseif (preg_match('/(\d+)\s*%/', $description, $matches)) {
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

        Log::debug('Extracted specs from description', [
            'fields' => array_keys(array_filter($specs))
        ]);

        return $specs;
    }

    /**
     * Find datasheet URL for component
     *
     * @param Component $component
     * @return string|null
     */
    protected function findDatasheetUrl(Component $component): ?string
    {
        // Method 1: Check if already stored
        if (!empty($component->datasheet_url)) {
            return $component->datasheet_url;
        }

        $supplierLinks = $component->supplier_links ?? [];

        // If supplier_links is empty, try to reconstruct from SKU
        if (empty($supplierLinks) && $component->manufacturer_part_number) {
            if (str_starts_with($component->sku, 'MOU-')) {
                $supplierLinks['mouser'] = $component->manufacturer_part_number;
            } elseif (str_starts_with($component->sku, 'DK-')) {
                $supplierLinks['digikey'] = $component->manufacturer_part_number;
            }
        }

        // Method 2: Try Mouser
        if (isset($supplierLinks['mouser']) && $this->mouserService) {
            $mouserPart = is_array($supplierLinks['mouser'])
                ? ($supplierLinks['mouser']['part_number'] ?? null)
                : $supplierLinks['mouser'];

            if ($mouserPart) {
                try {
                    $partData = $this->mouserService->getPartDetails($mouserPart);
                    if (!empty($partData['datasheet_url'])) {
                        // Save datasheet URL to component
                        $component->update(['datasheet_url' => $partData['datasheet_url']]);
                        return $partData['datasheet_url'];
                    }
                } catch (\Exception $e) {
                    Log::debug('Mouser datasheet lookup failed', ['error' => $e->getMessage()]);
                }
            }
        }

        // Method 3: Try DigiKey
        if (isset($supplierLinks['digikey']) && $this->digikeyService) {
            $digikeyPart = is_array($supplierLinks['digikey'])
                ? ($supplierLinks['digikey']['part_number'] ?? null)
                : $supplierLinks['digikey'];

            if ($digikeyPart) {
                try {
                    $partData = $this->digikeyService->getPartDetails($digikeyPart);
                    if (!empty($partData['datasheet_url'])) {
                        // Save datasheet URL to component
                        $component->update(['datasheet_url' => $partData['datasheet_url']]);
                        return $partData['datasheet_url'];
                    }
                } catch (\Exception $e) {
                    Log::debug('DigiKey datasheet lookup failed', ['error' => $e->getMessage()]);
                }
            }
        }

        // Method 4: Try web search for common datasheet sites
        if ($component->manufacturer && $component->manufacturer_part_number) {
            $datasheetUrl = $this->searchDatasheetOnWeb($component->manufacturer, $component->manufacturer_part_number);
            if ($datasheetUrl) {
                // Save datasheet URL to component
                $component->update(['datasheet_url' => $datasheetUrl]);
                return $datasheetUrl;
            }
        }

        return null;
    }

    /**
     * Search for datasheet URL on common datasheet websites
     *
     * @param string $manufacturer
     * @param string $partNumber
     * @return string|null
     */
    protected function searchDatasheetOnWeb(string $manufacturer, string $partNumber): ?string
    {
        try {
            // Clean manufacturer and part number
            $mfr = strtolower(trim($manufacturer));
            $mpn = trim($partNumber);

            // Common datasheet sites with direct URL patterns
            $datasheetSites = [
                // Manufacturer sites
                'ti.com' => "https://www.ti.com/lit/ds/symlink/{$mpn}.pdf",
                'onsemi.com' => "https://www.onsemi.com/pdf/datasheet/{$mpn}-d.pdf",
                'nxp.com' => "https://www.nxp.com/docs/en/data-sheet/{$mpn}.pdf",
                'infineon.com' => "https://www.infineon.com/dgdl/Infineon-{$mpn}-DataSheet-v01_00-EN.pdf",
                'st.com' => "https://www.st.com/resource/en/datasheet/{$mpn}.pdf",
                'microchip.com' => "https://ww1.microchip.com/downloads/en/DeviceDoc/{$mpn}.pdf",

                // Generic datasheet repositories
                'alldatasheet' => "https://www.alldatasheet.com/datasheet-pdf/pdf/1/" . urlencode($mfr) . "/" . urlencode($mpn) . ".html",
                'datasheetcatalog' => "https://www.datasheetcatalog.com/datasheets_pdf/" . strtoupper(substr($mpn, 0, 1)) . "/" . urlencode($mpn) . ".shtml",
            ];

            // Try to guess manufacturer-specific site
            $mfrPatterns = [
                'texas instruments' => 'ti.com',
                'ti' => 'ti.com',
                'on semiconductor' => 'onsemi.com',
                'onsemi' => 'onsemi.com',
                'nxp' => 'nxp.com',
                'infineon' => 'infineon.com',
                'st microelectronics' => 'st.com',
                'stm' => 'st.com',
                'microchip' => 'microchip.com',
            ];

            // Check if URL exists
            foreach ($mfrPatterns as $pattern => $site) {
                if (str_contains($mfr, $pattern) && isset($datasheetSites[$site])) {
                    $url = $datasheetSites[$site];

                    // Quick HEAD request to check if URL exists
                    $response = Http::timeout(5)->head($url);
                    if ($response->successful() && str_contains($response->header('Content-Type'), 'pdf')) {
                        Log::info('Found datasheet via manufacturer site', [
                            'url' => $url,
                            'manufacturer' => $manufacturer,
                            'mpn' => $partNumber
                        ]);
                        return $url;
                    }
                }
            }

            // Fallback: Try AllDatasheet (most comprehensive)
            if (isset($datasheetSites['alldatasheet'])) {
                $url = $datasheetSites['alldatasheet'];
                Log::debug('Trying AllDatasheet', ['url' => $url]);

                // Note: AllDatasheet returns HTML page, not direct PDF
                // The PDF link needs to be extracted from the page
                // For now, just return the search page URL
                return $url;
            }

        } catch (\Exception $e) {
            Log::warning('Datasheet web search failed', [
                'error' => $e->getMessage(),
                'manufacturer' => $manufacturer,
                'mpn' => $partNumber
            ]);
        }

        return null;
    }

    /**
     * Normalize value based on field type
     *
     * @param mixed $value
     * @param string $field
     * @return string|null
     */
    protected function normalizeValue($value, string $field): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim((string) $value);

        // Normalize mounting type
        if ($field === 'mounting_type') {
            if (preg_match('/(smd|smt|surface)/i', $value)) {
                return 'SMD';
            } elseif (preg_match('/(through|tht|thru)/i', $value)) {
                return 'Through Hole';
            }
        }

        // Normalize tolerance
        if ($field === 'tolerance') {
            if (!str_contains($value, '±')) {
                $value = '±' . $value;
            }
        }

        return $value;
    }

    /**
     * Detect mounting type from package type
     *
     * @param string $packageType
     * @return string
     */
    protected function detectMountingType(string $packageType): string
    {
        $smdPatterns = ['0201', '0402', '0603', '0805', '1206', '1210', '2010', '2512',
                        'sot', 'soic', 'tqfp', 'qfn', 'dfn', 'bga', 'lga',
                        '1616', 'p1616', '2020', '3030', '5050', // LED packages
                        'smd', 'smt', 'mlcc'];

        $throughHolePatterns = ['dip', 'to-220', 'to-92', 'radial', 'axial', 'through hole', 'tht'];

        $packageLower = strtolower($packageType);

        // Check through-hole patterns first (more specific)
        foreach ($throughHolePatterns as $pattern) {
            if (str_contains($packageLower, $pattern)) {
                return 'Through Hole';
            }
        }

        // Check SMD patterns
        foreach ($smdPatterns as $pattern) {
            if (str_contains($packageLower, $pattern)) {
                return 'SMD';
            }
        }

        // Default to SMD for most modern components
        return 'SMD';
    }

    /**
     * Merge two specification arrays (preference to non-null values)
     *
     * @param array $specs1
     * @param array $specs2
     * @return array
     */
    protected function mergeSpecifications(array $specs1, array $specs2): array
    {
        foreach ($specs2 as $key => $value) {
            if ($value !== null && (!isset($specs1[$key]) || $specs1[$key] === null)) {
                $specs1[$key] = $value;
            }
        }
        return $specs1;
    }

    /**
     * Check if specifications are complete (all major fields filled)
     *
     * @param array $specs
     * @return bool
     */
    protected function isComplete(array $specs): bool
    {
        return $this->calculateCompleteness($specs) >= $this->minCompletenessThreshold;
    }

    /**
     * Calculate completeness percentage
     *
     * @param array $specs
     * @return int Percentage (0-100)
     */
    protected function calculateCompleteness(array $specs): int
    {
        $importantFields = [
            'value' => 15,
            'tolerance' => 10,
            'voltage_rating' => 12,
            'current_rating' => 10,
            'power_rating' => 10,
            'package_type' => 13,
            'mounting_type' => 15,
            'operating_temperature' => 10,
            'case_style' => 5,
        ];

        $totalWeight = array_sum($importantFields);
        $achievedWeight = 0;

        foreach ($importantFields as $field => $weight) {
            if (!empty($specs[$field])) {
                $achievedWeight += $weight;
            }
        }

        return (int) (($achievedWeight / $totalWeight) * 100);
    }

    /**
     * Enable/disable datasheet scraping
     *
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Check if datasheet scraping is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set completeness threshold
     *
     * @param int $threshold Percentage (0-100)
     * @return self
     */
    public function setCompletenessThreshold(int $threshold): self
    {
        $this->minCompletenessThreshold = max(0, min(100, $threshold));
        return $this;
    }
}
