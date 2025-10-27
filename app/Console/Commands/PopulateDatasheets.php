<?php

namespace App\Console\Commands;

use App\Jobs\PopulateDatasheetUrlsJob;
use App\Models\Component;
use App\Services\DatasheetScraperService;
use Illuminate\Console\Command;

/**
 * PopulateDatasheets Command
 *
 * Artisan command to manually populate datasheet URLs for components.
 * Can run synchronously for testing or dispatch as a background job.
 */
class PopulateDatasheets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'components:populate-datasheets
                            {--limit=50 : Maximum number of components to process}
                            {--async : Run as background job instead of synchronously}
                            {--force : Process all components, even those with existing datasheets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate missing datasheet URLs for components using supplier APIs';

    /**
     * Execute the console command.
     */
    public function handle(DatasheetScraperService $scraperService): int
    {
        $limit = (int) $this->option('limit');
        $async = $this->option('async');
        $force = $this->option('force');

        $this->info("Starting datasheet population...");
        $this->newLine();

        // Show configuration
        $this->components->twoColumnDetail('Batch Size', (string) $limit);
        $this->components->twoColumnDetail('Mode', $async ? 'Async (Background Job)' : 'Sync (Immediate)');
        $this->components->twoColumnDetail('Force Reprocess', $force ? 'Yes' : 'No');
        $this->components->twoColumnDetail('AI Validation', $scraperService->isAiValidationAvailable() ? 'Available' : 'Not Available');
        $this->newLine();

        // Count components to process
        $query = Component::query();

        if (!$force) {
            $query->whereNull('datasheet_url')->orWhere('datasheet_url', '');
        }

        $totalCount = $query->count();
        $processCount = min($totalCount, $limit);

        if ($totalCount === 0) {
            $this->info('No components to process.');
            return Command::SUCCESS;
        }

        $this->components->info("Found {$totalCount} component(s) without datasheet URLs");
        $this->components->info("Will process {$processCount} component(s) in this run");
        $this->newLine();

        if ($async) {
            // Dispatch as background job
            $this->info('Dispatching background job...');
            PopulateDatasheetUrlsJob::dispatch($limit);
            $this->components->info('Job dispatched successfully!');
            $this->info('Monitor logs for progress and results.');
            return Command::SUCCESS;
        }

        // Run synchronously (for manual testing)
        $this->info('Processing components synchronously...');
        $this->newLine();

        $stats = [
            'processed' => 0,
            'found' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $components = $query->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        $progressBar = $this->output->createProgressBar($components->count());
        $progressBar->start();

        foreach ($components as $component) {
            try {
                $stats['processed']++;

                // Skip components without manufacturer or MPN
                if (empty($component->manufacturer_part_number) || empty($component->manufacturer)) {
                    $stats['skipped']++;
                    $progressBar->advance();
                    continue;
                }

                // Skip components without supplier links
                $supplierLinks = $component->supplier_links ?? [];
                if (empty($supplierLinks)) {
                    $stats['skipped']++;
                    $progressBar->advance();
                    continue;
                }

                // Try to find datasheet URL
                $datasheetUrl = $scraperService->findDatasheetUrl($component);

                if ($datasheetUrl) {
                    $component->datasheet_url = $datasheetUrl;
                    $component->save();
                    $stats['found']++;
                } else {
                    $stats['failed']++;
                }

                // Small delay to respect API rate limits
                usleep(100000); // 100ms

            } catch (\Exception $e) {
                $stats['failed']++;
                $this->error("\nError processing component {$component->sku}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->components->info('Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $stats['processed']],
                ['Found', $stats['found']],
                ['Failed', $stats['failed']],
                ['Skipped', $stats['skipped']],
            ]
        );

        $this->newLine();

        if ($stats['found'] > 0) {
            $this->components->success("Successfully populated {$stats['found']} datasheet URL(s)!");
        }

        if ($stats['failed'] > 0) {
            $this->components->warn("Failed to find datasheet for {$stats['failed']} component(s)");
        }

        if ($stats['skipped'] > 0) {
            $this->components->info("Skipped {$stats['skipped']} component(s) (missing MPN/manufacturer or supplier links)");
        }

        // Show remaining components
        $remaining = $query->count();
        if ($remaining > 0) {
            $this->newLine();
            $this->components->info("There are still {$remaining} component(s) without datasheet URLs");
            $this->info("Run the command again to process more components");
        }

        return Command::SUCCESS;
    }
}
