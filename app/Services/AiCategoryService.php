<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AiCategoryService
 *
 * Intelligent category generation service using AI to analyze component descriptions
 * and automatically create or match categories during component import.
 *
 * Features:
 * - AI-powered category extraction from descriptions
 * - Semantic similarity matching to prevent duplicates
 * - Fallback keyword-based categorization
 * - Italian language support for category names
 */
class AiCategoryService
{
    /**
     * Ollama service instance
     */
    protected OllamaService $ollama;

    /**
     * Similarity threshold for considering categories as the same (0-100)
     */
    protected float $similarityThreshold = 80.0;

    /**
     * Cache for category similarity calculations
     */
    protected array $similarityCache = [];

    /**
     * Constructor
     */
    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    /**
     * Generate or find a category from component description using AI
     *
     * @param string $description Component description
     * @param string|null $manufacturer Component manufacturer (optional context)
     * @return Category|null Generated or matched category
     */
    public function generateCategoryFromDescription(string $description, ?string $manufacturer = null): ?Category
    {
        try {
            Log::info('AI category generation started', [
                'description' => substr($description, 0, 100),
                'manufacturer' => $manufacturer
            ]);

            // Try AI-based category extraction
            if ($this->ollama->isAvailable()) {
                $categoryData = $this->analyzeWithAI($description, $manufacturer);

                if ($categoryData && !empty($categoryData['suggested_category'])) {
                    $proposedName = $categoryData['suggested_category'];
                    $confidence = $categoryData['confidence'] ?? 0;

                    Log::info('AI suggested category', [
                        'category' => $proposedName,
                        'confidence' => $confidence,
                        'main_type' => $categoryData['main_type'] ?? null,
                        'characteristics' => $categoryData['characteristics'] ?? null
                    ]);

                    // Find similar existing categories
                    $similarCategories = $this->findSimilarCategories($proposedName);

                    if ($similarCategories->isNotEmpty()) {
                        // Check if we should use an existing category
                        foreach ($similarCategories as $existingCategory) {
                            if ($this->shouldUseSimilarCategory($proposedName, $existingCategory)) {
                                Log::info('Using existing similar category', [
                                    'proposed' => $proposedName,
                                    'existing' => $existingCategory->name
                                ]);
                                return $existingCategory;
                            }
                        }
                    }

                    // Create new category if no match found
                    $category = $this->createCategory($proposedName, $categoryData);

                    Log::info('Created new AI-generated category', [
                        'category' => $category->name,
                        'id' => $category->id
                    ]);

                    return $category;
                }
            }

            // Fallback to keyword-based categorization
            Log::info('AI not available, using fallback categorization');
            return $this->fallbackCategoryDetection($description, $manufacturer);

        } catch (\Exception $e) {
            Log::error('AI category generation failed', [
                'error' => $e->getMessage(),
                'description' => substr($description, 0, 100)
            ]);

            // Ultimate fallback
            return $this->fallbackCategoryDetection($description, $manufacturer);
        }
    }

