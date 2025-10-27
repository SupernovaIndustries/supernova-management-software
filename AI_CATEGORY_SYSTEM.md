# AI-Powered Category Generation System

## Overview

This document describes the AI-powered category generation system for automatic component categorization during CSV/Excel imports. The system uses Ollama (local AI) to intelligently analyze component descriptions and create or match categories.

## System Components

### 1. OllamaService (`app/Services/OllamaService.php`)

Provides integration with Ollama API for local AI model interactions.

**Key Features:**
- Automatic Ollama URL and model detection from CompanyProfile
- JSON response parsing with fallback extraction
- Chat and completion endpoints support
- Model listing and availability checking
- Comprehensive error handling and logging

**Main Methods:**
```php
// Check if Ollama is available
$ollama->isAvailable(): bool

// Generate text completion
$ollama->generateText(string $prompt): ?string

// Generate JSON response (with automatic parsing)
$ollama->generateJson(string $prompt): ?array

// Chat with context
$ollama->chat(array $messages): ?array

// List available models
$ollama->listModels(): array
```

### 2. AiCategoryService (`app/Services/AiCategoryService.php`)

Core service for AI-powered category generation and similarity matching.

**Key Features:**
- AI-based category extraction from descriptions
- Semantic similarity matching to prevent duplicates
- Multi-algorithm similarity calculation (AI + string-based)
- Batch processing support for multiple components
- Automatic fallback to keyword-based categorization
- Italian language category names

**Main Methods:**
```php
// Generate category from description
$service->generateCategoryFromDescription(string $description, ?string $manufacturer): ?Category

// Find similar existing categories
$service->findSimilarCategories(string $categoryName): Collection

// Check if should use similar category
$service->shouldUseSimilarCategory(string $proposed, Category $existing): bool

// Batch generate categories
$service->batchGenerateCategories(array $components): array

// Configure similarity threshold
$service->setSimilarityThreshold(float $threshold): void
```

**Similarity Algorithms:**
1. **AI Semantic Similarity** (Primary)
   - Uses Ollama to understand semantic meaning
   - Handles synonyms (Capacitor vs Condensatore)
   - Language-aware (English vs Italian)
   - Returns 0-100 score with reasoning

2. **String-Based Similarity** (Fallback)
   - Levenshtein distance
   - Similar text percentage
   - Word-based comparison
   - Combined average score

**Default Threshold:** 80% similarity

### 3. ComponentImportService Integration

The existing import service has been enhanced to use AI category generation.

**Changes:**
- Constructor initializes AiCategoryService
- Category detection now uses AI when available
- Manufacturer context included in category generation
- Original keyword-based method renamed to `keywordBasedCategoryDetection()`
- New `intelligentCategoryDetection()` tries AI first, then falls back

**Control Methods:**
```php
// Enable/disable AI categories
$importService->setUseAiCategories(bool $enabled): self

// Check if AI is available
$importService->isAiCategoriesAvailable(): bool
```

## Database Changes

### Migration: `add_ollama_model_to_company_profiles_table`

Adds `ollama_model` field to `company_profiles` table.

```php
Schema::table('company_profiles', function (Blueprint $table) {
    $table->string('ollama_model')->nullable()->after('ollama_url');
});
```

### CompanyProfile Model Update

Added `ollama_model` to fillable fields. This allows users to configure which Ollama model to use for AI operations.

**Available Models:**
- llama3.2 (default)
- llama3.1
- llama3
- mistral
- mixtral
- codellama

## Configuration

### CompanyProfile Settings

Configure in the admin panel (Company Profile page):

1. **Ollama URL** (`ollama_url`)
   - Default: `http://localhost:11434`
   - Change if Ollama is running on a different host/port

2. **Ollama Model** (`ollama_model`)
   - Default: `llama3.2`
   - Select the AI model to use for category generation

## AI Prompts

### Category Extraction Prompt

```
Analyze this electronic component description and extract categorization information.

Description: "{description}"
Manufacturer: "{manufacturer}"

Extract:
1. Main component type in Italian (e.g., Condensatore, Resistenza, Induttore, IC, Connettore, etc.)
2. Specific characteristics/subcategory in Italian (e.g., Ceramico, Elettrolitico, SMD, Through-hole, MLCC, RF, GPS, etc.)
3. Suggested category name in Italian following the pattern: "Main Type + Characteristics"
4. Confidence score from 0 to 100

Respond in JSON format:
{
  "main_type": "main component type in Italian",
  "characteristics": "specific characteristics in Italian",
  "suggested_category": "complete category name in Italian",
  "confidence": 85
}
```

### Similarity Matching Prompt

