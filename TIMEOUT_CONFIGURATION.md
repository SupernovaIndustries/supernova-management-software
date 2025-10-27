# Timeout Configuration - Import CSV/Excel

## Problema Risolto

**Errore originale:**
```
Maximum execution time of 30 seconds exceeded
vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php:693
```

**Causa:** Import di file CSV/Excel grandi con upload su Nextcloud/Syncthing richiede più di 30 secondi.

**Soluzione:** Timeout aumentato a **5 minuti (300 secondi)** per tutte le operazioni di import.

---

## Modifiche Implementate

### 1. ComponentImportService.php

Timeout aumentato all'inizio di ogni metodo di import:

```php
// app/Services/ComponentImportService.php

public function importFromExcel(...): array
{
    // Set execution time to 5 minutes for large imports with file uploads
    set_time_limit(300);
    // ... resto del codice
}

public function importFromCsv(...): array
{
    // Set execution time to 5 minutes for large imports with file uploads
    set_time_limit(300);
    // ... resto del codice
}
```

**Posizione:** Lines 169-170, 317-318

### 2. ListComponents.php (Filament Action)

Timeout aumentato prima dell'import:

```php
// app/Filament/Resources/ComponentResource/Pages/ListComponents.php

Actions\Action::make('import_components')
    ->action(function (array $data) {
        // Increase execution time to 5 minutes for large imports with Nextcloud upload
        set_time_limit(300);

        $importService = app(ComponentImportService::class);
        // ... resto del codice
    })
```

**Posizione:** Lines 82-83

---

## Timeout Configurati nel Sistema

### PHP Execution Time
- ✅ **Import Excel:** 300 secondi (5 minuti)
- ✅ **Import CSV:** 300 secondi (5 minuti)
- ✅ **Filament Action:** 300 secondi (5 minuti)

### HTTP Client Timeouts
- ✅ **Currency API:** 5 secondi (CurrencyExchangeService)
- ✅ **Ollama AI:** Configurabile (default: 120 secondi)

### File Upload
- 📁 **Storage Disk:** Local (synced by Syncthing)
- 📁 **Max File Size:** Configurato in `php.ini` (vedi sotto)

---

## Configurazione PHP.ini (Opzionale)

Se hai ancora problemi di timeout, verifica anche il `php.ini`:

```ini
; Tempo massimo di esecuzione (secondi)
max_execution_time = 300

; Tempo massimo di parsing input (secondi)
max_input_time = 300

; Dimensione massima upload file
upload_max_filesize = 50M
post_max_size = 50M

; Memoria massima per script
memory_limit = 512M
```

### Trovare il php.ini

```bash
# Trova quale php.ini è in uso
php --ini

# Output:
# Configuration File (php.ini) Path: /etc/php/8.2/cli
# Loaded Configuration File:         /etc/php/8.2/cli/php.ini
```

### Modificare per Web Server

Se usi **Apache**:
```bash
sudo nano /etc/php/8.2/apache2/php.ini
sudo systemctl restart apache2
```

Se usi **PHP-FPM**:
```bash
sudo nano /etc/php/8.2/fpm/php.ini
sudo systemctl restart php8.2-fpm
```

Se usi **Docker**:
```dockerfile
# Dockerfile o docker-compose.yml
PHP_MAX_EXECUTION_TIME=300
PHP_MEMORY_LIMIT=512M
```

---

## Timeout Specifici per Operazione

### Import Piccoli (< 100 righe)
**Tempo stimato:** 10-30 secondi
- Include: Parsing CSV, AI categorization, conversione valuta

### Import Medi (100-500 righe)
**Tempo stimato:** 30-90 secondi
- Include: Batch processing, upload Nextcloud

### Import Grandi (500-2000 righe)
**Tempo stimato:** 90-240 secondi
- Include: Multiple batches, AI processing, sync Syncthing

### Import Molto Grandi (>2000 righe)
**Tempo stimato:** 240-300 secondi
- Considera di dividere il file in batch più piccoli

---

## Troubleshooting

### Problema: Timeout ancora dopo 5 minuti

**Soluzione 1:** Aumenta ulteriormente il timeout

```php
// In ComponentImportService.php
set_time_limit(600); // 10 minuti
```

**Soluzione 2:** Dividi il file CSV in parti più piccole

```bash
# Dividi un CSV grande in file da 500 righe
split -l 500 components.csv components_part_
```

**Soluzione 3:** Disabilita AI categorization per import grandi

```php
$importService = app(ComponentImportService::class);
$importService->setUseAiCategories(false); // Usa categorization keyword-based
```

**Soluzione 4:** Usa Queue per import asincrono

```php
// Dispatch job in background
ImportComponentsJob::dispatch($filePath, $supplier, $invoiceData);
```

### Problema: Upload file troppo lento su Nextcloud

**Diagnosi:** Verifica velocità upload

```bash
# Test velocità di scrittura su disco Syncthing
time dd if=/dev/zero of=/path/to/syncthing/test.dat bs=1M count=100
```