    /**
     * Analyze component description with AI to extract category information
     *
     * @param string $description Component description
     * @param string|null $manufacturer Component manufacturer
     * @return array|null Category data or null on failure
     */
    protected function analyzeWithAI(string $description, ?string $manufacturer = null): ?array
    {
        $manufacturerContext = $manufacturer ? "\nManufacturer: {$manufacturer}" : '';

        $prompt = <<<PROMPT
You are an electronics expert. Analyze this component and create a category name in Italian.

Component: "{$description}"{$manufacturerContext}

CRITICAL RULES - READ CAREFULLY:

1. ACTIVE COMPONENTS (NOT "Supporti" or generic categories):
   - GPS/GNSS antenna → "Antenne GPS/GNSS" (NOT "Supporti")
   - GPS/GNSS module → "Moduli GNSS/GPS" (NOT "Supporti")
   - WiFi/Bluetooth antenna → "Antenne WiFi/Bluetooth" (NOT "Supporti")
   - RF antenna → "Antenne RF" (NOT "Supporti")
   - Camera module → "Moduli Camera" (NOT "Supporti")
   - Display/LCD → "Display e Schermi" (NOT "Supporti")
   - Sensor (IMU, temp, pressure) → Specific sensor category (NOT "Supporti")

2. ONLY use "Supporti" or generic categories for:
   - Battery holders (OK as "Supporti" or "Holder per Batterie")
   - Mounting hardware (standoffs, spacers, screws)
   - PCB supports and brackets
   - Cable ties, clips, organizational items

3. POWER COMPONENTS:
   - High current (>3A) inductor → "Induttori di Potenza"
   - Battery charger IC → "IC Caricabatterie"
   - Linear regulator IC → "Regolatori di Tensione LDO"
   - Buck/Boost converter → "Convertitori DC-DC"
   - MOSFET for switching/power → "MOSFET di Potenza"

4. PASSIVES (VERY IMPORTANT - check carefully):
   - ANY capacitor (CAP CER, CAP MLCC, etc.) → "Condensatori Ceramici MLCC" (if X7R/X5R/C0G/ceramic)
   - Capacitor electrolytic → "Condensatori Elettrolitici"
   - Capacitor tantalum → "Condensatori al Tantalio"
   - Ferrite bead (FERRITE BEAD) → "Ferrite Beads" (NOT resistor!)
   - Resistor SMD → "Resistenze SMD"
   - Inductor → "Induttori" or "Induttori di Potenza" (if high current)

5. CONNECTORS (be specific):
   - Board-to-Board → "Connettori Board-to-Board"
   - FFC/FPC → "Connettori FFC/FPC"
   - Memory Card/SD → "Connettori Memory Card"
   - USB → "Connettori USB"
   - RF coaxial → "Connettori RF Coassiali"

6. SEMICONDUCTORS:
   - Schottky diodes → "Diodi Schottky"
   - TVS diodes → "Diodi Protettori TVS"
   - Microcontroller → "Microcontrollori"

Create a CONCISE, SPECIFIC Italian category name (2-4 words max) based on PRIMARY FUNCTION.

Examples:
✅ "GPS antenna" → "Antenne GPS/GNSS"
✅ "GPS ANT 1575MHz" → "Antenne GPS/GNSS"
✅ "GNSS passive antenna" → "Antenne GPS/GNSS"
✅ "Battery holder 18650" → "Holder per Batterie"
✅ "IMU 6-axis" → "Sensori IMU"
✅ "6A inductor" → "Induttori di Potenza"
✅ "CAP CER 47PF 16V X7R 0402" → "Condensatori Ceramici MLCC"
✅ "FERRITE BEAD 60 OHM 0402" → "Ferrite Beads"
❌ "GPS antenna" → "Supporti Elettronica Generale" (WRONG!)
❌ "CAP CER 47PF" → "Diodi Protettori TVS" (VERY WRONG!)
❌ "FERRITE BEAD" → "Resistenze SMD" (WRONG!)

Respond ONLY with valid JSON:
{"main_type":"type","characteristics":"chars","suggested_category":"name","confidence":90}
PROMPT;

        $result = $this->ollama->generateJson($prompt);

        if ($result && isset($result['suggested_category'])) {
            // Ensure confidence is numeric
            if (!isset($result['confidence']) || !is_numeric($result['confidence'])) {
                $result['confidence'] = 50;
            }

            // Apply post-processing rules to refine the category
            $result['suggested_category'] = $this->refineCategory($result['suggested_category'], $description);

            return $result;
        }

        Log::warning('AI category extraction returned invalid data', [
            'result' => $result
        ]);

        return null;
    }