```
Compare these two electronic component category names and determine if they refer to the same type of component.
Consider:
- Synonyms (e.g., "Capacitor" vs "Condensatore")
- Language variations (English vs Italian)
- Abbreviations (e.g., "IC" vs "Integrated Circuit")
- Similar component types

Category 1: "{category1}"
Category 2: "{category2}"

Respond in JSON format:
{
  "are_similar": true,
  "similarity_score": 95,
  "reasoning": "explanation"
}

The similarity_score should be 0-100, where:
- 100 = Identical meaning
- 80-99 = Very similar, same component type
- 60-79 = Similar, related component types
- 40-59 = Somewhat related
- 0-39 = Different component types
```

## Example Category Names

The AI generates Italian category names following these patterns:

### Passive Components
- **Condensatori Ceramici MLCC** - Multilayer ceramic capacitors
- **Condensatori Elettrolitici Alluminio** - Aluminum electrolytic capacitors
- **Condensatori Film Poliestere** - Film capacitors
- **Resistenze a Film Metallico** - Metal film resistors
- **Resistenze SMD** - SMD resistors
- **Induttori SMD di Potenza** - Power inductors

### Active Components
- **Diodi Schottky** - Schottky diodes
- **Diodi Zener** - Zener diodes
- **Transistor Bipolari NPN** - NPN transistors
- **MOSFETs Canale N** - N-channel MOSFETs

### Integrated Circuits
- **Microcontrollori ARM** - ARM microcontrollers
- **Regolatori di Tensione LDO** - LDO voltage regulators
- **Amplificatori Operazionali** - Operational amplifiers
- **Convertitori DC-DC** - DC-DC converters

### Connectors
- **Connettori USB Type-C** - USB Type-C connectors
- **Connettori RF SMA** - SMA RF connectors
- **Connettori Board-to-Board** - Board-to-board connectors

### RF Components
- **Antenne GPS/GNSS** - GPS/GNSS antennas
- **Antenne WiFi/Bluetooth** - WiFi/Bluetooth antennas
- **Antenne LTE/Cellular** - LTE/Cellular antennas

### Sensors
- **Sensori IMU** - IMU sensors (accelerometer + gyroscope)
- **Sensori di Temperatura** - Temperature sensors
- **Sensori di Pressione** - Pressure sensors

## Testing

### Prerequisites

1. **Install Ollama**
   ```bash
   # macOS
   brew install ollama

   # Linux
   curl -fsSL https://ollama.ai/install.sh | sh
   ```

2. **Pull a Model**
   ```bash
   ollama pull llama3.2
   ```

3. **Start Ollama Server**
   ```bash
   ollama serve
   ```

4. **Run Migration**
   ```bash
   php artisan migrate
   ```

5. **Configure CompanyProfile**
   - Log into admin panel
   - Navigate to Company Profile
   - Set Ollama URL: `http://localhost:11434`
   - Set Ollama Model: `llama3.2`

### Manual Testing

#### Test 1: Ollama Service

```php
use App\Services\OllamaService;

$ollama = app(OllamaService::class);

// Check availability
if ($ollama->isAvailable()) {
    echo "Ollama is available!\n";

    // List models
    $models = $ollama->listModels();
    print_r($models);

    // Test text generation
    $response = $ollama->generateText("What is a capacitor?");
    echo $response . "\n";

    // Test JSON generation
    $json = $ollama->generateJson("List 3 types of capacitors in JSON format");
    print_r($json);
} else {
    echo "Ollama is not available\n";
}
```

#### Test 2: AI Category Service

```php
use App\Services\AiCategoryService;

$service = app(AiCategoryService::class);

// Test category generation
$category = $service->generateCategoryFromDescription(
    "CAP CER 10UF 16V X7R 0805",
    "Samsung"
);

echo "Generated category: " . $category->name . "\n";
echo "Category ID: " . $category->id . "\n";

// Test similarity matching
$similar = $service->findSimilarCategories("Condensatori Ceramici");
echo "Found " . $similar->count() . " similar categories\n";
foreach ($similar as $cat) {
    echo "  - {$cat->name} (score: {$cat->similarity_score})\n";
}
```

#### Test 3: Import with AI Categories

```php
use App\Services\ComponentImportService;

$service = app(ComponentImportService::class);

// Check if AI is available
if ($service->isAiCategoriesAvailable()) {
    echo "AI category generation is enabled\n";
} else {
    echo "Using fallback keyword-based categories\n";
}

// Import CSV file
$results = $service->importFromCsv(
    storage_path('app/test_components.csv'),
    'digikey'
);

echo "Imported: {$results['imported']}\n";
echo "Updated: {$results['updated']}\n";
echo "Failed: {$results['failed']}\n";
```

### Test CSV Data

Create a test file at `storage/app/test_components.csv`:

