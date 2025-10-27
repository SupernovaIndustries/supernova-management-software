# Integrazione Laravel → Nextcloud per Gestionale

Guida completa per integrare il gestionale Laravel con Nextcloud per la gestione automatica dei file aziendali.

## Configurazione Nextcloud

- **URL**: `https://supernova-cloud.tailce6599.ts.net`
- **Username**: `admin`
- **Password**: [usa la password admin di Nextcloud]
- **WebDAV URL**: `https://supernova-cloud.tailce6599.ts.net/remote.php/dav/files/admin/`

### Architettura Storage

Nextcloud usa **NFS nativo** per lo storage su NAS:
- **NAS**: 10.0.0.72
- **Share NFS**: `/mnt/md0/Supernova-Cloud`
- **Mount interno container**: `/mnt/ncdata`
- **Path dati utente**: `/mnt/ncdata/admin/files/`
- **Spazio disponibile**: 436GB sul NAS

**IMPORTANTE**:
- I file caricati via WebDAV vanno direttamente sul NAS (no External Storage)
- Le modifiche sono visibili automaticamente grazie al sistema di auto-sync
- I permessi devono essere `www-data:www-data` (UID 33, GID 0)
- L'auto-sync corregge i permessi ogni 5 minuti automaticamente

### Workflow File con NFS

Quando Laravel carica file su Nextcloud:
1. **Upload via WebDAV** → File va direttamente su NAS (path `/mnt/ncdata/admin/files/...`)
2. **Auto-sync attivo** → Entro 5 minuti corregge permessi e fa scan
3. **File visibile** → Appare automaticamente in Nextcloud

**Non serve scan manuale** - il sistema è già configurato con auto-sync ogni 5 minuti.

---

## 1. Setup Laravel

### Installazione dipendenze

```bash
composer require guzzlehttp/guzzle
```

### Configurazione .env

Aggiungi al file `.env`:

```env
NEXTCLOUD_URL=https://supernova-cloud.tailce6599.ts.net
NEXTCLOUD_USERNAME=admin
NEXTCLOUD_PASSWORD=your_password_here
NEXTCLOUD_BASE_PATH=/admin/files
```

### Configurazione config/services.php

Aggiungi questa sezione:

```php
<?php

return [
    // ... altre configurazioni

    'nextcloud' => [
        'url' => env('NEXTCLOUD_URL'),
        'username' => env('NEXTCLOUD_USERNAME'),
        'password' => env('NEXTCLOUD_PASSWORD'),
        'base_path' => env('NEXTCLOUD_BASE_PATH', '/admin/files'),
    ],
];
```

---

## 2. Helper Class: NextcloudHelper.php

Crea il file `app/Helpers/NextcloudHelper.php`:

