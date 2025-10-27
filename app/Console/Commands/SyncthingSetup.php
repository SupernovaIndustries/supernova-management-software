<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SyncthingPathManager;

class SyncthingSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncthing:setup {--force : Force recreation of directories}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup Syncthing integration directories';

    /**
     * Execute the console command.
     */
    public function handle(SyncthingPathManager $syncthingPaths): int
    {
        $this->info('Setting up Syncthing integration...');
        $this->newLine();

        $force = $this->option('force');

        try {
            // Ensure all directories exist
            $syncthingPaths->ensureDirectoriesExist();

            $this->info('✓ Created/verified all Syncthing directories');

            // Display current configuration
            $this->newLine();
            $this->info('Current Configuration:');
            $this->info('Root Path: ' . $syncthingPaths->getRootPath());
            $this->newLine();

            // Create .gitkeep files in each directory to preserve structure
            foreach ($syncthingPaths->listDisks() as $diskName) {
                try {
                    $disk = $syncthingPaths->disk($diskName);
                    if (!$disk->exists('.gitkeep')) {
                        $disk->put('.gitkeep', '# This file ensures the directory is tracked by git');
                        $this->info("✓ Created .gitkeep in {$diskName}");
                    }
                } catch (\Exception $e) {
                    $this->warn("Could not create .gitkeep in {$diskName}: " . $e->getMessage());
                }
            }

            $this->newLine();
            $this->info('Syncthing setup completed successfully!');
            $this->info('Run "php artisan syncthing:status" to view the current status.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to setup Syncthing integration: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}