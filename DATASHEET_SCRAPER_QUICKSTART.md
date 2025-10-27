# Datasheet Scraper - Quick Start Guide

## TL;DR

The DatasheetScraperService automatically extracts technical specifications (value, tolerance, voltage, package, etc.) from electronic components using a 3-tier strategy: **Supplier APIs → PDF Datasheet → Description Regex**.

## Quick Test

```bash
# 1. Ensure Ollama is running (for AI PDF extraction)
ollama serve

# 2. Run test script
php scripts/test_datasheet_scraper.php
```

## Usage Examples

### Example 1: Auto-Enrichment During Import (RECOMMENDED)

When you import components, specifications are automatically extracted:

```php
$importService = app(ComponentImportService::class);

// Import Excel/CSV - auto-enrichment happens automatically!
$results = $importService->importFromExcel(
    '/path/to/mouser_order.xlsx',
    'mouser'
);

// All imported components now have technical specs populated
```

**That's it!** No additional code needed. Specifications are extracted and saved automatically during import.

### Example 2: Manual Extraction for Existing Component

```php
use App\Services\DatasheetScraperService;
use App\Models\Component;

$scraper = app(DatasheetScraperService::class);
$component = Component::find(123);

// Extract specs
$specs = $scraper->extractSpecifications($component);

// Update component
$component->update($specs);
```

### Example 3: Batch Enrich Existing Components

```php
use App\Services\DatasheetScraperService;
use App\Models\Component;

$scraper = app(DatasheetScraperService::class);

Component::whereNull('package_type')->chunk(50, function ($components) use ($scraper) {
    foreach ($components as $component) {
        $specs = $scraper->extractSpecifications($component);
        if (!empty($specs)) {
            $component->update($specs);
        }
    }
});
```

## Configuration

### Enable/Disable During Import

```php
$importService = app(ComponentImportService::class);

// Disable auto-enrichment
$importService->setUseDatasheetScraper(false);

// Re-enable
$importService->setUseDatasheetScraper(true);
```

### Adjust Completeness Threshold

```php
$scraper = app(DatasheetScraperService::class);

// Set higher threshold (will try harder to extract specs)
$scraper->setCompletenessThreshold(80); // Default is 60%
```

### Disable Service Completely

```php
$scraper = app(DatasheetScraperService::class);
$scraper->setEnabled(false);
```

## What Gets Extracted?

The service populates these Component model fields:

| Field | Example Values | Priority |
|-------|---------------|----------|
| `value` | "10uF", "100R", "1nH" | ⭐⭐⭐ |
| `tolerance` | "±5%", "±1%" | ⭐⭐⭐ |
| `voltage_rating` | "16V", "50V" | ⭐⭐⭐ |
| `package_type` | "0805", "SOT-23" | ⭐⭐⭐ |
| `mounting_type` | "SMD", "Through Hole" | ⭐⭐⭐ |
| `power_rating` | "0.25W", "1/4W" | ⭐⭐ |
| `current_rating` | "2A", "500mA" | ⭐⭐ |
| `operating_temperature` | "-40°C ~ +85°C" | ⭐⭐ |
| `dielectric` | "X7R", "X5R", "C0G" | ⭐ |
| `case_style` | "LQFP-100", "SOIC-8" | ⭐ |

## How It Works

```
Step 1: Try Mouser/DigiKey API
  ↓ (if fails or incomplete)
Step 2: Try PDF Datasheet with AI
  ↓ (if fails or incomplete)
Step 3: Fallback to Description Regex
  ↓
Result: Merged specifications
```

**Speed:**
- API: ~500ms (cached: 50ms)
- PDF: ~8-25s (cached: 100ms)
- Regex: <100ms

**Cache TTL:**
- API responses: 24 hours
- PDF text: 7 days

## Troubleshooting

### "No specifications extracted"

**Causes:**
1. Component missing supplier links (mouser/digikey part numbers)
2. No datasheet URL available
3. Description doesn't contain parseable specs