```php
<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class NextcloudHelper
{
    protected $client;
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $webdavUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.nextcloud.url');
        $this->username = config('services.nextcloud.username');
        $this->password = config('services.nextcloud.password');
        $this->webdavUrl = $this->baseUrl . '/remote.php/dav/files/' . $this->username;

        $this->client = new Client([
            'base_uri' => $this->webdavUrl,
            'auth' => [$this->username, $this->password],
            'verify' => true,
            'timeout' => 30,
        ]);
    }

    /**
     * Carica un file su Nextcloud
     */
    public function uploadFile(string $localPath, string $remotePath): bool
    {
        try {
            if (!file_exists($localPath)) {
                Log::error("File non trovato: {$localPath}");
                return false;
            }

            $this->createDirectory(dirname($remotePath));

            $fileContent = fopen($localPath, 'r');

            $response = $this->client->request('PUT', $remotePath, [
                'body' => $fileContent,
            ]);

            if (is_resource($fileContent)) {
                fclose($fileContent);
            }

            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, [200, 201, 204])) {
                Log::info("File caricato su Nextcloud: {$remotePath}");
                return true;
            }

            return false;

        } catch (GuzzleException $e) {
            Log::error("Errore upload Nextcloud: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Carica contenuto diretto (stringa, PDF)
     */
    public function uploadContent(string $content, string $remotePath): bool
    {
        try {
            $this->createDirectory(dirname($remotePath));

            $response = $this->client->request('PUT', $remotePath, [
                'body' => $content,
            ]);

            return in_array($response->getStatusCode(), [200, 201, 204]);

        } catch (GuzzleException $e) {
            Log::error("Errore upload contenuto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Scarica un file
     */
    public function downloadFile(string $remotePath, string $localPath): bool
    {
        try {
            $response = $this->client->request('GET', $remotePath);

            if ($response->getStatusCode() === 200) {
                $directory = dirname($localPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                file_put_contents($localPath, $response->getBody()->getContents());
                return true;
            }

            return false;

        } catch (GuzzleException $e) {
            Log::error("Errore download: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea directory ricorsivamente
     */
    public function createDirectory(string $path): bool
    {
        try {
            $path = trim($path, '/');

            if (empty($path) || $path === '.') {
                return true;
            }

            $parts = explode('/', $path);
            $currentPath = '';

            foreach ($parts as $part) {
                $currentPath .= '/' . $part;

                if (!$this->fileExists($currentPath)) {
                    try {
                        $this->client->request('MKCOL', $currentPath);
                    } catch (GuzzleException $e) {
                        if ($e->getCode() !== 405) {
                            throw $e;
                        }
                    }
                }
            }

            return true;

        } catch (GuzzleException $e) {
            Log::error("Errore creazione directory: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica esistenza file
     */
    public function fileExists(string $path): bool
    {
        try {
            $response = $this->client->request('PROPFIND', $path, [
                'headers' => ['Depth' => '0'],
            ]);

            return in_array($response->getStatusCode(), [200, 207]);

        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * Elimina file
     */
    public function deleteFile(string $remotePath): bool
    {
        try {
            $response = $this->client->request('DELETE', $remotePath);
            return in_array($response->getStatusCode(), [200, 204]);

        } catch (GuzzleException $e) {
            Log::error("Errore eliminazione: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sposta/Rinomina file
     */
    public function moveFile(string $sourcePath, string $destPath): bool
    {
        try {
            $response = $this->client->request('MOVE', $sourcePath, [
                'headers' => [
                    'Destination' => $this->webdavUrl . $destPath,
                ],
            ]);

            return in_array($response->getStatusCode(), [200, 201, 204]);

        } catch (GuzzleException $e) {
            Log::error("Errore spostamento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista file in directory
     */
    public function listFiles(string $path = '/')
    {
        try {
            $response = $this->client->request('PROPFIND', $path, [
                'headers' => ['Depth' => '1'],
            ]);

            if ($response->getStatusCode() !== 207) {
                return false;
            }

            $xml = simplexml_load_string($response->getBody()->getContents());
            $xml->registerXPathNamespace('d', 'DAV:');

            $files = [];

            foreach ($xml->xpath('//d:response') as $response) {
                $displayName = (string) $response->xpath('.//d:displayname')[0];

                if (empty($displayName)) continue;

                $isCollection = !empty($response->xpath('.//d:resourcetype/d:collection'));
                $size = (int) ($response->xpath('.//d:getcontentlength')[0] ?? 0);

                $files[] = [
                    'name' => $displayName,
                    'is_directory' => $isCollection,
                    'size' => $size,
                ];
            }

            return $files;

        } catch (GuzzleException $e) {
            Log::error("Errore listing: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea link pubblico
     */
    public function createPublicLink(string $path, ?string $password = null, ?string $expireDate = null)
    {
        try {
            $ocsUrl = $this->baseUrl . '/ocs/v2.php/apps/files_sharing/api/v1/shares';

            $client = new Client([
                'auth' => [$this->username, $this->password],
                'verify' => true,
            ]);

            $params = [
                'shareType' => 3,
                'path' => $path,
            ];

            if ($password) $params['password'] = $password;
            if ($expireDate) $params['expireDate'] = $expireDate;

            $response = $client->request('POST', $ocsUrl, [
                'form_params' => $params,
                'headers' => ['OCS-APIRequest' => 'true'],
            ]);

            if ($response->getStatusCode() === 200) {
                $xml = simplexml_load_string($response->getBody()->getContents());
                return (string) $xml->data->url;
            }

            return false;

        } catch (GuzzleException $e) {
            Log::error("Errore link pubblico: " . $e->getMessage());
            return false;
        }
    }
}
```

---

## 3. Autoload Helper (opzionale)

In `composer.json`, aggiungi alla sezione `autoload`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/"
    },
    "files": [
        "app/Helpers/NextcloudHelper.php"
    ]
},
```

Poi esegui:

```bash
composer dump-autoload
```

---

## 4. Esempi Pratici per Gestionale

### A. Upload file da Form

```php
use App\Helpers\NextcloudHelper;

class DocumentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clienti,id',
            'documento' => 'required|file|max:10240',
        ]);

        $cliente = Cliente::findOrFail($request->cliente_id);
        $file = $request->file('documento');

        // Upload su Nextcloud
        $nc = new NextcloudHelper();
        $remotePath = sprintf(
            '/Clienti/%s/Documenti/%s',
            $cliente->nome,
            $file->getClientOriginalName()
        );

        if ($nc->uploadFile($file->getRealPath(), $remotePath)) {
            // Salva nel database
            Documento::create([
                'cliente_id' => $cliente->id,
                'nome' => $file->getClientOriginalName(),
                'nextcloud_path' => $remotePath,
                'size' => $file->getSize(),
            ]);

            return redirect()->back()->with('success', 'Documento caricato');
        }

        return redirect()->back()->with('error', 'Errore upload');
    }
}
```

### B. Genera PDF e carica su Nextcloud

```php
use Barryvdh\DomPDF\Facade\Pdf;
use App\Helpers\NextcloudHelper;

class FatturaController extends Controller
{
    public function genera($id)
    {
        $fattura = Fattura::with('cliente', 'righe')->findOrFail($id);

        // Genera PDF
        $pdf = Pdf::loadView('fatture.pdf', compact('fattura'));
        $content = $pdf->output();

        // Upload su Nextcloud
        $nc = new NextcloudHelper();
        $remotePath = sprintf(
            '/Clienti/%s/Fatture/Fattura_%s.pdf',
            $fattura->cliente->nome,
            $fattura->numero
        );

        if ($nc->uploadContent($content, $remotePath)) {
            $fattura->update([
                'nextcloud_path' => $remotePath,
                'pdf_generato_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'path' => $remotePath
            ]);
        }

        return response()->json(['success' => false], 500);
    }
}
```

### C. Sincronizza directory intera

```php
use App\Helpers\NextcloudHelper;
use Illuminate\Support\Facades\File;

class SyncController extends Controller
{
    public function syncExports()
    {
        $nc = new NextcloudHelper();
        $localDir = storage_path('app/exports');
        $synced = 0;

        if (!is_dir($localDir)) {
            return response()->json(['error' => 'Directory non trovata']);
        }

        $files = File::files($localDir);

        foreach ($files as $file) {
            $remotePath = '/Export/' . $file->getFilename();

            if (!$nc->fileExists($remotePath)) {
                if ($nc->uploadFile($file->getRealPath(), $remotePath)) {
                    $synced++;
                }
            }
        }

        return response()->json([
            'synced' => $synced,
            'total' => count($files)
        ]);
    }
}
```

### D. Job per upload in background

```php
// app/Jobs/UploadToNextcloud.php

namespace App\Jobs;

use App\Helpers\NextcloudHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UploadToNextcloud implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $remotePath;
    protected $deleteAfter;

    public function __construct($filePath, $remotePath, $deleteAfter = false)
    {
        $this->filePath = $filePath;
        $this->remotePath = $remotePath;
        $this->deleteAfter = $deleteAfter;
    }

    public function handle()
    {
        $nc = new NextcloudHelper();

        if ($nc->uploadFile($this->filePath, $this->remotePath)) {
            // Elimina file locale se richiesto
            if ($this->deleteAfter && file_exists($this->filePath)) {
                unlink($this->filePath);
            }

            \Log::info("File caricato: {$this->remotePath}");
        } else {
            throw new \Exception("Upload fallito: {$this->remotePath}");
        }
    }
}
```

**Uso del Job:**

```php
use App\Jobs\UploadToNextcloud;

// Nel controller
dispatch(new UploadToNextcloud($localPath, $remotePath, true));
```

### E. Event & Listener per upload automatico

```php
// app/Events/DocumentoCreato.php

namespace App\Events;

use App\Models\Documento;
use Illuminate\Foundation\Events\Dispatchable;

class DocumentoCreato
{
    use Dispatchable;

    public $documento;
    public $filePath;

    public function __construct(Documento $documento, $filePath)
    {
        $this->documento = $documento;
        $this->filePath = $filePath;
    }
}
```

```php
// app/Listeners/CaricaDocumentoSuNextcloud.php

namespace App\Listeners;

use App\Events\DocumentoCreato;
use App\Helpers\NextcloudHelper;

