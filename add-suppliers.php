<?php

// Script per aggiungere Mouser e DigiKey con API keys
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Supplier;

// Chiedi le API keys all'utente
echo "🔑 Configurazione API Keys Fornitori\n";
echo "=====================================\n\n";

// Mouser API Key
echo "📦 MOUSER API Configuration:\n";
echo "Inserisci la tua Mouser API Key: ";
$mouseApiKey = trim(fgets(STDIN));

// DigiKey API Keys
echo "\n📦 DIGIKEY API Configuration:\n";
echo "Inserisci la tua DigiKey Client ID: ";
$digikeyClientId = trim(fgets(STDIN));
echo "Inserisci la tua DigiKey Client Secret: ";
$digikeyClientSecret = trim(fgets(STDIN));

// Crea/aggiorna Mouser
$mouser = Supplier::updateOrCreate(
    ['name' => 'Mouser Electronics'],
    [
        'description' => 'Global electronic component distributor',
        'website' => 'https://www.mouser.com',
        'contact_email' => 'support@mouser.com',
        'api_enabled' => true,
        'api_base_url' => 'https://api.mouser.com/api/v1',
        'api_credentials' => encrypt(json_encode([
            'api_key' => $mouseApiKey
        ])),
        'api_settings' => [
            'rate_limit' => 100,
            'timeout' => 30,
            'retry_attempts' => 3
        ]
    ]
);

// Crea/aggiorna DigiKey  
$digikey = Supplier::updateOrCreate(
    ['name' => 'DigiKey Electronics'],
    [
        'description' => 'Global electronic components distributor',
        'website' => 'https://www.digikey.com',
        'contact_email' => 'support@digikey.com',
        'api_enabled' => true,
        'api_base_url' => 'https://api.digikey.com',
        'api_credentials' => encrypt(json_encode([
            'client_id' => $digikeyClientId,
            'client_secret' => $digikeyClientSecret
        ])),
        'api_settings' => [
            'rate_limit' => 1000,
            'timeout' => 30,
            'retry_attempts' => 3,
            'oauth_scope' => 'productinformation'
        ]
    ]
);

echo "\n✅ Fornitori configurati con successo!\n";
echo "🔍 Mouser ID: " . $mouser->id . "\n";
echo "🔍 DigiKey ID: " . $digikey->id . "\n";
echo "\n🌐 Vai su http://localhost:8000/admin/suppliers per gestirli\n";