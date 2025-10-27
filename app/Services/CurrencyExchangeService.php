<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CurrencyExchangeService
{
    /**
     * Frankfurter API base URL (free, no API key required, ECB data)
     */
    protected string $apiUrl = 'https://api.frankfurter.app';

    /**
     * Cache duration in seconds (24 hours - rates update once per day)
     */
    protected int $cacheDuration = 86400;

    /**
     * Default fallback rates if API is unavailable
     */
    protected array $fallbackRates = [
        'USD' => 0.92,  // 1 USD = 0.92 EUR (approximate)
        'GBP' => 1.17,  // 1 GBP = 1.17 EUR (approximate)
        'JPY' => 0.0063, // 1 JPY = 0.0063 EUR (approximate)
        'CNY' => 0.13,  // 1 CNY = 0.13 EUR (approximate)
        'CHF' => 1.05,  // 1 CHF = 1.05 EUR (approximate)
    ];

    /**
     * Get exchange rate from source currency to EUR
     *
     * @param string $fromCurrency Source currency code (USD, GBP, etc.)
     * @return float|null Exchange rate to EUR, or null if unavailable
     */
    public function getExchangeRate(string $fromCurrency): ?float
    {
        $fromCurrency = strtoupper($fromCurrency);

        // EUR to EUR is always 1.0
        if ($fromCurrency === 'EUR') {
            return 1.0;
        }

        // Try to get from cache first
        $cacheKey = "exchange_rate_{$fromCurrency}_EUR";
        $cachedRate = Cache::get($cacheKey);

        if ($cachedRate !== null) {
            Log::debug('Exchange rate from cache', [
                'from' => $fromCurrency,
                'to' => 'EUR',
                'rate' => $cachedRate
            ]);
            return $cachedRate;
        }

        // Fetch from API
        try {
            $rate = $this->fetchRateFromApi($fromCurrency);

            if ($rate !== null) {
                // Cache the rate
                Cache::put($cacheKey, $rate, $this->cacheDuration);

                Log::info('Exchange rate fetched from API', [
                    'from' => $fromCurrency,
                    'to' => 'EUR',
                    'rate' => $rate,
                    'cached_for' => $this->cacheDuration . ' seconds'
                ]);

                return $rate;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch exchange rate from API', [
                'from' => $fromCurrency,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to default rates
        $fallbackRate = $this->fallbackRates[$fromCurrency] ?? null;

        if ($fallbackRate !== null) {
            Log::warning('Using fallback exchange rate', [
                'from' => $fromCurrency,
                'to' => 'EUR',
                'rate' => $fallbackRate
            ]);

            // Cache fallback rate for a shorter time (1 hour)
            Cache::put($cacheKey, $fallbackRate, 3600);
        }

        return $fallbackRate;
    }

    /**
     * Fetch exchange rate from Frankfurter API
     *
     * @param string $fromCurrency
     * @return float|null
     */
    protected function fetchRateFromApi(string $fromCurrency): ?float
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->apiUrl}/latest", [
                    'from' => $fromCurrency,
                    'to' => 'EUR'
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Frankfurter returns: {"amount":1.0,"base":"USD","date":"2025-10-06","rates":{"EUR":0.92}}
                if (isset($data['rates']['EUR'])) {
                    return (float) $data['rates']['EUR'];
                }
            }

            Log::warning('API returned unsuccessful response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('API request failed', [
                'error' => $e->getMessage(),
                'from' => $fromCurrency
            ]);
            return null;
        }
    }

    /**
     * Convert amount from source currency to EUR
     *
     * @param float $amount Amount to convert
     * @param string $fromCurrency Source currency code
     * @return float|null Converted amount in EUR, or null if conversion failed
     */
    public function convertToEur(float $amount, string $fromCurrency): ?float
    {
        $rate = $this->getExchangeRate($fromCurrency);

        if ($rate === null) {
            Log::warning('Cannot convert amount - exchange rate unavailable', [
                'amount' => $amount,
                'from' => $fromCurrency
            ]);
            return null;
        }

        $convertedAmount = $amount * $rate;

        Log::debug('Currency conversion', [
            'original_amount' => $amount,
            'from' => $fromCurrency,
            'rate' => $rate,
            'converted_amount' => $convertedAmount,
            'to' => 'EUR'
        ]);

        return round($convertedAmount, 4);
    }

    /**
     * Detect currency from price string
     *
     * @param string $priceString Price string with potential currency symbol/code
     * @return string|null Currency code (USD, EUR, GBP, etc.) or null if not detected
     */
    public function detectCurrency(string $priceString): ?string
    {
        // Currency patterns
        $patterns = [
            '/\$/' => 'USD',
            '/USD/i' => 'USD',
            '/€/' => 'EUR',
            '/EUR/i' => 'EUR',
            '/£/' => 'GBP',
            '/GBP/i' => 'GBP',
            '/¥/' => 'JPY',
            '/JPY/i' => 'JPY',
            '/CNY/i' => 'CNY',
            '/CHF/i' => 'CHF',
        ];

        foreach ($patterns as $pattern => $currency) {
            if (preg_match($pattern, $priceString)) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * Clear cached exchange rates (useful for testing or manual refresh)
     *
     * @return void
     */
    public function clearCache(): void
    {
        $currencies = array_merge(['EUR'], array_keys($this->fallbackRates));

        foreach ($currencies as $currency) {
            Cache::forget("exchange_rate_{$currency}_EUR");
        }

        Log::info('Exchange rate cache cleared');
    }

    /**
     * Get all cached exchange rates
     *
     * @return array
     */
    public function getAllCachedRates(): array
    {
        $currencies = array_merge(['EUR'], array_keys($this->fallbackRates));
        $rates = [];

        foreach ($currencies as $currency) {
            $cacheKey = "exchange_rate_{$currency}_EUR";
            $rate = Cache::get($cacheKey);

            if ($rate !== null) {
                $rates[$currency] = $rate;
            }
        }

        return $rates;
    }

    /**
     * Update fallback rate for a currency
     *
     * @param string $currency Currency code
     * @param float $rate Exchange rate to EUR
     * @return void
     */
    public function setFallbackRate(string $currency, float $rate): void
    {
        $currency = strtoupper($currency);
        $this->fallbackRates[$currency] = $rate;

        Log::info('Fallback rate updated', [
            'currency' => $currency,
            'rate' => $rate
        ]);
    }
}