    /**
     * Refine category based on keyword rules
     * Applies specific rules to ensure consistent categorization
     *
     * @param string $aiCategory Category suggested by AI
     * @param string $description Original component description
     * @return string Refined category name
     */
    protected function refineCategory(string $aiCategory, string $description): string
    {
        $descLower = strtolower($description);

        // ============================================================
        // PASSIVES - HIGHEST PRIORITY (check FIRST to avoid misclassification)
        // ============================================================

        // Ceramic Capacitors (MLCC) - PRIORITY #1
        if (preg_match('/\bcap\b/i', $description) &&
            (preg_match('/\bcer/i', $description) ||
             preg_match('/\b(x[57]r|c0g|npo|y5v|z5u)\b/i', $description) ||
             preg_match('/\bmlcc\b/i', $description))) {
            return 'Condensatori Ceramici MLCC';
        }

        // Generic Capacitors (if not ceramic)
        if (preg_match('/\bcap\b/i', $description)) {
            // Electrolytic
            if (preg_match('/\b(elec|electrolytic|aluminum|alu)\b/i', $description)) {
                return 'Condensatori Elettrolitici';
            }
            // Tantalum
            if (preg_match('/\btantal/i', $description)) {
                return 'Condensatori al Tantalio';
            }
            // Film capacitors
            if (preg_match('/\b(film|polyester|polypropylene|mylar)\b/i', $description)) {
                return 'Condensatori a Film';
            }
            // Generic capacitor fallback
            return 'Condensatori Ceramici MLCC';
        }

        // Ferrite Beads (before resistors/inductors)
        if (preg_match('/\bferrite\b/i', $description) ||
            preg_match('/\bbead\b/i', $description)) {
            return 'Ferrite Beads';
        }

        // Resistors
        if (preg_match('/\bres\b/i', $description) || preg_match('/\bresist/i', $description)) {
            if (preg_match('/\b(smd|chip|surface)/i', $description) ||
                preg_match('/\b(0201|0402|0603|0805|1206|1210|2010|2512)\b/i', $description)) {
                return 'Resistenze SMD';
            }
            if (preg_match('/\b(thru|through|axial|lead)\b/i', $description)) {
                return 'Resistenze Through-Hole';
            }
            return 'Resistenze SMD'; // Default to SMD
        }

        // Inductors
        if (preg_match('/\b(ind|inductor)\b/i', $description)) {
            // High current = power inductors
            if (preg_match('/([3-9]|[1-9]\d+)(\.\d+)?a/i', $description) ||
                preg_match('/\bfixed\s+ind\b/i', $description) ||
                preg_match('/\bpower\b/i', $description)) {
                return 'Induttori di Potenza';
            }
            return 'Induttori';
        }

        // ============================================================
        // SEMICONDUCTORS & ICs
        // ============================================================

        // Battery Chargers
        if (preg_match('/\b(batt.*chg|battery.*charg|charger)\b/i', $description)) {
            return 'IC Caricabatterie';
        }

        // Linear Regulators (LDO)
        if (preg_match('/\b(reg.*linear|ldo|voltage.*regulator)\b/i', $description)) {
            return 'Regolatori di Tensione LDO';
        }

        // ============================================================
        // ANTENNAS & RF
        // ============================================================

        // GNSS/GPS Modules and Antennas (PRIORITY - check before generic categories)
        if (preg_match('/\b(gnss|gps|galileo|glonass)\b/i', $description)) {
            // Check if it's a module first
            if (preg_match('/\b(module|receiver|rx|chip|ic)\b/i', $description)) {
                return 'Moduli GNSS/GPS';
            }
            // Then check if it's an antenna (most common case)
            if (preg_match('/\b(ant|antenna|aerial)\b/i', $description)) {
                return 'Antenne GPS/GNSS';
            }
            // If GPS/GNSS mentioned but no specific type, assume antenna
            if (preg_match('/\b(passive|active|ceramic|patch)\b/i', $description)) {
                return 'Antenne GPS/GNSS';
            }
        }

        // WiFi/Bluetooth Antennas (check before RF connectors)
        if (preg_match('/\b(wifi|wi-fi|bluetooth|ble|2\.4ghz|5ghz)\b/i', $description) &&
            preg_match('/\b(ant|antenna|aerial)\b/i', $description)) {
            return 'Antenne WiFi/Bluetooth';
        }

        // RF Antennas (generic)
        if (preg_match('/\b(rf|radio|wireless)\b/i', $description) &&
            preg_match('/\b(ant|antenna|aerial)\b/i', $description) &&
            !preg_match('/\bconn/i', $description)) {
            return 'Antenne RF';
        }

        // Battery Holders (Supporti category is OK for these)
        if (preg_match('/\b(battery|batt).*\b(holder|clip|contact|mount)\b/i', $description) ||
            preg_match('/\b(holder|clip).*\b(battery|batt|cell)\b/i', $description)) {
            return 'Holder per Batterie';
        }

        // Memory Card Connectors
        if (preg_match('/\b(micro.*sd|sd.*card|memory.*card).*\bconn/i', $description)) {
            return 'Connettori Memory Card';
        }

        // FFC/FPC Connectors
        if (preg_match('/\b(ffc|fpc).*\bconn/i', $description)) {
            return 'Connettori FFC/FPC';
        }

        // Board-to-Board Connectors (high pin count receptacles)
        if (preg_match('/\b(conn|connector).*\b(rcpt|receptacle).*\b\d{2,3}pos\b/i', $description)) {
            return 'Connettori Board-to-Board';
        }

        // USB Connectors
        if (preg_match('/\busb.*\bconn/i', $description)) {
            return 'Connettori USB';
        }

        // MOSFETs
        if (preg_match('/\bmosfet/i', $description)) {
            return 'MOSFET di Potenza';
        }

        // Schottky Diodes
        if (preg_match('/\bdiode.*schottky/i', $description)) {
            return 'Diodi Schottky';
        }

        // LEDs
        if (preg_match('/\bled\b/i', $description)) {
            return 'LED';
        }

        // Microcontrollers
        if (preg_match('/\b(mcu|microcontroller|cortex|arm)/i', $description)) {
            return 'Microcontrollori';
        }

        // Single Board Computers
        if (preg_match('/\b(sbc|raspberry|compute.*module)\b/i', $description)) {
            return 'Computer a Scheda Singola';
        }

        // Heatsinks
        if (preg_match('/\b(heatsink|heat.*sink|cooler)\b/i', $description)) {
            return 'Dissipatori di Calore';
        }

        // IMU Sensors (check before generic sensors)
        if (preg_match('/\b(imu|mems|accelerometer|gyroscope|gyro|accel|magnetometer)\b/i', $description) &&
            preg_match('/\b(sensor|module|ic|chip|6-axis|9-axis|3-axis)\b/i', $description)) {
            return 'Sensori IMU';
        }

        // Temperature/Humidity Sensors
        if (preg_match('/\b(temp|temperature|humidity|pressure).*\b(sensor|ic)\b/i', $description)) {
            return 'Sensori Ambientali';
        }

        // Cameras
        if (preg_match('/\bcamera/i', $description)) {
            return 'Moduli Camera';
        }

        // Display/LCD/OLED
        if (preg_match('/\b(display|lcd|oled|tft|e-ink|screen)\b/i', $description)) {
            return 'Display e Schermi';
        }

        // Voltage Level Translators
        if (preg_match('/\b(translator|level.*shift|xltr)\b/i', $description)) {
            return 'Traduttori di Livello';
        }

        // If no specific rule matches, return AI suggestion
        return $aiCategory;
    }

