<?php

namespace App\Services\Suppliers;

use App\Models\Component;
use App\Models\Supplier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MouserApiService
{
    protected string $searchApiKey;
    protected string $orderApiKey;
    protected string $cartApiKey;
    protected array $endpoints;
    protected ?Supplier $supplier;

    public function __construct()
    {
        $this->supplier = Supplier::where('api_name', 'mouser')->first();
        
        if ($this->supplier && $this->supplier->api_credentials) {
            $credentials = $this->supplier->api_credentials;
            $this->searchApiKey = $credentials['search_api_key'] ?? '';
            $this->orderApiKey = $credentials['order_api_key'] ?? '';
            $this->cartApiKey = $credentials['cart_api_key'] ?? '';
            
            $settings = $this->supplier->api_settings ?? [];
            $this->endpoints = $settings['endpoints'] ?? [
                'search' => 'https://api.mouser.com/api/v1/search/keyword',
                'part_detail' => 'https://api.mouser.com/api/v1/search/partnumber',
                'order' => 'https://api.mouser.com/api/v1.0/order',
                'cart' => 'https://api.mouser.com/api/v1.0/cart'
            ];
        }
    }

    /**
     * Search for parts by keyword
     */
    public function searchParts(string $keyword, int $records = 50): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoints['search'] . "?apiKey={$this->searchApiKey}", [
                    'SearchByKeywordRequest' => [
                        'keyword' => $keyword,
                        'records' => $records,
                        'startingRecord' => 0,
                        'searchOptions' => 'InStock',
                        'searchWithYourSignUpLanguage' => 'N',
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->transformSearchResults($data['SearchResults'] ?? []);
            }

            Log::error('Mouser API search failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Mouser API exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get part details by Mouser part number
     */
    public function getPartDetails(string $partNumber): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($this->endpoints['part_detail'], [
                    'apiKey' => $this->searchApiKey,
                    'mouserPartNumber' => $partNumber,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $parts = $data['SearchResults']['Parts'] ?? [];
                
                if (!empty($parts)) {
                    return $this->transformPartDetails($parts[0]);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Mouser API part details exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get web order details by order number/cart key
     */
    public function getWebOrderDetails(string $webOrderNumber): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($this->endpoints['order'] . "/history/{$webOrderNumber}", [
                    'apiKey' => $this->orderApiKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['OrderHistory'] ?? null;
            }

            // Try alternative endpoint for cart details
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($this->endpoints['cart'] . "/{$webOrderNumber}", [
                    'apiKey' => $this->cartApiKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['CartDetails'] ?? null;
            }

            Log::error('Mouser API get web order failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'order_number' => $webOrderNumber
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Mouser API web order exception', [
                'error' => $e->getMessage(),
                'order_number' => $webOrderNumber
            ]);
            return null;
        }
    }

    /**
     * Create web order cart
     */
    public function createWebOrder(array $items, string $customerOrderNumber): ?string
    {
        try {
            $cartItems = array_map(function ($item) {
                return [
                    'MouserPartNumber' => $item['part_number'],
                    'Quantity' => $item['quantity'],
                    'CustomerPartNumber' => $item['customer_part_number'] ?? '',
                ];
            }, $items);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoints['cart'] . "?apiKey={$this->cartApiKey}", [
                    'CartRequest' => [
                        'CustomerOrderNumber' => $customerOrderNumber,
                        'CartItems' => $cartItems,
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['CartKey'] ?? null;
            }

            Log::error('Mouser API create order failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Mouser API order exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Transform search results to standard format
     */
    protected function transformSearchResults(array $results): array
    {
        $parts = $results['Parts'] ?? [];
        
        return array_map(function ($part) {
            return [
                'supplier' => 'Mouser',
                'part_number' => $part['MouserPartNumber'] ?? '',
                'manufacturer_part_number' => $part['ManufacturerPartNumber'] ?? '',
                'manufacturer' => $part['Manufacturer'] ?? '',
                'description' => $part['Description'] ?? '',
                'datasheet_url' => $part['DataSheetUrl'] ?? '',
                'image_url' => $part['ImagePath'] ?? '',
                'stock' => $part['Availability'] ?? 'Unknown',
                'min_quantity' => $part['Min'] ?? 1,
                'price_breaks' => $this->transformPriceBreaks($part['PriceBreaks'] ?? []),
                'category' => $part['Category'] ?? '',
                'lead_time' => $part['LeadTime'] ?? '',
            ];
        }, $parts);
    }

    /**
     * Transform part details
     */
    protected function transformPartDetails(array $part): array
    {
        return [
            'supplier' => 'Mouser',
            'part_number' => $part['MouserPartNumber'] ?? '',
            'manufacturer_part_number' => $part['ManufacturerPartNumber'] ?? '',
            'manufacturer' => $part['Manufacturer'] ?? '',
            'description' => $part['Description'] ?? '',
            'datasheet_url' => $part['DataSheetUrl'] ?? '',
            'image_url' => $part['ImagePath'] ?? '',
            'stock' => $part['Availability'] ?? 'Unknown',
            'min_quantity' => $part['Min'] ?? 1,
            'mult' => $part['Mult'] ?? 1,
            'price_breaks' => $this->transformPriceBreaks($part['PriceBreaks'] ?? []),
            'category' => $part['Category'] ?? '',
            'lead_time' => $part['LeadTime'] ?? '',
            'lifecycle_status' => $part['LifecycleStatus'] ?? '',
            'rohs_status' => $part['ROHSStatus'] ?? '',
            'attributes' => $this->transformAttributes($part['ProductAttributes'] ?? []),
        ];
    }

    /**
     * Transform price breaks
     */
    protected function transformPriceBreaks(array $priceBreaks): array
    {
        return array_map(function ($break) {
            return [
                'quantity' => (int) $break['Quantity'],
                'price' => (float) str_replace(['€', ','], '', $break['Price'] ?? '0'),
                'currency' => $break['Currency'] ?? 'EUR',
            ];
        }, $priceBreaks);
    }

    /**
     * Transform product attributes
     */
    protected function transformAttributes(array $attributes): array
    {
        $result = [];
        
        foreach ($attributes as $attr) {
            $result[$attr['AttributeName']] = $attr['AttributeValue'];
        }
        
        return $result;
    }

    /**
     * Import component from Mouser data
     */
    public function importComponent(array $partData, int $categoryId): ?Component
    {
        try {
            $lowestPrice = $this->getLowestPrice($partData['price_breaks']);
            
            $component = Component::updateOrCreate(
                ['manufacturer_part_number' => $partData['manufacturer_part_number']],
                [
                    'sku' => 'MOU-' . $partData['part_number'],
                    'name' => $partData['description'],
                    'description' => $partData['description'],
                    'category_id' => $categoryId,
                    'manufacturer' => $partData['manufacturer'],
                    'datasheet_url' => $partData['datasheet_url'],
                    'image_url' => $partData['image_url'],
                    'unit_price' => $lowestPrice,
                    'specifications' => $partData['attributes'] ?? [],
                    'supplier_links' => [
                        'mouser' => [
                            'part_number' => $partData['part_number'],
                            'url' => "https://www.mouser.it/ProductDetail/{$partData['part_number']}",
                        ]
                    ],
                    'status' => 'active',
                ]
            );

            return $component;
        } catch (\Exception $e) {
            Log::error('Failed to import Mouser component', [
                'error' => $e->getMessage(),
                'part_number' => $partData['part_number']
            ]);
            return null;
        }
    }

    /**
     * Import components from web order
     */
    public function importComponentsFromWebOrder(string $webOrderNumber): array
    {
        $results = [
            'imported' => [],
            'skipped' => [],
            'errors' => []
        ];

        try {
            $orderDetails = $this->getWebOrderDetails($webOrderNumber);
            
            if (!$orderDetails) {
                $results['errors'][] = "Web Order {$webOrderNumber} non trovato";
                return $results;
            }

            // Extract part numbers from order
            $orderItems = $orderDetails['OrderItems'] ?? $orderDetails['CartItems'] ?? [];
            
            if (empty($orderItems)) {
                $results['errors'][] = "Nessun componente trovato nell'ordine";
                return $results;
            }

            foreach ($orderItems as $item) {
                $partNumber = $item['MouserPartNumber'] ?? $item['PartNumber'] ?? null;
                
                if (!$partNumber) {
                    $results['skipped'][] = 'Part number mancante nell\'item';
                    continue;
                }

                // Get detailed part information
                $partDetails = $this->getPartDetails($partNumber);
                
                if (!$partDetails) {
                    $results['skipped'][] = $partNumber . ' (dettagli non trovati)';
                    continue;
                }

                // Determine category automatically based on Mouser category
                $categoryId = $this->determineCategoryFromMouserData($partDetails);

                // Import the component
                $component = $this->importComponent($partDetails, $categoryId);
                
                if ($component) {
                    $results['imported'][] = [
                        'component' => $component,
                        'part_number' => $partNumber,
                        'quantity' => $item['Quantity'] ?? 1
                    ];
                } else {
                    $results['skipped'][] = $partNumber . ' (errore import)';
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Failed to import components from web order', [
                'error' => $e->getMessage(),
                'web_order' => $webOrderNumber
            ]);
        }

        return $results;
    }

    /**
     * Automatically determine category from Mouser data
     */
    protected function determineCategoryFromMouserData(array $partData): int
    {
        $mouserCategory = strtolower($partData['category'] ?? '');
        
        // Map Mouser categories to our categories
        $categoryMappings = [
            'integrated circuits' => 'ICs',
            'semiconductors' => 'Semiconductors',
            'passive components' => 'Passivi',
            'connectors' => 'Connettori',
            'electromechanical' => 'Elettromeccanici',
            'sensors' => 'Sensori',
            'test & measurement' => 'Strumenti',
            'power supplies' => 'Alimentatori',
            'capacitors' => 'Condensatori',
            'resistors' => 'Resistori',
            'inductors' => 'Induttori',
            'crystals' => 'Cristalli',
        ];

        foreach ($categoryMappings as $mouserCat => $ourCat) {
            if (str_contains($mouserCategory, $mouserCat)) {
                $category = \App\Models\Category::firstOrCreate(['name' => $ourCat]);
                return $category->id;
            }
        }

        // Default category
        $defaultCategory = \App\Models\Category::firstOrCreate(['name' => 'Componenti Generici']);
        return $defaultCategory->id;
    }

    /**
     * Get lowest price from price breaks
     */
    protected function getLowestPrice(array $priceBreaks): float
    {
        if (empty($priceBreaks)) {
            return 0;
        }

        // Usually the highest quantity has the lowest price
        $prices = array_column($priceBreaks, 'price');
        return min($prices);
    }
}