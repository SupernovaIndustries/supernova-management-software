<?php

namespace App\Services\Suppliers;

use App\Models\Component;
use App\Models\Supplier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DigiKeyApiService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $accessToken;
    protected string $baseUrl;
    protected ?Supplier $supplier;

    public function __construct()
    {
        $this->supplier = Supplier::where('api_name', 'digikey')->first();
        
        if ($this->supplier && $this->supplier->api_credentials) {
            $this->clientId = $this->supplier->api_credentials['client_id'] ?? '';
            $this->clientSecret = $this->supplier->api_credentials['client_secret'] ?? '';
            $this->baseUrl = $this->supplier->api_settings['base_url'] ?? 'https://api.digikey.com';
            
            // Get or refresh access token
            $this->initializeAccessToken();
        }
    }

    /**
     * Initialize or refresh access token
     */
    protected function initializeAccessToken(): void
    {
        // In production, implement OAuth2 flow
        // For now, assume token is stored in api_credentials
        $this->accessToken = $this->supplier->api_credentials['access_token'] ?? '';
    }

    /**
     * Search for parts by keyword
     */
    public function searchParts(string $keyword, int $limit = 50): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->accessToken}",
                    'X-DIGIKEY-Client-Id' => $this->clientId,
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/Search/v3/Products/Keyword", [
                    'Keywords' => $keyword,
                    'RecordCount' => $limit,
                    'RecordStartPosition' => 0,
                    'Filters' => [
                        'InStock' => true,
                    ],
                    'Sort' => [
                        'SortByDigiKeyPartNumber' => false,
                        'DescendingOrder' => false,
                    ],
                    'RequestedQuantity' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->transformSearchResults($data['Products'] ?? []);
            }

            Log::error('DigiKey API search failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('DigiKey API exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get part details by DigiKey part number
     */
    public function getPartDetails(string $partNumber): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->accessToken}",
                    'X-DIGIKEY-Client-Id' => $this->clientId,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/Search/v3/Products/{$partNumber}");

            if ($response->successful()) {
                $data = $response->json();
                return $this->transformPartDetails($data);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('DigiKey API part details exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Add items to web order
     */
    public function addToWebOrder(array $items, string $customerReference): ?array
    {
        try {
            $lineItems = array_map(function ($item) {
                return [
                    'DigiKeyPartNumber' => $item['part_number'],
                    'Quantity' => $item['quantity'],
                    'CustomerReference' => $item['reference'] ?? '',
                ];
            }, $items);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->accessToken}",
                    'X-DIGIKEY-Client-Id' => $this->clientId,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/Ordering/v3/Orders/AddToCart", [
                    'CustomerReference' => $customerReference,
                    'LineItems' => $lineItems,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('DigiKey API add to cart failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('DigiKey API order exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Transform search results to standard format
     */
    protected function transformSearchResults(array $products): array
    {
        return array_map(function ($product) {
            return [
                'supplier' => 'DigiKey',
                'part_number' => $product['DigiKeyPartNumber'] ?? '',
                'manufacturer_part_number' => $product['ManufacturerPartNumber'] ?? '',
                'manufacturer' => $product['Manufacturer']['Value'] ?? '',
                'description' => $product['ProductDescription'] ?? '',
                'datasheet_url' => $product['PrimaryDatasheet'] ?? '',
                'image_url' => $product['PrimaryPhoto'] ?? '',
                'stock' => $product['QuantityAvailable'] ?? 0,
                'min_quantity' => $product['MinimumOrderQuantity'] ?? 1,
                'price_breaks' => $this->transformPriceBreaks($product['StandardPricing'] ?? []),
                'category' => $product['Category']['Value'] ?? '',
                'lead_time' => $product['LeadStatus'] ?? '',
            ];
        }, $products);
    }

    /**
     * Transform part details
     */
    protected function transformPartDetails(array $product): array
    {
        return [
            'supplier' => 'DigiKey',
            'part_number' => $product['DigiKeyPartNumber'] ?? '',
            'manufacturer_part_number' => $product['ManufacturerPartNumber'] ?? '',
            'manufacturer' => $product['Manufacturer']['Value'] ?? '',
            'description' => $product['ProductDescription'] ?? '',
            'detailed_description' => $product['DetailedDescription'] ?? '',
            'datasheet_url' => $product['PrimaryDatasheet'] ?? '',
            'image_url' => $product['PrimaryPhoto'] ?? '',
            'stock' => $product['QuantityAvailable'] ?? 0,
            'min_quantity' => $product['MinimumOrderQuantity'] ?? 1,
            'order_multiple' => $product['OrderMultiple'] ?? 1,
            'price_breaks' => $this->transformPriceBreaks($product['StandardPricing'] ?? []),
            'category' => $product['Category']['Value'] ?? '',
            'family' => $product['Family']['Value'] ?? '',
            'series' => $product['Series']['Value'] ?? '',
            'packaging' => $product['Packaging']['Value'] ?? '',
            'lead_time' => $product['LeadStatus'] ?? '',
            'rohs_status' => $product['RoHSStatus'] ?? '',
            'attributes' => $this->transformParameters($product['Parameters'] ?? []),
        ];
    }

    /**
     * Transform price breaks
     */
    protected function transformPriceBreaks(array $pricing): array
    {
        return array_map(function ($price) {
            return [
                'quantity' => (int) $price['BreakQuantity'],
                'price' => (float) $price['UnitPrice'],
                'currency' => 'EUR', // DigiKey returns in account currency
            ];
        }, $pricing);
    }

    /**
     * Transform product parameters to attributes
     */
    protected function transformParameters(array $parameters): array
    {
        $result = [];
        
        foreach ($parameters as $param) {
            $result[$param['Parameter']] = $param['Value'];
        }
        
        return $result;
    }

    /**
     * Import component from DigiKey data
     */
    public function importComponent(array $partData, int $categoryId): ?Component
    {
        try {
            $lowestPrice = $this->getLowestPrice($partData['price_breaks']);
            
            $component = Component::updateOrCreate(
                ['manufacturer_part_number' => $partData['manufacturer_part_number']],
                [
                    'sku' => 'DK-' . $partData['part_number'],
                    'name' => $partData['description'],
                    'description' => $partData['detailed_description'] ?? $partData['description'],
                    'category_id' => $categoryId,
                    'manufacturer' => $partData['manufacturer'],
                    'package' => $partData['packaging'] ?? null,
                    'datasheet_url' => $partData['datasheet_url'],
                    'image_url' => $partData['image_url'],
                    'unit_price' => $lowestPrice,
                    'specifications' => $partData['attributes'] ?? [],
                    'supplier_links' => [
                        'digikey' => [
                            'part_number' => $partData['part_number'],
                            'url' => "https://www.digikey.it/product-detail/en/{$partData['part_number']}",
                        ]
                    ],
                    'status' => 'active',
                ]
            );

            return $component;
        } catch (\Exception $e) {
            Log::error('Failed to import DigiKey component', [
                'error' => $e->getMessage(),
                'part_number' => $partData['part_number']
            ]);
            return null;
        }
    }

    /**
     * Get lowest price from price breaks
     */
    protected function getLowestPrice(array $priceBreaks): float
    {
        if (empty($priceBreaks)) {
            return 0;
        }

        $prices = array_column($priceBreaks, 'price');
        return min($prices);
    }
}