# AI Category Generation - Quick Start Guide

## Installation & Setup (5 minutes)

### 1. Install Ollama

**macOS:**
```bash
brew install ollama
```

**Linux:**
```bash
curl -fsSL https://ollama.ai/install.sh | sh
```

**Windows:**
Download from https://ollama.ai

### 2. Download AI Model

```bash
# Start Ollama server (in background)
ollama serve &

# Pull the default model (llama3.2)
ollama pull llama3.2

# Verify installation
ollama list
```

### 3. Run Database Migration

```bash
cd /Users/supernova/supernova-management
php artisan migrate
```

### 4. Configure Company Profile

Option A - Via UI:
1. Log into admin panel
2. Go to Company Profile
3. Set "Ollama URL" to `http://localhost:11434`
4. Set "Ollama Model" to `llama3.2`
5. Save

Option B - Via Tinker:
```bash
php artisan tinker
```
```php
$profile = App\Models\CompanyProfile::current();
$profile->ollama_url = 'http://localhost:11434';
$profile->ollama_model = 'llama3.2';
$profile->save();
```

## Quick Test (2 minutes)

### Test Ollama Connection

```bash
php artisan tinker
```

```php
// Test Ollama service
$ollama = app(App\Services\OllamaService::class);
$ollama->isAvailable(); // Should return true

// Test AI generation
$ollama->generateText("Say hello"); // Should return a greeting

// Test JSON generation
$ollama->generateJson("Generate JSON with fields: name, type"); // Should return array
```

### Test Category Generation

```php
// Test AI category service
$service = app(App\Services\AiCategoryService::class);

// Generate a category from description
$category = $service->generateCategoryFromDescription(
    "CAP CER 10UF 16V X7R 0805",
    "Murata"
);

echo $category->name; // Should show something like "Condensatori Ceramici MLCC"
```

### Test Import with AI

```php
// Test import service
$import = app(App\Services\ComponentImportService::class);

// Check if AI is available
$import->isAiCategoriesAvailable(); // Should return true

// Now import your CSV files normally - AI categories will be automatic!
```

## Usage in Import

### Automatic (Default)

AI category generation is **automatically enabled** for all imports. Just import your CSV/Excel files as usual:

```php
$results = $importService->importFromCsv(
    storage_path('app/components.csv'),
    'digikey'
);
```

### Manual Control

```php
// Disable AI categories (use fallback keyword matching)
$importService->setUseAiCategories(false);

// Re-enable AI categories
$importService->setUseAiCategories(true);

// Check availability
if ($importService->isAiCategoriesAvailable()) {
    echo "AI categories enabled and Ollama is available";
}
```

## Advanced Configuration

### Change Similarity Threshold

```php
$aiService = app(App\Services\AiCategoryService::class);

// Default is 80% - increase for more strict matching
$aiService->setSimilarityThreshold(90);

// Decrease for more aggressive category reuse
$aiService->setSimilarityThreshold(70);
```

### Use Different Model

```php
// Via CompanyProfile
$profile = App\Models\CompanyProfile::current();
$profile->ollama_model = 'llama3.1'; // or mistral, mixtral, etc.
$profile->save();

// Or directly in Ollama service (for testing)
$result = $ollama->generate($prompt, ['model' => 'mistral']);
```

### Batch Processing

```php
$aiService = app(App\Services\AiCategoryService::class);

$components = [
    ['description' => 'CAP CER 10UF...', 'manufacturer' => 'Murata'],
    ['description' => 'RES SMD 10K...', 'manufacturer' => 'Yageo'],
    // ... more components
];

// More efficient than calling generateCategoryFromDescription multiple times
$categories = $aiService->batchGenerateCategories($components);
```

## Troubleshooting

### "Ollama is not available"

```bash
# Check if Ollama is running
ps aux | grep ollama

# Start Ollama
ollama serve

# Test connection
curl http://localhost:11434/api/tags
```

### "Failed to parse JSON"

Try a different model:
```bash
ollama pull llama3.1
```

Then update CompanyProfile to use `llama3.1`.

### Slow Performance

```php
// Option 1: Use faster model
$profile->ollama_model = 'llama3.2'; // faster than llama3.1

// Option 2: Disable AI temporarily
$importService->setUseAiCategories(false);
```

### Wrong Categories

```php
// Adjust similarity threshold
$aiService->setSimilarityThreshold(85); // More strict

// Or manually correct and AI will learn to match better
```

## Running Tests

```bash
# Run all AI category tests
php artisan test --filter=AiCategoryGenerationTest

# Run specific test
php artisan test --filter=test_ollama_availability_check

# View detailed logs
tail -f storage/logs/laravel.log | grep "AI category"
```

## Integration with Import UI

The AI system works automatically with the existing Filament import interface:

1. Navigate to Components → Import
2. Upload CSV/Excel file
3. Select supplier (Mouser, DigiKey, etc.)
4. Click Import
5. **AI automatically generates categories** during import
6. View results - similar categories are reused, new ones created as needed

## Performance Notes

**With Ollama (AI enabled):**
- ~2-5 seconds per component
- Much better category accuracy
- Handles synonyms and language variations
- Prevents duplicate categories

**Without Ollama (fallback):**
- ~0.1 seconds per component
- Good keyword-based categorization
- May create more duplicate categories
- English/Italian synonyms not handled

## Examples of Generated Categories

Real-world examples from test imports:

| Description | Generated Category |
|------------|-------------------|
| CAP CER 10UF 16V X7R 0805 | Condensatori Ceramici MLCC |
| RES SMD 10K OHM 1% 1/8W 0805 | Resistenze SMD |
| GNSS MODULE GPS/GALILEO/GLONASS | Moduli GNSS/GPS |
| CONN FFC/FPC 40POS 0.5MM R/A | Connettori FFC/FPC |
| POWER INDUCTOR 10UH 8A SMD | Induttori di Potenza |
| IC MCU 32BIT ARM CORTEX-M4 | Microcontrollori ARM |
| DIODE SCHOTTKY 40V 1A SOD123 | Diodi Schottky |
| ANT GPS/GNSS CERAMIC 28DB GAIN | Antenne GPS/GNSS |

## Best Practices

1. **Group Similar Components**
   - Import capacitors together, resistors together, etc.
   - AI reuses categories more efficiently

2. **Include Manufacturer Data**
   - Helps AI understand component context
   - Improves category accuracy

3. **Review Generated Categories**
   - Check categories after first import
   - Merge similar categories if needed
   - AI learns from your category structure

4. **Monitor Logs**
   - Review `storage/logs/laravel.log` for AI decisions
   - Look for similarity scores and reasoning

5. **Adjust Threshold Based on Results**
   - Too many categories? Lower threshold (e.g., 75%)
   - Too much merging? Raise threshold (e.g., 85%)

## Support & Documentation

- Full documentation: `AI_CATEGORY_SYSTEM.md`
- Test suite: `tests/Feature/AiCategoryGenerationTest.php`
- Ollama docs: https://ollama.ai
- Laravel docs: https://laravel.com/docs

## Quick Reference Commands

```bash
# Start Ollama
ollama serve

# List models
ollama list

# Pull new model
ollama pull llama3.1

# Run migration
php artisan migrate

# Run tests
php artisan test --filter=AiCategoryGenerationTest

# Access tinker for testing
php artisan tinker

# View logs
tail -f storage/logs/laravel.log
```

---

**That's it!** You're ready to use AI-powered category generation in your component imports.
