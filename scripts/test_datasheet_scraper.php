#!/usr/bin/env php
<?php

/**
 * Test script for DatasheetScraperService
 *
 * This script tests specification extraction with real component MPNs.
 *
 * Usage:
 *   php scripts/test_datasheet_scraper.php
 *
 * Make sure Ollama is running for AI-powered PDF extraction:
 *   ollama serve
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\DatasheetScraperService;
use App\Models\Component;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=== Datasheet Scraper Service Test ===\n\n";

// Test components with real MPNs
$testComponents = [
    [
        'name' => 'Capacitor - Samsung 22uF',
        'mpn' => 'CL10A226MP8NUNE',
        'manufacturer' => 'Samsung',
        'description' => 'CAP CER 22UF 10V X5R 0603',
        'supplier_links' => [
            'mouser' => ['part_number' => '187-CL10A226MP8NUNE']
        ],
        'expected_specs' => ['value', 'voltage_rating', 'package_type', 'mounting_type', 'dielectric']
    ],
    [
        'name' => 'Resistor - Vishay 1K',
        'mpn' => 'CRCW08051K00FKEA',
        'manufacturer' => 'Vishay',
        'description' => 'RES SMD 1K OHM 1% 1/8W 0805',
        'supplier_links' => [
            'mouser' => ['part_number' => '71-CRCW08051K00FKEA']
        ],
        'expected_specs' => ['value', 'tolerance', 'power_rating', 'package_type', 'mounting_type']
    ],
    [
        'name' => 'Microcontroller - STM32',
        'mpn' => 'STM32F407VGT6',
        'manufacturer' => 'STMicroelectronics',
        'description' => 'ARM Cortex-M4 MCU 168MHz 1MB Flash LQFP-100',
        'supplier_links' => [
            'mouser' => ['part_number' => '511-STM32F407VGT6']
        ],
        'expected_specs' => ['case_style', 'mounting_type', 'operating_temperature']
    ],
];

// Initialize service
$scraperService = app(DatasheetScraperService::class);

echo "Datasheet Scraper Status:\n";
echo "  Enabled: " . ($scraperService->isEnabled() ? 'YES' : 'NO') . "\n";
echo "  Ollama Available: " . (app(\App\Services\OllamaService::class)->isAvailable() ? 'YES' : 'NO') . "\n";
echo "\n";

// Test each component
foreach ($testComponents as $index => $testData) {
    echo "---------------------------------------------------\n";
    echo "Test " . ($index + 1) . ": " . $testData['name'] . "\n";
    echo "MPN: " . $testData['mpn'] . "\n";
    echo "---------------------------------------------------\n\n";

    // Find or create category
    $category = Category::firstOrCreate(['name' => 'Test Components']);

    // Create test component
    $component = Component::updateOrCreate(
        ['manufacturer_part_number' => $testData['mpn']],
        [
            'sku' => 'TEST-' . $testData['mpn'],
            'name' => $testData['name'],
            'description' => $testData['description'],
            'manufacturer' => $testData['manufacturer'],
            'category_id' => $category->id,
            'supplier_links' => $testData['supplier_links'],
            'status' => 'active',
        ]
    );

    echo "Component Created: ID {$component->id}\n\n";

    // Extract specifications
    echo "Extracting specifications...\n";
    $startTime = microtime(true);

    $specs = $scraperService->extractSpecifications($component);

    $duration = round(microtime(true) - $startTime, 2);
    echo "Extraction completed in {$duration} seconds\n\n";

    // Display results
    if (empty($specs)) {
        echo "❌ No specifications extracted\n\n";
    } else {
        echo "✅ Specifications extracted:\n";
        foreach ($specs as $field => $value) {
            if (!empty($value)) {
                echo "  - {$field}: {$value}\n";
            }
        }
        echo "\n";

        // Check expected specs
        echo "Expected fields check:\n";
        foreach ($testData['expected_specs'] as $expectedField) {
            $found = !empty($specs[$expectedField]);
            $status = $found ? '✓' : '✗';
            $value = $found ? $specs[$expectedField] : 'NOT FOUND';
            echo "  {$status} {$expectedField}: {$value}\n";
        }
        echo "\n";

        // Calculate completeness
        $reflection = new \ReflectionClass($scraperService);
        $method = $reflection->getMethod('calculateCompleteness');
        $method->setAccessible(true);
        $completeness = $method->invoke($scraperService, $specs);

        echo "Completeness: {$completeness}%\n";

        // Update component with specs
        $component->update($specs);
        echo "Component updated with specifications\n\n";
    }
}

echo "---------------------------------------------------\n";
echo "Test Summary\n";
echo "---------------------------------------------------\n\n";

echo "All tests completed!\n\n";

echo "To verify results in database:\n";
echo "  php artisan tinker\n";
echo "  Component::whereIn('manufacturer_part_number', [";
foreach ($testComponents as $test) {
    echo "'{$test['mpn']}', ";
}
echo "])->get(['manufacturer_part_number', 'value', 'tolerance', 'voltage_rating', 'package_type', 'mounting_type'])\n";
echo "\n";

// Performance statistics
echo "Performance Notes:\n";
echo "  - API extraction: Very fast (< 1s with caching)\n";
echo "  - PDF extraction: Slower (5-30s depending on PDF size and AI model)\n";
echo "  - Description fallback: Very fast (< 0.1s)\n";
echo "\n";

echo "Next Steps:\n";
echo "  1. Check Laravel logs for detailed extraction process\n";
echo "  2. Verify cached API responses (24 hours TTL)\n";
echo "  3. Test with your own components by modifying this script\n";
echo "  4. Enable/disable scraper in ComponentImportService if needed\n";
echo "\n";
