<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test cases
$testPrices = [
    // USD prices
    '$10.50' => 'USD',
    'USD 25.99' => 'USD',
    '$1,234.56' => 'USD',
    '10.50 USD' => 'USD',

    // EUR prices (should not convert)
    '€15.75' => 'EUR',
    'EUR 20.00' => 'EUR',
    '12,50 €' => 'EUR',

    // GBP prices
    '£8.99' => 'GBP',
    'GBP 45.00' => 'GBP',

    // Other formats
    '19.99' => null, // No currency symbol
    '100' => null,   // No currency symbol
];

echo "=== Currency Conversion Test ===\n\n";

$currencyService = app(App\Services\CurrencyExchangeService::class);

// Display current exchange rates
echo "Current Exchange Rates (cached or from API):\n";
echo str_repeat('-', 60) . "\n";

$currencies = ['USD', 'GBP', 'JPY', 'CHF', 'CNY'];
foreach ($currencies as $currency) {
    $rate = $currencyService->getExchangeRate($currency);
    if ($rate !== null) {
        echo sprintf("1 %s = %.4f EUR\n", $currency, $rate);
    } else {
        echo sprintf("1 %s = Rate not available\n", $currency);
    }
}

echo "\n" . str_repeat('=', 60) . "\n\n";

// Test currency detection and conversion
echo "Testing Currency Detection & Conversion:\n";
echo str_repeat('-', 60) . "\n";

foreach ($testPrices as $priceString => $expectedCurrency) {
    $detectedCurrency = $currencyService->detectCurrency($priceString);

    // Extract numeric value for conversion test
    $numericValue = (float) preg_replace('/[^0-9.,]/', '', $priceString);

    // Handle decimal separator
    if (substr_count($priceString, ',') === 1 && substr_count($priceString, '.') === 0) {
        $numericValue = (float) str_replace(',', '.', preg_replace('/[^0-9,]/', '', $priceString));
    } elseif (substr_count($priceString, ',') > 1 || (substr_count($priceString, '.') > 1)) {
        // Thousands separator
        $cleaned = str_replace([',', '.'], ['', ''], $priceString);
        if (preg_match('/(\d+)[,.](\d{2})$/', $priceString, $matches)) {
            $numericValue = (float)($matches[1] . '.' . $matches[2]);
        }
    }

    $converted = null;
    if ($detectedCurrency && $detectedCurrency !== 'EUR') {
        $converted = $currencyService->convertToEur($numericValue, $detectedCurrency);
    }

    echo sprintf(
        "%-20s | Detected: %-4s | Expected: %-4s | %s\n",
        $priceString,
        $detectedCurrency ?: 'NONE',
        $expectedCurrency ?: 'NONE',
        $detectedCurrency === $expectedCurrency ? '✓' : '✗'
    );

    if ($converted !== null) {
        echo sprintf(
            "  → Conversion: %.2f %s = %.4f EUR\n",
            $numericValue,
            $detectedCurrency,
            $converted
        );
    }

    echo "\n";
}

echo str_repeat('=', 60) . "\n\n";

// Test ComponentImportService integration
echo "Testing ComponentImportService Integration:\n";
echo str_repeat('-', 60) . "\n";

$importService = app(App\Services\ComponentImportService::class);

// Use reflection to test the protected parsePrice method
$reflector = new ReflectionClass($importService);
$parsePriceMethod = $reflector->getMethod('parsePrice');
$parsePriceMethod->setAccessible(true);

$testPricesForImport = [
    '$5.50' => 'Should convert from USD to EUR',
    '€5.50' => 'Should keep EUR as is',
    '£10.00' => 'Should convert from GBP to EUR',
    'USD 25.99' => 'Should convert from USD to EUR',
    '$1,250.99' => 'Should handle thousands separator and convert',
];

foreach ($testPricesForImport as $price => $description) {
    $result = $parsePriceMethod->invoke($importService, $price);
    echo sprintf(
        "%-20s → %.4f EUR | %s\n",
        $price,
        $result,
        $description
    );
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Test completed!\n";
echo "\nNote: Check logs for detailed conversion information:\n";
echo "  tail -f storage/logs/laravel.log\n";
