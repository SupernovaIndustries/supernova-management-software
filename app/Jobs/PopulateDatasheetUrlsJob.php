<?php

namespace App\Jobs;

use App\Models\Component;
use App\Services\DatasheetScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PopulateDatasheetUrlsJob
 *
 * Background job to populate missing datasheet URLs for components.
 * Processes components in batches and uses supplier APIs to find datasheets.
 */
class PopulateDatasheetUrlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout in seconds (10 minutes)
     */
    public $timeout = 600;

    /**
     * Number of times to retry the job
     */
    public $tries = 3;

    /**
     * Batch size for processing components
     */
    protected int $batchSize;

    /**
     * Constructor
     *
     * @param int $batchSize Number of components to process (default: 50)
     */
    public function __construct(int $batchSize = 50)
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Execute the job
     */
    public function handle(DatasheetScraperService $scraperService): void
    {
        Log::info('PopulateDatasheetUrlsJob started', [
            'batch_size' => $this->batchSize,
            'started_at' => now()->toIso8601String(),
        ]);

        $stats = [
            'processed' => 0,
            'found' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            // Fetch components without datasheet URLs
            $components = Component::whereNull('datasheet_url')
                ->orWhere('datasheet_url', '')
                ->orderBy('updated_at', 'asc') // Process oldest first
                ->limit($this->batchSize)
                ->get();

            if ($components->isEmpty()) {
                Log::info('No components to process (all have datasheet URLs)');
                return;
            }

            Log::info('Processing components', [
                'total_count' => $components->count(),
            ]);

            foreach ($components as $component) {
                try {
                    $stats['processed']++;

                    // Skip components without manufacturer or MPN
                    if (empty($component->manufacturer_part_number) || empty($component->manufacturer)) {
                        Log::debug('Skipping component without MPN or manufacturer', [
                            'component_id' => $component->id,
                            'sku' => $component->sku,
                        ]);
                        $stats['skipped']++;
                        continue;
                    }

                    // Skip components without supplier links
                    $supplierLinks = $component->supplier_links ?? [];
                    if (empty($supplierLinks)) {
                        Log::debug('Skipping component without supplier links', [
                            'component_id' => $component->id,
                            'sku' => $component->sku,
                        ]);
                        $stats['skipped']++;
                        continue;
                    }

                    // Try to find datasheet URL
                    $datasheetUrl = $scraperService->findDatasheetUrl($component);

                    if ($datasheetUrl) {
                        // Update component with found datasheet URL
                        $component->datasheet_url = $datasheetUrl;
                        $component->save();

                        $stats['found']++;

                        Log::info('Datasheet URL populated', [
                            'component_id' => $component->id,
                            'sku' => $component->sku,
                            'url' => $datasheetUrl,
                        ]);
                    } else {
                        Log::debug('No datasheet URL found for component', [
                            'component_id' => $component->id,
                            'sku' => $component->sku,
                        ]);
                        $stats['failed']++;
                    }

                    // Small delay to respect API rate limits
                    usleep(100000); // 100ms delay between requests

                } catch (\Exception $e) {
                    $stats['failed']++;
                    $errorMessage = "Component {$component->id} ({$component->sku}): " . $e->getMessage();
                    $stats['errors'][] = $errorMessage;

                    Log::error('Error processing component', [
                        'component_id' => $component->id,
                        'sku' => $component->sku,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Continue processing other components
                    continue;
                }
            }

        } catch (\Exception $e) {
            Log::error('PopulateDatasheetUrlsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        // Log final statistics
        Log::info('PopulateDatasheetUrlsJob completed', [
            'stats' => $stats,
            'completed_at' => now()->toIso8601String(),
            'ai_validation_available' => $scraperService->isAiValidationAvailable(),
        ]);

        // Log summary
        $summary = sprintf(
            'Datasheet population completed: %d processed, %d found, %d failed, %d skipped',
            $stats['processed'],
            $stats['found'],
            $stats['failed'],
            $stats['skipped']
        );

        Log::info($summary, ['stats' => $stats]);

        // If there are errors, log them
        if (!empty($stats['errors'])) {
            Log::warning('Errors during datasheet population', [
                'error_count' => count($stats['errors']),
                'errors' => array_slice($stats['errors'], 0, 10), // Log first 10 errors
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PopulateDatasheetUrlsJob permanently failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'failed_at' => now()->toIso8601String(),
        ]);
    }
}