    /**
     * Find similar existing categories using AI-based similarity matching
     *
     * @param string $proposedCategoryName Proposed category name
     * @return Collection<Category> Collection of similar categories
     */
    public function findSimilarCategories(string $proposedCategoryName): Collection
    {
        // Get all active categories
        $allCategories = Category::where('is_active', true)->get();

        if ($allCategories->isEmpty()) {
            return collect();
        }

        $similarCategories = collect();

        foreach ($allCategories as $category) {
            $similarity = $this->calculateSimilarity($proposedCategoryName, $category->name);

            if ($similarity >= $this->similarityThreshold) {
                // Add similarity score as attribute
                $category->similarity_score = $similarity;
                $similarCategories->push($category);
            }
        }

        // Sort by similarity score (highest first)
        return $similarCategories->sortByDesc('similarity_score');
    }

    /**
     * Decide whether to use a similar existing category
     *
     * @param string $proposedName Proposed category name
     * @param Category $existingCategory Existing category to compare
     * @return bool True if should use existing category
     */
    public function shouldUseSimilarCategory(string $proposedName, Category $existingCategory): bool
    {
        $similarity = $this->calculateSimilarity($proposedName, $existingCategory->name);

        Log::debug('Category similarity check', [
            'proposed' => $proposedName,
            'existing' => $existingCategory->name,
            'similarity' => $similarity
        ]);

        // Use existing category if similarity is above threshold
        return $similarity >= $this->similarityThreshold;
    }

