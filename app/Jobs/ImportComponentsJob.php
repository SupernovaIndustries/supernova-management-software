<?php

namespace App\Jobs;

use App\Models\ComponentImport;
use App\Services\ComponentImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportComponentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour
    public $tries = 1;

    protected string $filePath;
    protected string $supplier;
    protected ?array $fieldMapping;
    protected ?array $invoiceData;
    protected string $jobId;
    protected int $userId;

    public function __construct(
        string $filePath,
        string $supplier,
        ?array $fieldMapping = null,
        ?array $invoiceData = null,
        string $jobId = null,
        int $userId = null
    ) {
        $this->filePath = $filePath;
        $this->supplier = $supplier;
        $this->fieldMapping = $fieldMapping;
        $this->invoiceData = $invoiceData;
        $this->jobId = $jobId ?? uniqid('import_', true);
        $this->userId = $userId ?? auth()->id();
    }

    public function handle()
    {
        $this->updateProgress(0, 0, 'Avvio import...');
        $this->addToImportJobsList();
        $this->addLog('🚀 Import avviato');
        $this->addLog('📂 File: ' . basename($this->filePath));
        $this->addLog('🏭 Fornitore: ' . ucfirst($this->supplier));

        // Detect file type
        $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));

        // Create ComponentImport record to track this import
        $componentImport = ComponentImport::create([
            'user_id' => $this->userId,
            'job_id' => $this->jobId,
            'supplier' => $this->supplier,
            'original_filename' => basename($this->filePath),
            'file_type' => $extension,
            'invoice_number' => $this->invoiceData['invoice_number'] ?? null,
            'invoice_path' => $this->invoiceData['invoice_path'] ?? null,
            'invoice_date' => $this->invoiceData['invoice_date'] ?? null,
            'invoice_total' => $this->invoiceData['invoice_total'] ?? null,
            'destination_project_id' => $this->invoiceData['project_id'] ?? null,
            'status' => 'processing',
            'started_at' => now(),
            'field_mapping' => $this->fieldMapping,
        ]);

        $this->addLog('📊 Import ID: #' . $componentImport->id);

        $importService = app(ComponentImportService::class);

        // Set user ID for inventory movements (required for queued jobs)
        $importService->setUserId($this->userId);

        // Set import ID for tracking
        $importService->setImportId($componentImport->id);

        // Set progress callback
        $importService->setProgressCallback(function ($current, $total, $message) {
            $this->updateProgress($current, $total, $message);
            $this->addLog("⚙️ {$message}");
        });

        try {
            $isExcel = in_array($extension, ['xlsx', 'xls']);

            if ($isExcel) {
                $result = $importService->importFromExcel(
                    $this->filePath,
                    $this->supplier,
                    $this->fieldMapping,
                    $this->invoiceData
                );
            } else {
                $result = $importService->importFromCsv(
                    $this->filePath,
                    $this->supplier,
                    $this->fieldMapping,
                    $this->invoiceData
                );
            }

            // Update ComponentImport record with results
            $componentImport->update([
                'components_imported' => $result['imported'],
                'components_updated' => $result['updated'],
                'components_skipped' => $result['skipped'],
                'components_failed' => $result['failed'] ?? 0,
                'movements_created' => $result['movements_created'] ?? 0,
                'status' => 'completed',
                'completed_at' => now(),
                'import_details' => [
                    'imported_details' => $result['imported_details'] ?? [],
                    'updated_details' => $result['updated_details'] ?? [],
                    'skipped_details' => $result['skipped_details'] ?? [],
                    'errors' => $result['errors'] ?? [],
                ],
            ]);

            $this->updateProgress(100, 100, 'Import completato!');
            $this->addLog('✅ Import completato con successo');
            $this->addLog("📊 RIEPILOGO:");
            $this->addLog("   ✅ Importati: {$result['imported']}");
            $this->addLog("   ✏️ Aggiornati: {$result['updated']}");
            $this->addLog("   ⏭️ Saltati: {$result['skipped']}");
            if (isset($result['failed']) && $result['failed'] > 0) {
                $this->addLog("   ❌ Errori: {$result['failed']}");
            }

            // Show imported details
            if (!empty($result['imported_details'])) {
                $this->addLog("");
                $this->addLog("✅ COMPONENTI IMPORTATI:");
                foreach ($result['imported_details'] as $detail) {
                    $this->addLog("   • {$detail['sku']} - {$detail['mpn']}");
                    $this->addLog("     {$detail['description']}");
                    $this->addLog("     Categoria: {$detail['category']}");
                }
            }

            // Show updated details
            if (!empty($result['updated_details'])) {
                $this->addLog("");
                $this->addLog("✏️ COMPONENTI AGGIORNATI:");
                foreach ($result['updated_details'] as $detail) {
                    $this->addLog("   • {$detail['sku']} - {$detail['mpn']}");
                    $this->addLog("     {$detail['description']}");
                    $this->addLog("     Categoria: {$detail['category']}");
                }
            }

            // Show skipped details
            if (!empty($result['skipped_details'])) {
                $this->addLog("");
                $this->addLog("⏭️ COMPONENTI SALTATI:");
                foreach ($result['skipped_details'] as $detail) {
                    $this->addLog("   • Riga #{$detail['row']} - MPN: {$detail['mpn']}");
                    if (!empty($detail['description']) && $detail['description'] !== 'N/A') {
                        $this->addLog("     {$detail['description']}");
                    }
                    $this->addLog("     Motivo: {$detail['reason']}");
                }
            }

            if (!empty($result['errors'])) {
                $this->addLog("");
                $this->addLog("⚠️ Dettaglio errori:");
                foreach (array_slice($result['errors'], 0, 5) as $error) {
                    $this->addLog("   • {$error}");
                }
                if (count($result['errors']) > 5) {
                    $this->addLog("   ... e altri " . (count($result['errors']) - 5) . " errori");
                }
            }
            $this->markAsCompleted($result);

            // Clean up temp file
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
                $this->addLog('🗑️ File temporaneo eliminato');
            }

        } catch (\Exception $e) {
            // Update ComponentImport record with error
            $componentImport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $this->addLog('❌ Errore: ' . $e->getMessage());
            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    protected function addLog(string $message): void
    {
        $logs = Cache::get("import_logs_{$this->jobId}", []);
        $logs[] = [
            'timestamp' => now()->toIso8601String(),
            'message' => $message
        ];
        // Keep last 100 logs
        if (count($logs) > 100) {
            array_shift($logs);
        }
        Cache::put("import_logs_{$this->jobId}", $logs, 7200);
    }

    protected function updateProgress(int $current, int $total, string $message)
    {
        Cache::put("import_progress_{$this->jobId}", [
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
        Cache::put("import_progress_{$this->jobId}", [
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now()->toIso8601String(),
            'user_id' => $this->userId,
        ], 7200);

        // Update global import jobs list
        $this->addToImportJobsList();

        Log::info('Import job completed', [
            'job_id' => $this->jobId,
            'result' => $result
        ]);
    }

    protected function markAsFailed(string $error)
    {
        Cache::put("import_progress_{$this->jobId}", [
            'status' => 'failed',
            'error' => $error,
            'failed_at' => now()->toIso8601String(),
            'user_id' => $this->userId,
        ], 7200);

        // Update global import jobs list
        $this->addToImportJobsList();

        Log::error('Import job failed', [
            'job_id' => $this->jobId,
            'error' => $error
        ]);
    }

    /**
     * Add this job to the global import jobs list for monitoring
     */
    protected function addToImportJobsList(): void
    {
        $allJobs = Cache::get('import_jobs_list', []);

        if (!in_array($this->jobId, $allJobs)) {
            $allJobs[] = $this->jobId;
            // Keep last 50 jobs
            if (count($allJobs) > 50) {
                array_shift($allJobs);
            }
            Cache::put('import_jobs_list', $allJobs, 86400); // 24 hours
        }
    }

    /**
     * Get the job ID for tracking
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }
}