**Soluzioni:**
1. Aumenta timeout solo per upload: `ini_set('max_execution_time', 600)`
2. Usa storage locale temporaneo, poi sposta su Syncthing
3. Ottimizza configurazione Syncthing (disabilita versioning temporaneamente)

### Problema: Timeout su Ollama AI

**Soluzione:** Aumenta timeout in OllamaService

```php
// app/Services/OllamaService.php
protected int $timeout = 300; // 5 minuti invece di 120 secondi
```

### Problema: Timeout su Currency API

**Soluzione:** Disabilita conversione automatica per import grandi

```php
$importService->setAutoConvertCurrency(false);
```

---

## Monitoring e Logging

### Verifica Timeout Attuale

```php
// Verifica il timeout corrente durante import
echo "Current max_execution_time: " . ini_get('max_execution_time') . " seconds\n";
```

### Log Import Performance

I log dettagliati sono in `storage/logs/laravel.log`:

```bash
# Monitora import in tempo reale
tail -f storage/logs/laravel.log | grep -E "import|Import|CSV|Excel"

# Vedi solo errori di timeout
tail -f storage/logs/laravel.log | grep -i "timeout\|execution time"
```

### Esempi Log Import

```
[INFO] CSV import started {"supplier":"mouser","total_rows":150}
[INFO] Excel import started {"supplier":"digikey","total_rows":300}
[INFO] Price automatically converted {"from_currency":"USD","converted_amount":9.00}
[INFO] AI category generated {"category":"Resistori SMD","category_id":5}
[INFO] Batch inventory movements created {"count":50}
```

---

## Performance Tips

### 1. Usa Excel invece di CSV
- ✅ Più veloce da parsare
- ✅ Nessun problema di encoding
- ✅ Meglio con file grandi

```php
// Excel è preferito automaticamente nel sistema
$importService->importFromExcel($filePath, ...);
```

### 2. Batch Import
Il sistema già usa batch di 50 componenti. Puoi aumentare:

```php
// In ComponentImportService.php (line ~212)
$batchSize = 100; // Aumenta da 50 a 100
```

### 3. Disabilita Features per Import Veloci

```php
$importService = app(ComponentImportService::class);

// Disabilita AI categorization (usa keyword matching)
$importService->setUseAiCategories(false);

// Disabilita conversione valuta
$importService->setAutoConvertCurrency(false);

// Import veloce
$result = $importService->importFromCsv($filePath, $supplier);
```

### 4. Cache Warming

Pre-carica i tassi di cambio prima di import grandi:

```bash
# Riscalda cache valuta
php test_currency_conversion.php
```

---

## Test Timeout

### Script di Test

```php
<?php
// test_timeout.php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing timeout configuration...\n";
echo "Current max_execution_time: " . ini_get('max_execution_time') . " seconds\n";

// Test setting timeout
set_time_limit(300);
echo "After set_time_limit(300): " . ini_get('max_execution_time') . " seconds\n";

// Simulate long import
echo "Simulating 2-minute process...\n";
sleep(120);
echo "✓ Completed successfully after 2 minutes\n";
```

### Esegui Test

```bash
php test_timeout.php
```

---

## Configurazione Ambiente

### Development (.env)

```env
# Nessuna configurazione speciale necessaria
# I timeout sono gestiti programmaticamente
```

### Production (.env)

```env
# Opzionale: Configura timeout per job queue
QUEUE_CONNECTION=redis
QUEUE_TIMEOUT=600
```

---

## Backup & Recovery

### Se Import Fallisce a Metà

Il sistema è **transazionale** per i movimenti di inventario, ma i componenti vengono creati uno per uno. Se l'import fallisce:

1. **Componenti già importati:** ✅ Rimangono nel database
2. **Movimenti inventario:** ✅ Creati in batch alla fine
3. **Componenti falliti:** ❌ Loggati in `errors` array

### Riprova Import

Puoi **importare lo stesso file nuovamente**:
- Componenti esistenti vengono **aggiornati** (non duplicati)
- Match su `manufacturer_part_number`

---

## FAQ

### Q: Posso aumentare il timeout oltre 5 minuti?
**A:** Sì, modifica `set_time_limit(600)` per 10 minuti. Ma considera di usare Queue per import molto grandi.

### Q: Il timeout si applica anche alle API esterne?
**A:** No, le API hanno timeout separati (Currency: 5s, Ollama: 120s). Il timeout di esecuzione PHP è solo per lo script totale.

### Q: Cosa succede se il timeout scade comunque?
**A:** L'import si ferma, ma i componenti già importati rimangono salvati. Puoi importare di nuovo lo stesso file - i componenti esistenti vengono aggiornati.

### Q: Posso usare Queue per import asincroni?
**A:** Sì, ma richiede implementazione di Job Laravel. Attualmente l'import è sincrono.

---

**Implementato:** 2025-10-07
**Versione:** 1.0
**Timeout Default:** 300 secondi (5 minuti)
**File Modificati:**
- `app/Services/ComponentImportService.php`
- `app/Filament/Resources/ComponentResource/Pages/ListComponents.php`
