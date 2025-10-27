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
        $this->webdavUrl = $this->baseUrl . '/remote.php/dav/files/' . $this->username . '/';

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
     * Crea directory ricorsivamente (ottimizzato - nessun check esistenza)
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
                $currentPath .= ($currentPath ? '/' : '') . $part;

                try {
                    $this->client->request('MKCOL', $currentPath);
                } catch (GuzzleException $e) {
                    // 405 = Method Not Allowed = cartella già esiste (OK)
                    // 409 = Conflict = genitore non esiste (dovrebbe essere creato nel loop)
                    if (!in_array($e->getCode(), [405, 409])) {
                        throw $e;
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
     * Sposta/Rinomina cartella (alias di moveFile per consistenza)
     */
    public function moveFolder(string $sourcePath, string $destPath): bool
    {
        try {
            // Ensure destination parent directory exists
            $this->createDirectory(dirname($destPath));

            $response = $this->client->request('MOVE', $sourcePath, [
                'headers' => [
                    'Destination' => $this->webdavUrl . $destPath,
                ],
            ]);

            return in_array($response->getStatusCode(), [200, 201, 204]);

        } catch (GuzzleException $e) {
            Log::error("Errore spostamento cartella: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina cartella (ricorsivamente)
     */
    public function deleteFolder(string $remotePath): bool
    {
        try {
            $response = $this->client->request('DELETE', $remotePath);
            return in_array($response->getStatusCode(), [200, 204]);

        } catch (GuzzleException $e) {
            Log::error("Errore eliminazione cartella: " . $e->getMessage());
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
                $displayNameResult = $response->xpath('.//d:displayname');
                $displayName = !empty($displayNameResult) ? (string) $displayNameResult[0] : '';

                if (empty($displayName)) continue;

                $isCollection = !empty($response->xpath('.//d:resourcetype/d:collection'));

                $contentLengthResult = $response->xpath('.//d:getcontentlength');
                $size = !empty($contentLengthResult) ? (int) $contentLengthResult[0] : 0;

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
