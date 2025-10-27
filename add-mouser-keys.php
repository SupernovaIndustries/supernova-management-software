<?php

// Script per aggiungere le chiavi API Mouser
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Supplier;

echo "🔑 Configurazione Mouser API Keys\n";
echo "==================================\n";

// API Keys fornite dall'utente
$mouseSearchKey = '40db9f08-8d80-4f5c-bd57-8b11f70fb443';
$mouseOrderKey = '898fe9eb-311d-4fd9-842f-86a4a0660ad1';
$mouseCartKey = '898fe9eb-311d-4fd9-842f-86a4a0660ad1'; // Stessa di Order

// Crea/aggiorna Mouser
$mouser = Supplier::updateOrCreate(
    ['api_name' => 'mouser'],
    [
        'name' => 'Mouser Electronics',
        'code' => 'MOUSER',
        'api_name' => 'mouser',
        'website' => 'https://www.mouser.com',
        'email' => 'support@mouser.com',
        'api_enabled' => true,
        'api_credentials' => json_encode([
            'search_api_key' => $mouseSearchKey,
            'order_api_key' => $mouseOrderKey,
            'cart_api_key' => $mouseCartKey
        ]),
        'api_settings' => [
            'endpoints' => [
                'search' => 'https://api.mouser.com/api/v1/search/keyword',
                'part_detail' => 'https://api.mouser.com/api/v1/search/partnumber',
                'order' => 'https://api.mouser.com/api/v1.0/order',
                'cart' => 'https://api.mouser.com/api/v1.0/cart'
            ],
            'rate_limits' => [
                'search' => 1000, // requests per day
                'order' => 100,
                'cart' => 100
            ],
            'timeout' => 30,
            'retry_attempts' => 3,
            'supported_currencies' => ['USD', 'EUR']
        ],
        'is_active' => true
    ]
);

echo "✅ Mouser Electronics configurato!\n";
echo "===================================\n";
echo "🔍 Supplier ID: " . $mouser->id . "\n";
echo "🔑 Search API: " . substr($mouseSearchKey, 0, 8) . "...\n";
echo "🔑 Order API: " . substr($mouseOrderKey, 0, 8) . "...\n";
echo "🔑 Cart API: " . substr($mouseCartKey, 0, 8) . "...\n";
echo "🌐 Status: " . ($mouser->api_enabled ? 'ATTIVO' : 'INATTIVO') . "\n\n";

echo "🚀 Ora puoi:\n";
echo "   1. Andare su http://localhost:8000/admin/suppliers\n";
echo "   2. Vedere il fornitore Mouser configurato\n";
echo "   3. Testare l'importazione componenti\n";