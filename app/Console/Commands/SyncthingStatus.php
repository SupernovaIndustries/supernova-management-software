<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SyncthingPathManager;

class SyncthingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncthing:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display Syncthing integration status and storage information';

    /**
     * Execute the console command.
     */
    public function handle(SyncthingPathManager $syncthingPaths): int
    {
        $this->info('Syncthing Integration Status');
        $this->info('============================');
        $this->newLine();

        $this->info('Root Path: ' . $syncthingPaths->getRootPath());
        $this->newLine();

        $storageInfo = $syncthingPaths->getStorageInfo();

        $headers = ['Disk', 'Path', 'Exists', 'Writable', 'Size', 'Files'];
        $rows = [];

        foreach ($storageInfo as $name => $info) {
            $rows[] = [
                $name,
                str_replace($syncthingPaths->getRootPath() . DIRECTORY_SEPARATOR, '', $info['path']),
                $info['exists'] ? '✓' : '✗',
                $info['writable'] ? '✓' : '✗',
                $this->formatBytes($info['size']),
                $info['files_count'],
            ];
        }

        $this->table($headers, $rows);

        // Show clients if available
        try {
            $clients = $syncthingPaths->listClients();
            if (!empty($clients)) {
                $this->newLine();
                $this->info('Available Clients:');
                foreach ($clients as $client) {
                    $this->line('  - ' . $client);
                }
            }
        } catch (\Exception $e) {
            $this->warn('Could not list clients: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}