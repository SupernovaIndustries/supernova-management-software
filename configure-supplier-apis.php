<?php

// Script per configurare correttamente le API di Mouser (3 diverse) e DigiKey
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Supplier;

echo "🔑 Configurazione API Keys Fornitori Elettronici\n";
echo "===============================================\n\n";

// MOUSER - 3 API diverse
echo "📦 MOUSER ELECTRONICS - 3 API Keys richieste:\n";
echo "==============================================\n";
echo "Mouser ha 3 API separate che richiedono chiavi diverse:\n";
echo "1. Search API - Per ricerca componenti\n";
echo "2. Order API - Per gestione ordini\n"; 
echo "3. Cart API - Per gestione carrello\n\n";

echo "Inserisci Mouser Search API Key: ";
$mouseSearchKey = trim(fgets(STDIN));

echo "Inserisci Mouser Order API Key: ";
$mouseOrderKey = trim(fgets(STDIN));

echo "Inserisci Mouser Cart API Key: ";
$mouseCartKey = trim(fgets(STDIN));

// DIGIKEY
echo "\n📦 DIGIKEY ELECTRONICS - OAuth2:\n";
echo "=================================\n";
echo "DigiKey usa OAuth2 con Client ID e Secret:\n\n";

echo "Inserisci DigiKey Client ID: ";
$digikeyClientId = trim(fgets(STDIN));

echo "Inserisci DigiKey Client Secret: ";
$digikeyClientSecret = trim(fgets(STDIN));

// Crea/aggiorna Mouser con le 3 API
$mouser = Supplier::updateOrCreate(
    ['api_name' => 'mouser'],
    [
        'name' => 'Mouser Electronics',
        'code' => 'MOUSER',
        'api_name' => 'mouser',
        'website' => 'https://www.mouser.com',
        'email' => 'support@mouser.com',
        'api_enabled' => true,
        'api_credentials' => encrypt(json_encode([
            'search_api_key' => $mouseSearchKey,
            'order_api_key' => $mouseOrderKey,
            'cart_api_key' => $mouseCartKey
        ])),
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
        ]
    ]
);

// Crea/aggiorna DigiKey
$digikey = Supplier::updateOrCreate(
    ['api_name' => 'digikey'],
    [
        'name' => 'DigiKey Electronics', 
        'code' => 'DIGIKEY',
        'api_name' => 'digikey',
        'website' => 'https://www.digikey.com',
        'email' => 'api.support@digikey.com',
        'api_enabled' => true,
        'api_credentials' => encrypt(json_encode([
            'client_id' => $digikeyClientId,
            'client_secret' => $digikeyClientSecret,
            'grant_type' => 'client_credentials'
        ])),
        'api_settings' => [
            'endpoints' => [
                'oauth' => 'https://api.digikey.com/v1/oauth2/token',
                'search' => 'https://api.digikey.com/products/v4/search',
                'part_detail' => 'https://api.digikey.com/products/v4/search/{partnumber}/productdetails',
                'pricing' => 'https://api.digikey.com/products/v4/search/{partnumber}/pricing'
            ],
            'oauth_scope' => 'product.information',
            'rate_limit' => 1000, // requests per hour
            'timeout' => 30,
            'retry_attempts' => 3,
            'supported_currencies' => ['USD', 'EUR']
        ]
    ]
);

echo "\n✅ Fornitori configurati con successo!\n";
echo "===========================================\n";
echo "🔍 Mouser Electronics:\n";
echo "   - ID: " . $mouser->id . "\n";
echo "   - Search API: Configurata\n";
echo "   - Order API: Configurata\n"; 
echo "   - Cart API: Configurata\n\n";
echo "🔍 DigiKey Electronics:\n";
echo "   - ID: " . $digikey->id . "\n";
echo "   - OAuth2: Configurato\n\n";
echo "🌐 Gestisci i fornitori su: http://localhost:8000/admin/suppliers\n";
echo "🧪 Testa le API nella sezione Components > Import from Supplier\n";