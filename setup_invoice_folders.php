<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Setup Fatture Fornitori Folder Structure ===\n\n";

// Check if syncthing_documents disk is configured
if (!config('filesystems.disks.syncthing_documents')) {
    echo "❌ Error: syncthing_documents disk not configured\n";
    echo "Please configure SYNCTHING_DOCUMENTS_PATH in .env\n";
    exit(1);
}

$disk = Storage::disk('syncthing_documents');
$basePath = $disk->path('');

echo "📁 Syncthing Documents Path: {$basePath}\n\n";

// Check if base path exists
if (!file_exists($basePath)) {
    echo "❌ Error: Base path does not exist: {$basePath}\n";
    echo "Please ensure Syncthing is configured and the folder is synced\n";
    exit(1);
}

echo "✓ Base path exists\n\n";

// Create folder structure
$currentYear = date('Y');
$previousYear = (int)$currentYear - 1;
$years = [$previousYear, $currentYear];
$suppliers = ['Mouser', 'DigiKey', 'Farnell', 'Altri'];

$foldersCreated = 0;
$foldersExisted = 0;

echo "Creating folder structure:\n";
echo str_repeat('-', 60) . "\n";

foreach ($years as $year) {
    foreach ($suppliers as $supplier) {
        $folderPath = "Fatture Fornitori/{$year}/{$supplier}";
        $fullPath = $disk->path($folderPath);

        if (file_exists($fullPath)) {
            echo "✓ Already exists: {$folderPath}\n";
            $foldersExisted++;
        } else {
            try {
                // Create directory with proper permissions
                if (!file_exists(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0755, true);
                }
                mkdir($fullPath, 0755, true);

                // Create a README file in each folder
                $readmePath = $fullPath . '/README.txt';
                $readmeContent = "Fatture Fornitori - {$supplier} {$year}\n";
                $readmeContent .= "=======================================\n\n";
                $readmeContent .= "Questa cartella contiene le fatture d'acquisto da {$supplier} per l'anno {$year}.\n\n";
                $readmeContent .= "Le fatture vengono caricate automaticamente dal sistema di gestione\n";
                $readmeContent .= "durante l'import dei componenti.\n\n";
                $readmeContent .= "Formato file: PDF, JPG, PNG\n";
                $readmeContent .= "Sincronizzato con: Nextcloud/Syncthing\n\n";
                $readmeContent .= "Generato automaticamente: " . date('Y-m-d H:i:s') . "\n";

                file_put_contents($readmePath, $readmeContent);

                echo "✅ Created: {$folderPath}\n";
                $foldersCreated++;
            } catch (\Exception $e) {
                echo "❌ Failed: {$folderPath} - {$e->getMessage()}\n";
            }
        }
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Summary:\n";
echo "  Folders created: {$foldersCreated}\n";
echo "  Folders existed: {$foldersExisted}\n";
echo "  Total folders: " . ($foldersCreated + $foldersExisted) . "\n";

// Create a master README in the base Fatture Fornitori folder
$masterReadmePath = $disk->path('Fatture Fornitori/README.txt');
$masterReadmeContent = <<<EOT
FATTURE FORNITORI - GESTIONE COMPONENTI ELETTRONICI
====================================================

Questa cartella contiene tutte le fatture d'acquisto dai fornitori di componenti.

STRUTTURA:
  Fatture Fornitori/
  ├── [Anno]/
  │   ├── Mouser/
  │   ├── DigiKey/
  │   ├── Farnell/
  │   └── Altri/

FUNZIONAMENTO:
- Le fatture vengono caricate automaticamente tramite il sistema di gestione
- Ogni fattura è collegata ai componenti importati tramite il numero di fattura
- Le fatture sono sincronizzate su Nextcloud via Syncthing
- Accessibile a: Amministratori + Contabile

FORMATI ACCETTATI:
- PDF (preferito)
- JPG, PNG (per fatture scannerizzate)

ACCESSO:
- Sistema di gestione: Filament Admin > Components > Import
- Nextcloud: Documenti SRL/Fatture Fornitori/
- Syncthing: Sincronizzato automaticamente

INFORMAZIONI FATTURA:
Ogni fattura contiene i seguenti metadati nel database:
- Numero fattura
- Data fattura
- Totale (€)
- Fornitore
- Componenti acquistati
- Note

Per maggiori informazioni, consultare il manuale del sistema di gestione.

Generato: {date('Y-m-d H:i:s')}
Sistema: Supernova Management
EOT;

try {
    $baseFattureDir = $disk->path('Fatture Fornitori');
    if (!file_exists($baseFattureDir)) {
        mkdir($baseFattureDir, 0755, true);
    }
    file_put_contents($masterReadmePath, $masterReadmeContent);
    echo "\n✅ Created master README in Fatture Fornitori/\n";
} catch (\Exception $e) {
    echo "\n❌ Failed to create master README: {$e->getMessage()}\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "✅ Setup completed!\n\n";
echo "Folder structure ready for invoice uploads.\n";
echo "Location: {$basePath}/Fatture Fornitori/\n";
echo "\nNext steps:\n";
echo "1. Ensure Syncthing is running and syncing\n";
echo "2. Import components with invoices via Filament Admin\n";
echo "3. Check Nextcloud to verify invoices are synced\n";
echo "4. Share folder access with your accountant if needed\n";