    /**
     * Calculate similarity between two category names
     * Uses both AI semantic analysis and string similarity metrics
     *
     * @param string $name1 First category name
     * @param string $name2 Second category name
     * @return float Similarity score (0-100)
     */
    protected function calculateSimilarity(string $name1, string $name2): float
    {
        // Check cache first
        $cacheKey = md5($name1 . '|' . $name2);
        if (isset($this->similarityCache[$cacheKey])) {
            return $this->similarityCache[$cacheKey];
        }

        // Quick exact match check
        if (strtolower(trim($name1)) === strtolower(trim($name2))) {
            $this->similarityCache[$cacheKey] = 100.0;
            return 100.0;
        }

        // Try AI-based semantic similarity if available
        if ($this->ollama->isAvailable()) {
            $aiSimilarity = $this->calculateAiSimilarity($name1, $name2);
            if ($aiSimilarity !== null) {
                $this->similarityCache[$cacheKey] = $aiSimilarity;
                return $aiSimilarity;
            }
        }

        // Fallback to string-based similarity
        $stringSimilarity = $this->calculateStringSimilarity($name1, $name2);
        $this->similarityCache[$cacheKey] = $stringSimilarity;

        return $stringSimilarity;
    }

    /**
     * Calculate similarity using AI semantic analysis
     *
     * @param string $name1 First category name
     * @param string $name2 Second category name
     * @return float|null Similarity score (0-100) or null on failure
     */
    protected function calculateAiSimilarity(string $name1, string $name2): ?float
    {
        $prompt = <<<PROMPT
Compare these two electronic component category names and determine if they refer to the same type of component.
Consider:
- Synonyms (e.g., "Capacitor" vs "Condensatore")
- Language variations (English vs Italian)
- Abbreviations (e.g., "IC" vs "Integrated Circuit")
- Similar component types (e.g., "Condensatori Ceramici" vs "Capacitors Ceramic")

Category 1: "{$name1}"
Category 2: "{$name2}"

Respond in JSON format:
{
  "are_similar": true,
  "similarity_score": 95,
  "reasoning": "Both refer to ceramic capacitors, just in different languages"
}

The similarity_score should be 0-100, where:
- 100 = Identical meaning
- 80-99 = Very similar, same component type
- 60-79 = Similar, related component types
- 40-59 = Somewhat related
- 0-39 = Different component types
PROMPT;

        $result = $this->ollama->generateJson($prompt);

        if ($result && isset($result['similarity_score']) && is_numeric($result['similarity_score'])) {
            Log::debug('AI similarity calculated', [
                'name1' => $name1,
                'name2' => $name2,
                'score' => $result['similarity_score'],
                'reasoning' => $result['reasoning'] ?? 'N/A'
            ]);

            return (float) $result['similarity_score'];
        }

        return null;
    }

