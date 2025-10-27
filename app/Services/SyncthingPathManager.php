<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class SyncthingPathManager
{
    protected array $diskMapping = [
        'clients' => 'syncthing_clients',
        'documents' => 'syncthing_documents',
        'warehouse' => 'syncthing_warehouse',
        'templates' => 'syncthing_templates',
        'prototypes' => 'syncthing_prototypes',
        'deepsouth' => 'syncthing_deepsouth',
        'finance' => 'syncthing_finance',
        'logos' => 'syncthing_logos',
        'marketing' => 'syncthing_marketing',
        'partnership' => 'syncthing_partnership',
        'planning' => 'syncthing_planning',
        'pitch' => 'syncthing_pitch',
        'shareit' => 'syncthing_shareit',
        'site' => 'syncthing_site',
    ];

    /**
     * Get the root path for Syncthing
     */
    public function getRootPath(): string
    {
        return env('SYNCTHING_ROOT_PATH', storage_path('app/syncthing'));
    }

    /**
     * Get a specific disk by name
     */
    public function disk(string $name)
    {
        if (!isset($this->diskMapping[$name])) {
            throw new \InvalidArgumentException("Unknown Syncthing disk: {$name}");
        }

        return Storage::disk($this->diskMapping[$name]);
    }

    /**
     * Get the full path for a specific Syncthing directory
     */
    public function getPath(string $name): string
    {
        $disk = $this->disk($name);
        return $disk->path('');
    }

    /**
     * Check if a disk exists and is accessible
     */
    public function diskExists(string $name): bool
    {
        try {
            $disk = $this->disk($name);
            return $disk->exists('');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * List all available Syncthing disks
     */
    public function listDisks(): array
    {
        return array_keys($this->diskMapping);
    }

    /**
     * Get client-specific disk
     */
    public function clientDisk(string $clientName)
    {
        $disk = $this->disk('clients');
        $clientPath = $clientName;
        
        // Ensure the client directory exists
        if (!$disk->exists($clientPath)) {
            $disk->makeDirectory($clientPath);
        }
        
        return $disk;
    }

    /**
     * Get the full path for a client directory
     */
    public function getClientPath(string $clientName): string
    {
        $disk = $this->disk('clients');
        return $disk->path($clientName);
    }

    /**
     * List all clients
     */
    public function listClients(): array
    {
        $disk = $this->disk('clients');
        return $disk->directories();
    }

    /**
     * Ensure all Syncthing directories exist
     */
    public function ensureDirectoriesExist(): void
    {
        foreach ($this->diskMapping as $name => $diskName) {
            try {
                $disk = Storage::disk($diskName);
                $path = $disk->path('');
                
                if (!is_dir($path)) {
                    mkdir($path, 0755, true);
                }
            } catch (\Exception $e) {
                // Log error but continue with other directories
                \Log::error("Failed to create directory for disk {$name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get storage information for all disks
     */
    public function getStorageInfo(): array
    {
        $info = [];
        
        foreach ($this->diskMapping as $name => $diskName) {
            try {
                $disk = Storage::disk($diskName);
                $path = $disk->path('');
                
                if (is_dir($path)) {
                    $info[$name] = [
                        'path' => $path,
                        'exists' => true,
                        'writable' => is_writable($path),
                        'size' => $this->getDirectorySize($path),
                        'files_count' => count($disk->allFiles()),
                    ];
                } else {
                    $info[$name] = [
                        'path' => $path,
                        'exists' => false,
                        'writable' => false,
                        'size' => 0,
                        'files_count' => 0,
                    ];
                }
            } catch (\Exception $e) {
                $info[$name] = [
                    'path' => 'unknown',
                    'exists' => false,
                    'writable' => false,
                    'size' => 0,
                    'files_count' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $info;
    }

    /**
     * Get directory size in bytes
     */
    protected function getDirectorySize(string $path): int
    {
        $size = 0;
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            // Return 0 if we can't calculate size
        }
        
        return $size;
    }
}