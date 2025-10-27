<?php

namespace App\Observers;

use App\Models\BoardAssemblyLog;
use App\Services\AssemblyChecklistService;
use App\Services\BoardQrCodeService;
use Illuminate\Support\Facades\Log;

class BoardAssemblyLogObserver
{
    protected BoardQrCodeService $qrCodeService;
    protected AssemblyChecklistService $checklistService;

    public function __construct()
    {
        $this->qrCodeService = new BoardQrCodeService();
        $this->checklistService = new AssemblyChecklistService();
    }

    /**
     * Handle the BoardAssemblyLog "created" event.
     * Automatically generate QR codes and assembly checklist.
     */
    public function created(BoardAssemblyLog $boardAssemblyLog): void
    {
        try {
            // Generate QR codes asynchronously to avoid blocking
            // We'll dispatch a job or do it directly depending on needs
            $generatedCount = $this->qrCodeService->generateQrCodesForAssemblyLog($boardAssemblyLog);

            Log::info('QR codes auto-generated after assembly log creation', [
                'assembly_log_id' => $boardAssemblyLog->id,
                'generated_count' => $generatedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to auto-generate QR codes after assembly log creation', [
                'assembly_log_id' => $boardAssemblyLog->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Auto-generate assembly checklist
        try {
            $checklist = $this->checklistService->generateChecklistForAssembly($boardAssemblyLog);

            if ($checklist) {
                Log::info('Assembly checklist auto-generated after assembly log creation', [
                    'assembly_log_id' => $boardAssemblyLog->id,
                    'checklist_id' => $checklist->id,
                    'total_items' => $checklist->total_items,
                ]);
            } else {
                Log::warning('Assembly checklist generation returned null', [
                    'assembly_log_id' => $boardAssemblyLog->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to auto-generate assembly checklist', [
                'assembly_log_id' => $boardAssemblyLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't fail the entire creation - checklist can be generated manually later
        }
    }

    /**
     * Handle the BoardAssemblyLog "updated" event.
     * Regenerate QR codes if boards_count or batch_number changed.
     */
    public function updated(BoardAssemblyLog $boardAssemblyLog): void
    {
        try {
            // Check if fields that affect QR codes were changed
            if ($boardAssemblyLog->wasChanged(['boards_count', 'batch_number'])) {
                Log::info('Regenerating QR codes due to assembly log update', [
                    'assembly_log_id' => $boardAssemblyLog->id,
                    'changes' => $boardAssemblyLog->getChanges(),
                ]);

                $generatedCount = $this->qrCodeService->regenerateQrCodes($boardAssemblyLog);

                Log::info('QR codes regenerated after assembly log update', [
                    'assembly_log_id' => $boardAssemblyLog->id,
                    'generated_count' => $generatedCount,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to regenerate QR codes after assembly log update', [
                'assembly_log_id' => $boardAssemblyLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the BoardAssemblyLog "deleted" event.
     * QR codes will be automatically deleted via cascade foreign key.
     */
    public function deleted(BoardAssemblyLog $boardAssemblyLog): void
    {
        // QR codes are deleted automatically via cascadeOnDelete in migration
        Log::info('Board assembly log deleted, QR codes deleted via cascade', [
            'assembly_log_id' => $boardAssemblyLog->id,
        ]);
    }

    /**
     * Handle the BoardAssemblyLog "restored" event.
     */
    public function restored(BoardAssemblyLog $boardAssemblyLog): void
    {
        // If we support soft deletes and restore, regenerate QR codes
        try {
            $generatedCount = $this->qrCodeService->generateQrCodesForAssemblyLog($boardAssemblyLog);

            Log::info('QR codes regenerated after assembly log restoration', [
                'assembly_log_id' => $boardAssemblyLog->id,
                'generated_count' => $generatedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to regenerate QR codes after assembly log restoration', [
                'assembly_log_id' => $boardAssemblyLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the BoardAssemblyLog "force deleted" event.
     */
    public function forceDeleted(BoardAssemblyLog $boardAssemblyLog): void
    {
        // Cascade delete handles QR codes
        Log::info('Board assembly log force deleted', [
            'assembly_log_id' => $boardAssemblyLog->id,
        ]);
    }
}