    /**
     * Calculate string-based similarity using multiple algorithms
     *
     * @param string $name1 First category name
     * @param string $name2 Second category name
     * @return float Similarity score (0-100)
     */
    protected function calculateStringSimilarity(string $name1, string $name2): float
    {
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));

        // Calculate using multiple algorithms and average
        $similarities = [];

        // 1. Levenshtein distance
        $maxLen = max(strlen($name1), strlen($name2));
        if ($maxLen > 0) {
            $levenshtein = 1 - (levenshtein($name1, $name2) / $maxLen);
            $similarities[] = $levenshtein * 100;
        }

        // 2. Similar text percentage
        similar_text($name1, $name2, $percent);
        $similarities[] = $percent;

        // 3. Word-based comparison
        $words1 = preg_split('/\s+/', $name1);
        $words2 = preg_split('/\s+/', $name2);
        $commonWords = count(array_intersect($words1, $words2));
        $totalWords = max(count($words1), count($words2));
        if ($totalWords > 0) {
            $similarities[] = ($commonWords / $totalWords) * 100;
        }

        // Average all similarities
        return !empty($similarities) ? array_sum($similarities) / count($similarities) : 0.0;
    }

    /**
     * Create a new category with AI-extracted data
     *
     * @param string $name Category name
     * @param array $categoryData Additional category data from AI
     * @return Category Created category
     */
    protected function createCategory(string $name, array $categoryData = []): Category
    {
        $description = '';

        if (!empty($categoryData['main_type'])) {
            $description = "Tipo: {$categoryData['main_type']}";
            if (!empty($categoryData['characteristics'])) {
                $description .= " - Caratteristiche: {$categoryData['characteristics']}";
            }
        }

        return Category::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'is_active' => true,
            'sort_order' => 0
        ]);
    }

    /**
     * Fallback keyword-based category detection when AI is not available
     *
     * @param string $description Component description
     * @param string|null $manufacturer Component manufacturer
     * @return Category Detected or default category
     */
    protected function fallbackCategoryDetection(string $description, ?string $manufacturer = null): Category
    {
        $description = strtolower($description);

        // Define keyword patterns for common component types (PRIORITY ORDER)
        $categoryPatterns = [
            // Passives FIRST (highest priority to avoid misclassification)
            'Condensatori Ceramici MLCC' => ['cap cer', 'ceramic cap', 'mlcc', 'x7r', 'x5r', 'c0g', 'npo'],
            'Condensatori Elettrolitici' => ['electrolytic cap', 'aluminum cap', 'elec cap'],
            'Condensatori al Tantalio' => ['tantalum cap'],
            'Ferrite Beads' => ['ferrite bead', 'ferrite', 'bead'],
            'Resistenze SMD' => ['smd res', 'chip resistor', 'res smd'],
            'Resistenze Through-Hole' => ['thru-hole res', 'axial res'],
            'Induttori di Potenza' => ['power inductor', 'fixed ind'],
            'Induttori' => ['inductor', 'inductance', 'coil'],

            // Active components (to avoid "Supporti" misclassification)
            'Antenne GPS/GNSS' => ['gps ant', 'gnss ant', 'gps antenna', 'gnss antenna', 'galileo', 'glonass'],
            'Antenne WiFi/Bluetooth' => ['wifi ant', 'bluetooth ant', 'ble antenna', '2.4ghz antenna', 'wi-fi antenna'],
            'Antenne RF' => ['rf ant', 'rf antenna', 'wireless antenna'],
            'Moduli GNSS/GPS' => ['gps module', 'gnss module', 'gps receiver'],
            'Sensori IMU' => ['imu', 'accelerometer', 'gyroscope', '6-axis', '9-axis', 'mems sensor'],
            'Sensori Ambientali' => ['temperature sensor', 'humidity sensor', 'pressure sensor'],
            'Moduli Camera' => ['camera module', 'camera sensor'],
            'Display e Schermi' => ['display', 'lcd', 'oled', 'tft', 'screen'],

            // Semiconductors
            'Diodi Schottky' => ['schottky diode'],
            'Diodi' => ['diode', 'rectifier', 'zener'],
            'MOSFET di Potenza' => ['mosfet'],
            'Transistor' => ['transistor', 'bjt', 'fet'],
            'Microcontrollori' => ['mcu', 'microcontroller', 'arm', 'stm32', 'cortex'],
            'IC Caricabatterie' => ['battery charger', 'charger ic'],
            'Regolatori di Tensione LDO' => ['voltage regulator', 'ldo', 'linear regulator'],
            'Convertitori DC-DC' => ['buck', 'boost', 'dc-dc converter'],

            // Connectors
            'Connettori USB' => ['usb connector', 'type-c', 'micro usb'],
            'Connettori RF Coassiali' => ['rf connector', 'sma', 'u.fl', 'coaxial'],
            'Connettori Memory Card' => ['sd card', 'micro sd', 'memory card connector'],

            // Supporti (ONLY for actual mounting/holder components)
            'Holder per Batterie' => ['battery holder', 'battery clip', 'cell holder'],

            // Other
            'Cristalli' => ['crystal', 'xtal', 'oscillator'],
            'LED' => ['led'],
        ];

        // Find best match based on keywords
        $bestMatch = null;
        $highestCount = 0;

        foreach ($categoryPatterns as $categoryName => $keywords) {
            $matchCount = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    $matchCount++;
                }
            }

            if ($matchCount > $highestCount) {
                $highestCount = $matchCount;
                $bestMatch = $categoryName;
            }
        }

        // Use matched category or default
        $categoryName = $bestMatch ?? 'Componenti Generici';

        // Find or create category
        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            [
                'slug' => Str::slug($categoryName),
                'description' => "Categoria generata automaticamente da descrizione componente",
                'is_active' => true,
                'sort_order' => 0
            ]
        );

        Log::info('Fallback category detection', [
            'category' => $category->name,
            'match_count' => $highestCount
        ]);

        return $category;
    }

    /**
     * Batch generate categories for multiple components
     * More efficient than calling generateCategoryFromDescription multiple times
     *
     * @param array $components Array of component data with 'description' and optional 'manufacturer'
     * @return array Associative array mapping component index to Category
     */
    public function batchGenerateCategories(array $components): array
    {
        $results = [];
        $descriptionCache = [];

        foreach ($components as $index => $component) {
            $description = $component['description'] ?? '';
            $manufacturer = $component['manufacturer'] ?? null;

            // Use cache for identical descriptions
            $cacheKey = md5($description . '|' . $manufacturer);

            if (isset($descriptionCache[$cacheKey])) {
                $results[$index] = $descriptionCache[$cacheKey];
                Log::debug('Using cached category', ['index' => $index]);
            } else {
                $category = $this->generateCategoryFromDescription($description, $manufacturer);
                $results[$index] = $category;
                $descriptionCache[$cacheKey] = $category;
            }
        }

        return $results;
    }

    /**
     * Set similarity threshold for category matching
     *
     * @param float $threshold Similarity threshold (0-100)
     */
    public function setSimilarityThreshold(float $threshold): void
    {
        $this->similarityThreshold = max(0, min(100, $threshold));
    }

    /**
     * Get current similarity threshold
     *
     * @return float Current threshold (0-100)
     */
    public function getSimilarityThreshold(): float
    {
        return $this->similarityThreshold;
    }

    /**
     * Clear the similarity cache
     * Useful when categories have been modified
     */
    public function clearCache(): void
    {
        $this->similarityCache = [];
        Log::debug('AI category similarity cache cleared');
    }
}