**Solutions:**
1. Add supplier part numbers to `supplier_links` field
2. Manually set `datasheet_url`
3. Improve component description

### "OllamaService not available"

**Cause:** Ollama not running (only needed for PDF extraction)

**Solution:**
```bash
ollama serve
```

**Note:** PDF extraction is optional. API and regex extraction will still work.

### Slow performance

**Causes:**
- PDF extraction takes time (8-25s per component)
- No caching configured

**Solutions:**
1. Temporarily disable PDF extraction:
   ```php
   // In DatasheetScraperService, set:
   protected int $pdfDownloadTimeout = 0; // Skips PDF
   ```

2. Configure Redis for better caching:
   ```env
   CACHE_DRIVER=redis
   ```

3. Process in smaller batches

## Testing

### Unit Tests
```bash
php artisan test tests/Feature/DatasheetScraperServiceTest.php
```

### Interactive Test
```bash
php scripts/test_datasheet_scraper.php
```

### Manual Component Test
```bash
php artisan tinker
```

```php
$scraper = app(\App\Services\DatasheetScraperService::class);
$component = \App\Models\Component::where('manufacturer_part_number', 'CL10A226MP8NUNE')->first();
$specs = $scraper->extractSpecifications($component);
print_r($specs);
```

## Performance Tips

### For Large Imports (>1000 components)

1. **Use caching:** Configure Redis
2. **API only:** Skip PDF extraction for speed
3. **Batch processing:** Import in chunks of 100-500
4. **Monitor logs:** Check extraction success rate

```bash
tail -f storage/logs/laravel.log | grep "specification extraction"
```

## Common Use Cases

### Use Case 1: Import + Auto-Enrich (Most Common)

**When:** Importing components from Mouser/DigiKey Excel/CSV
**How:** Already works automatically!
```php
$importService->importFromExcel('mouser_order.xlsx', 'mouser');
```

### Use Case 2: Enrich Existing Database

**When:** You have existing components without specs
**How:** Run batch enrichment
```php
Component::chunk(100, function ($components) {
    foreach ($components as $component) {
        $scraper->extractSpecifications($component)->save();
    }
});
```

### Use Case 3: Manual Component Entry

**When:** Adding component manually in Filament
**How:** Service runs automatically via import service
**Alternative:** Create Filament action for manual trigger

### Use Case 4: BOM Analysis

**When:** Need complete specs for cost/availability analysis
**How:** Ensure all BOM components have specs
```php
$bomComponents = Bom::find($bomId)->components;
foreach ($bomComponents as $component) {
    if (!$component->package_type) {
        $scraper->extractSpecifications($component)->save();
    }
}
```

## API Reference

### DatasheetScraperService

```php
// Main extraction method
extractSpecifications(Component $component): array

// Configuration
setEnabled(bool $enabled): self
isEnabled(): bool
setCompletenessThreshold(int $threshold): self
```

### ComponentImportService

```php
// Configuration
setUseDatasheetScraper(bool $enabled): self
isDatasheetScraperAvailable(): bool
```

## Environment Variables

```env
# Ollama (optional - for PDF extraction)
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=llama3.1:8b

# Mouser API (already configured)
MOUSER_API_KEY=your_key

# DigiKey API (already configured)
DIGIKEY_CLIENT_ID=your_client_id
DIGIKEY_CLIENT_SECRET=your_secret
```

## Next Steps

1. ✅ Run test script: `php scripts/test_datasheet_scraper.php`
2. ✅ Import sample file and verify auto-enrichment
3. ✅ Check logs for extraction success rate
4. ✅ Configure Redis caching (optional, for production)
5. ✅ Fine-tune completeness threshold if needed

## Support

**Full Documentation:** See `DATASHEET_SCRAPER_IMPLEMENTATION.md`

**Logs Location:** `storage/logs/laravel.log`

**Search Logs:**
```bash
grep "specification extraction" storage/logs/laravel.log
grep "Component enriched" storage/logs/laravel.log
```

---

**Status:** ✅ Production Ready
**Last Updated:** October 8, 2025
