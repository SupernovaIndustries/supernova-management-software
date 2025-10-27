<?php

namespace App\Observers;

use App\Models\CustomerContract;
use App\Services\PdfGeneratorService;
use App\Services\ContractAnalysisService;
use Illuminate\Support\Facades\Log;

class CustomerContractObserver
{
    /**
     * Handle the CustomerContract "created" event.
     */
    public function created(CustomerContract $contract): void
    {
        // PDF generation is now handled manually via actions
        // or automatically when contract is signed
        Log::info("Contract created: {$contract->contract_number}");
    }

    /**
     * Handle the CustomerContract "updated" event.
     */
    public function updated(CustomerContract $contract): void
    {
        // Auto-generate PDF when contract is signed
        if ($contract->isDirty('signed_at') && $contract->signed_at) {
            try {
                $pdfService = app(PdfGeneratorService::class);
                $pdfService->generateCustomerContractPdf($contract, uploadToNextcloud: true);

                Log::info("Auto-generated PDF for contract {$contract->contract_number}");
            } catch (\Exception $e) {
                Log::error("Failed to auto-generate PDF for contract {$contract->id}: " . $e->getMessage());
            }
        }

        // Auto-trigger AI analysis when:
        // 1. nextcloud_path is set for the first time
        // 2. status changes from draft to active
        $shouldAnalyze = false;
        $reason = '';

        if ($contract->isDirty('nextcloud_path') && !empty($contract->nextcloud_path) && empty($contract->getOriginal('nextcloud_path'))) {
            $shouldAnalyze = true;
            $reason = 'PDF caricato';
        } elseif ($contract->isDirty('status') && $contract->status === 'active' && $contract->getOriginal('status') === 'draft') {
            $shouldAnalyze = true;
            $reason = 'Status cambiato a active';
        }

        if ($shouldAnalyze && !$contract->isAnalyzed()) {
            try {
                $analysisService = app(ContractAnalysisService::class);

                // Verifica che il contratto possa essere analizzato
                if ($analysisService->canAnalyze($contract)) {
                    $pdfPath = $analysisService->getContractPdfPath($contract);

                    if ($pdfPath && file_exists($pdfPath)) {
                        // Esegui l'analisi
                        $analysisData = $analysisService->analyzeContractPdf($contract, $pdfPath);

                        // Salva i risultati (senza triggare nuovamente l'observer)
                        $contract->updateQuietly($analysisData);

                        Log::info("Auto-analyzed contract {$contract->contract_number} - Reason: {$reason}");
                    } else {
                        Log::warning("Cannot auto-analyze contract {$contract->contract_number}: PDF file not found at path");
                    }
                }
            } catch (\Exception $e) {
                // Log l'errore ma non bloccare l'update
                Log::error("Failed to auto-analyze contract {$contract->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