```csv
Digi-Key Part Number,Manufacturer Part Number,Manufacturer,Description,Quantity Available,Unit Price
123-456-ND,GRM21BR61C106KE15L,Murata,"CAP CER 10UF 16V X7R 0805",10000,0.15
456-789-ND,RC0805FR-0710KL,Yageo,"RES SMD 10K OHM 1% 1/8W 0805",50000,0.05
789-012-ND,NEO-M9N-00B,u-blox,"GNSS MODULE GPS/GALILEO/GLONASS",500,35.50
012-345-ND,FPC40P050-03011,JAE Electronics,"CONN FFC/FPC 40POS 0.5MM R/A",1000,1.25
345-678-ND,SRR1260-100M,Bourns,"POWER INDUCTOR 10UH 8A SMD",2000,0.85
```

### Expected Results

For each component, the system should:
1. Analyze the description using AI
2. Generate appropriate Italian category names:
   - `CAP CER...` → "Condensatori Ceramici MLCC"
   - `RES SMD...` → "Resistenze SMD"
   - `GNSS MODULE...` → "Moduli GNSS/GPS"
   - `CONN FFC/FPC...` → "Connettori FFC/FPC"
   - `POWER INDUCTOR...` → "Induttori di Potenza"
3. Check for similar existing categories
4. Reuse categories if similarity > 80%
5. Create new categories only when needed

### Success Criteria

✅ **AI Service**
- Ollama connection successful
- JSON responses properly parsed
- Error handling works (fallback when Ollama unavailable)

✅ **Category Generation**
- Meaningful Italian category names created
- Categories match component type accurately
- Similar categories detected and reused
- No duplicate categories created

✅ **Import Integration**
- AI categories used when available
- Fallback to keyword-based when AI fails
- All components successfully categorized
- Performance remains acceptable (< 5 seconds per component)

## Troubleshooting

### Issue: Ollama Not Available

**Symptoms:**
- Logs show "Ollama availability check failed"
- Imports use fallback keyword-based categories

**Solutions:**
1. Check if Ollama is running: `ps aux | grep ollama`
2. Start Ollama: `ollama serve`
3. Verify URL in CompanyProfile settings
4. Test connection: `curl http://localhost:11434/api/tags`

### Issue: Invalid JSON Responses

**Symptoms:**
- Logs show "Failed to parse JSON from Ollama response"
- Categories not generated correctly

**Solutions:**
1. Try a different model (e.g., llama3.1 instead of llama3.2)
2. Check model is properly downloaded: `ollama list`
3. Pull model again: `ollama pull llama3.2`
4. Review logs for AI responses

### Issue: Slow Import Performance

**Symptoms:**
- Imports taking too long (> 10 seconds per component)
- Timeout errors

**Solutions:**
1. Disable AI categories: `$service->setUseAiCategories(false)`
2. Use a faster model (e.g., codellama)
3. Increase timeout in OllamaService
4. Batch process components in smaller chunks

### Issue: Wrong Categories Generated

**Symptoms:**
- AI creates incorrect category names
- Similar categories not detected

**Solutions:**
1. Adjust similarity threshold: `$service->setSimilarityThreshold(70)`
2. Review and improve AI prompts
3. Add manual category corrections
4. Use more specific manufacturer context

## Performance Considerations

### Caching Strategy

The system implements multiple caching levels:

1. **Description Cache** - Same descriptions reuse categories
2. **Similarity Cache** - Category comparisons cached in memory
3. **Batch Processing** - Multiple components processed efficiently

### Optimization Tips

1. **Pre-warm Cache**
   - Import similar components together
   - Group by category type in CSV

2. **Adjust Similarity Threshold**
   - Higher (90%): More categories, fewer false matches
   - Lower (70%): Fewer categories, more aggressive matching

3. **Model Selection**
   - Large models (llama3.1): Better accuracy, slower
   - Small models (llama3.2): Faster, good enough for most cases

## Future Enhancements

### Planned Features

1. **Category Hierarchy**
   - AI generates parent-child relationships
   - Example: "Condensatori" → "Condensatori Ceramici" → "Condensatori Ceramici MLCC"

2. **Multi-language Support**
   - Generate categories in multiple languages
   - Automatic translation

3. **Learning System**
   - Learn from user corrections
   - Improve category suggestions over time

4. **Confidence Scoring**
   - Show AI confidence in UI
   - Allow manual review of low-confidence categories

5. **Category Merging**
   - UI tool to merge similar categories
   - Automatic duplicate detection

## License and Credits

Part of Supernova Management Software - Laravel + Filament v3

AI-powered category generation implemented using:
- Ollama (https://ollama.ai) - Local AI models
- Laravel (https://laravel.com) - PHP framework
- Filament (https://filamentphp.com) - Admin panel