class CaricaDocumentoSuNextcloud
{
    public function handle(DocumentoCreato $event)
    {
        $nc = new NextcloudHelper();

        $remotePath = sprintf(
            '/Clienti/%s/Documenti/%s',
            $event->documento->cliente->nome,
            basename($event->filePath)
        );

        if ($nc->uploadFile($event->filePath, $remotePath)) {
            $event->documento->update([
                'nextcloud_path' => $remotePath,
                'synced_at' => now()
            ]);
        }
    }
}
```

**Registra in EventServiceProvider:**

```php
protected $listen = [
    DocumentoCreato::class => [
        CaricaDocumentoSuNextcloud::class,
    ],
];
```

**Usa nei controller:**

```php
$documento = Documento::create([...]);
event(new DocumentoCreato($documento, $tempFilePath));
```

### F. Comando Artisan per sincronizzazione schedulata

```php
// app/Console/Commands/SyncToNextcloud.php

namespace App\Console\Commands;

use App\Helpers\NextcloudHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncToNextcloud extends Command
{
    protected $signature = 'nextcloud:sync {directory}';
    protected $description = 'Sincronizza directory con Nextcloud';

    public function handle()
    {
        $directory = $this->argument('directory');

        if (!is_dir($directory)) {
            $this->error("Directory non trovata: {$directory}");
            return 1;
        }

        $nc = new NextcloudHelper();
        $files = File::allFiles($directory);
        $bar = $this->output->createProgressBar(count($files));

        foreach ($files as $file) {
            $relativePath = str_replace($directory, '', $file->getRealPath());
            $remotePath = '/Sync' . $relativePath;

            if (!$nc->fileExists($remotePath)) {
                $nc->uploadFile($file->getRealPath(), $remotePath);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sincronizzazione completata!');

        return 0;
    }
}
```

**Uso:**

```bash
php artisan nextcloud:sync /path/to/directory
```

### G. Scheduler automatico

In `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sincronizza exports ogni ora
    $schedule->call(function () {
        $nc = new NextcloudHelper();
        $files = \File::files(storage_path('app/exports'));

        foreach ($files as $file) {
            $remotePath = '/Export/' . $file->getFilename();
            if (!$nc->fileExists($remotePath)) {
                $nc->uploadFile($file->getRealPath(), $remotePath);
            }
        }
    })->hourly();

    // Backup database giornaliero
    $schedule->call(function () {
        $filename = 'backup-' . date('Y-m-d') . '.sql';
        $localPath = storage_path('app/backups/' . $filename);

        // Esegui backup (esempio mysqldump)
        exec("mysqldump -u user -p password database > {$localPath}");

        // Carica su Nextcloud
        $nc = new NextcloudHelper();
        $nc->uploadFile($localPath, '/Backups/' . $filename);

    })->daily();
}
```

---

## 5. Trait Riusabile per Modelli

```php
// app/Traits/SyncsWithNextcloud.php

namespace App\Traits;

use App\Helpers\NextcloudHelper;

trait SyncsWithNextcloud
{
    public function uploadToNextcloud(string $localPath, string $remotePath): bool
    {
        $nc = new NextcloudHelper();

        if ($nc->uploadFile($localPath, $remotePath)) {
            $this->update(['nextcloud_path' => $remotePath]);
            return true;
        }

        return false;
    }

    public function downloadFromNextcloud(string $localPath): bool
    {
        if (!$this->nextcloud_path) {
            return false;
        }

        $nc = new NextcloudHelper();
        return $nc->downloadFile($this->nextcloud_path, $localPath);
    }

    public function deleteFromNextcloud(): bool
    {
        if (!$this->nextcloud_path) {
            return false;
        }

        $nc = new NextcloudHelper();
        return $nc->deleteFile($this->nextcloud_path);
    }
}
```

**Uso:**

```php
use App\Traits\SyncsWithNextcloud;

class Fattura extends Model
{
    use SyncsWithNextcloud;

    // ...
}

// Nel controller
$fattura->uploadToNextcloud($localFile, '/Clienti/Fatture/fattura.pdf');
```

---

## 6. Middleware per Logging

```php
// app/Http/Middleware/LogNextcloudActivity.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class LogNextcloudActivity
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($request->has('nextcloud_upload')) {
            Log::channel('nextcloud')->info('Upload effettuato', [
                'user' => auth()->id(),
                'file' => $request->input('documento')?->getClientOriginalName(),
                'timestamp' => now(),
            ]);
        }

        return $response;
    }
}
```

---

## 7. Testing

```php
// tests/Feature/NextcloudTest.php

