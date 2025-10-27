<?php

namespace App\Jobs;

use App\Models\Component;
use App\Services\DatasheetScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnrichComponentsWithDatasheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 1;

    protected string $jobId;
    protected int $userId;
    protected ?array $filters;

    public function __construct(
        string $jobId = null,
        int $userId = null,
        ?array $filters = null
    ) {
        $this->jobId = $jobId ?? uniqid('enrich_', true);
        $this->userId = $userId ?? auth()->id();
        $this->filters = $filters;
    }

    public function handle()
    {
        $this->updateProgress(0, 0, 'Avvio arricchimento...');
        $this->addToEnrichJobsList();
        $this->addLog('🚀 Arricchimento componenti avviato');

        $scraperService = app(DatasheetScraperService::class);

        if (!$scraperService->isEnabled()) {
            $this->addLog('❌ Datasheet Scraper Service non disponibile');
            $this->markAsFailed('Datasheet Scraper Service non disponibile');
            return;
        }

        // Build query for components to enrich
        $query = Component::whereNotNull('manufacturer_part_number');

        // Apply filters if provided
        if (!empty($this->filters['missing_specs_only'])) {
            $query->where(function ($q) {
                $q->whereNull('package_type')
                  ->orWhereNull('value')
                  ->orWhereNull('voltage_rating')
                  ->orWhereNull('mounting_type');
            });
        }

        if (!empty($this->filters['category_id'])) {
            $query->where('category_id', $this->filters['category_id']);
        }

        $components = $query->get();
        $total = $components->count();

        $this->addLog("📊 Trovati {$total} componenti da processare");

        if ($total === 0) {
            $this->addLog('⚠️ Nessun componente da arricchire');
            $this->markAsCompleted([
                'enriched' => 0,
                'skipped' => 0,
                'failed' => 0,
                'total' => 0,
            ]);
            return;
        }

        $results = [
            'enriched' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total' => $total,
            'enriched_details' => [],
            'skipped_details' => [],
            'failed_details' => [],
        ];

        $current = 0;

        foreach ($components as $component) {
            $current++;

            try {
                $this->updateProgress($current, $total, "Processando {$current}/{$total}: {$component->sku}");

                // Extract specifications
                $specs = $scraperService->extractSpecifications($component);

                if (empty($specs)) {
                    $results['skipped']++;
                    $results['skipped_details'][] = [
                        'sku' => $component->sku,
                        'mpn' => $component->manufacturer_part_number,
                        'reason' => 'Nessuna specifica estratta'
                    ];
                    $this->addLog("⏭️ Saltato: {$component->sku} - Nessuna specifica disponibile");
                    continue;
                }

                // Only update empty fields
                $toUpdate = [];
                foreach ($specs as $field => $value) {
                    if (empty($component->$field) && !empty($value)) {
                        $toUpdate[$field] = $value;
                    }
                }

                if (empty($toUpdate)) {
                    $results['skipped']++;
                    $results['skipped_details'][] = [
                        'sku' => $component->sku,
                        'mpn' => $component->manufacturer_part_number,
                        'reason' => 'Tutti i campi già popolati'
                    ];
                    $this->addLog("⏭️ Saltato: {$component->sku} - Campi già popolati");
                    continue;
                }

                // Update component
                $component->update($toUpdate);
                $results['enriched']++;

                $results['enriched_details'][] = [
                    'sku' => $component->sku,
                    'mpn' => $component->manufacturer_part_number,
                    'fields_updated' => array_keys($toUpdate),
                    'specs' => $toUpdate
                ];

                $fieldsStr = implode(', ', array_keys($toUpdate));
                $this->addLog("✅ Arricchito: {$component->sku} - Campi: {$fieldsStr}");

            } catch (\Exception $e) {
                $results['failed']++;
                $results['failed_details'][] = [
                    'sku' => $component->sku,
                    'mpn' => $component->manufacturer_part_number,
                    'error' => $e->getMessage()
                ];
                $this->addLog("❌ Errore: {$component->sku} - {$e->getMessage()}");

                Log::error('Component enrichment failed', [
                    'sku' => $component->sku,
                    'error' => $e->getMessage()
                ]);
            }

            // Update progress every 5 components
            if ($current % 5 === 0) {
                $this->updateProgress($current, $total, "Processati {$current}/{$total} componenti");
            }
        }

        $this->updateProgress(100, 100, 'Arricchimento completato!');
        $this->addLog('✅ Arricchimento completato con successo');
        $this->addLog("📊 RIEPILOGO:");
        $this->addLog("   ✅ Arricchiti: {$results['enriched']}");
        $this->addLog("   ⏭️ Saltati: {$results['skipped']}");
        $this->addLog("   ❌ Errori: {$results['failed']}");

        // Show enriched details
        if (!empty($results['enriched_details'])) {
            $this->addLog("");
            $this->addLog("✅ COMPONENTI ARRICCHITI:");
            foreach (array_slice($results['enriched_details'], 0, 10) as $detail) {
                $this->addLog("   • {$detail['sku']} - {$detail['mpn']}");
                $this->addLog("     Campi aggiornati: " . implode(', ', $detail['fields_updated']));
            }
            if (count($results['enriched_details']) > 10) {
                $remaining = count($results['enriched_details']) - 10;
                $this->addLog("   ... e altri {$remaining} componenti");
            }
        }

        // Show skipped details (sample)
        if (!empty($results['skipped_details'])) {
            $this->addLog("");
            $this->addLog("⏭️ COMPONENTI SALTATI (primi 5):");
            foreach (array_slice($results['skipped_details'], 0, 5) as $detail) {
                $this->addLog("   • {$detail['sku']} - {$detail['mpn']}");
                $this->addLog("     Motivo: {$detail['reason']}");
            }
            if (count($results['skipped_details']) > 5) {
                $remaining = count($results['skipped_details']) - 5;
                $this->addLog("   ... e altri {$remaining} componenti");
            }
        }

        // Show failed details
        if (!empty($results['failed_details'])) {
            $this->addLog("");
            $this->addLog("❌ COMPONENTI CON ERRORI:");
            foreach ($results['failed_details'] as $detail) {
                $this->addLog("   • {$detail['sku']} - {$detail['mpn']}");
                $this->addLog("     Errore: {$detail['error']}");
            }
        }

        $this->markAsCompleted($results);
    }

    protected function addLog(string $message): void
    {
        $logs = Cache::get("enrich_logs_{$this->jobId}", []);
        $logs[] = [
            'timestamp' => now()->toIso8601String(),
            'message' => $message
        ];
        // Keep last 200 logs
        if (count($logs) > 200) {
            array_shift($logs);
        }
        Cache::put("enrich_logs_{$this->jobId}", $logs, 7200);
    }

    protected function updateProgress(int $current, int $total, string $message)
    {
        Cache::put("enrich_progress_{$this->jobId}", [
            'current' => $current,
            'total' => $total,
            'percentage' => $total > 0 ? round(($current / $total) * 100) : 0,
            'message' => $message,
            'status' => 'processing',
            'updated_at' => now()->toIso8601String(),
        ], 7200); // 2 hours
    }

    protected function markAsCompleted(array $result)
    {
        Cache::put("enrich_progress_{$this->jobId}", [
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now()->toIso8601String(),
            'user_id' => $this->userId,
        ], 7200);

        $this->addToEnrichJobsList();

        Log::info('Enrichment job completed', [
            'job_id' => $this->jobId,
            'result' => $result
        ]);
    }

    protected function markAsFailed(string $error)
    {
        Cache::put("enrich_progress_{$this->jobId}", [
            'status' => 'failed',
            'error' => $error,
            'failed_at' => now()->toIso8601String(),
            'user_id' => $this->userId,
        ], 7200);

        $this->addToEnrichJobsList();

        Log::error('Enrichment job failed', [
            'job_id' => $this->jobId,
            'error' => $error
        ]);
    }

    protected function addToEnrichJobsList(): void
    {
        $allJobs = Cache::get('enrich_jobs_list', []);

        if (!in_array($this->jobId, $allJobs)) {
            $allJobs[] = $this->jobId;
            // Keep last 50 jobs
            if (count($allJobs) > 50) {
                array_shift($allJobs);
            }
            Cache::put('enrich_jobs_list', $allJobs, 86400); // 24 hours
        }
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