namespace Tests\Feature;

use App\Helpers\NextcloudHelper;
use Tests\TestCase;

class NextcloudTest extends TestCase
{
    public function test_can_upload_file()
    {
        $nc = new NextcloudHelper();

        $testFile = storage_path('app/test.txt');
        file_put_contents($testFile, 'Test content');

        $result = $nc->uploadFile($testFile, '/Test/test.txt');

        $this->assertTrue($result);

        unlink($testFile);
    }

    public function test_can_create_directory()
    {
        $nc = new NextcloudHelper();
        $result = $nc->createDirectory('/Test/SubDir');

        $this->assertTrue($result);
    }
}
```

---

## 8. Tips & Best Practices

### Gestione errori

```php
try {
    $nc = new NextcloudHelper();
    $result = $nc->uploadFile($local, $remote);

    if (!$result) {
        throw new \Exception("Upload fallito");
    }
} catch (\Exception $e) {
    Log::error("Errore Nextcloud: " . $e->getMessage());
    return response()->json(['error' => 'Upload fallito'], 500);
}
```

### Verifica prima di sovrascrivere

```php
$nc = new NextcloudHelper();

if ($nc->fileExists($remotePath)) {
    // Aggiungi timestamp al nome
    $remotePath = str_replace('.pdf', '_' . time() . '.pdf', $remotePath);
}

$nc->uploadFile($localPath, $remotePath);
```

### Batch upload

```php
$files = [...]; // array di file
$results = [];

foreach ($files as $file) {
    $results[] = [
        'file' => $file['name'],
        'success' => $nc->uploadFile($file['local'], $file['remote'])
    ];
}

return response()->json($results);
```

### Cleanup automatico

```php
// Elimina file locali dopo upload
if ($nc->uploadFile($localPath, $remotePath)) {
    unlink($localPath);
}
```

---

## 9. Troubleshooting

### Errore SSL/certificato

Se hai problemi con HTTPS:

```php
// In NextcloudHelper.php __construct()
$this->client = new Client([
    'base_uri' => $this->webdavUrl,
    'auth' => [$this->username, $this->password],
    'verify' => false, // SOLO per sviluppo
    'timeout' => 30,
]);
```

### Timeout per file grandi

```php
$this->client = new Client([
    // ...
    'timeout' => 300, // 5 minuti
]);
```

### Debug richieste

```php
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

$stack = HandlerStack::create();
$stack->push(Middleware::log(\Log::getLogger(), new \GuzzleHttp\MessageFormatter()));

$this->client = new Client([
    'handler' => $stack,
    // ... altre config
]);
```

---

## 10. Verifica Storage NAS

### Controllare che i file siano sul NAS

Dopo aver caricato file via WebDAV, puoi verificare che siano effettivamente sul NAS:

```bash
# Dal Mac (se connesso al NAS)
docker exec nextcloud-aio-nextcloud ls -lah /mnt/ncdata/admin/files/

# Verificare mount NFS
docker exec nextcloud-aio-nextcloud df -h | grep ncdata
# Output atteso: :/mnt/md0/Supernova-Cloud  436G  ...  /mnt/ncdata
```

### Sistema Auto-Sync

Il server Mac ha un auto-sync attivo che ogni 5 minuti:
1. Corregge permessi (`chown -R www-data:www-data`)
2. Esegue scan Nextcloud (`occ files:scan admin`)

**Log auto-sync**: `/Users/supernova/Cloud/auto-sync.log`

```bash
# Verificare auto-sync attivo
ps aux | grep auto-sync

# Vedere log in tempo reale
tail -f /Users/supernova/Cloud/auto-sync.log
```

### Scan Manuale (se necessario)

Se hai bisogno di vedere immediatamente i file caricati:

```bash
# Scan immediato
/Users/supernova/Cloud/scan-nas.sh

# O comando diretto
docker exec --user www-data nextcloud-aio-nextcloud php occ files:scan admin
```

---

## 11. Riferimenti

- **WebDAV URL**: `https://supernova-cloud.tailce6599.ts.net/remote.php/dav/files/admin/`
- **API Docs**: https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/
- **OCS Share API**: https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-share-api.html
- **NAS NFS**: `10.0.0.72:/mnt/md0/Supernova-Cloud`

---

**Creato**: 2025-10-03
**Aggiornato**: 2025-10-04 (NFS nativo + auto-sync)
**Autore**: Claude Code
