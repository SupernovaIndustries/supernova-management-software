<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Services\DocumentService;
use App\Services\NextcloudService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    /**
     * Mutate form data before creating the record.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store items temporarily (they'll be handled in afterCreate)
        if (isset($data['items'])) {
            $this->cachedItems = $data['items'];
            unset($data['items']);
        }

        return $data;
    }

    /**
     * Handle after create logic.
     */
    protected function afterCreate(): void
    {
        $quotation = $this->record;

        Log::info('AfterCreate called for quotation', [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->number,
            'has_cached_items' => isset($this->cachedItems) && !empty($this->cachedItems),
            'cached_items_count' => isset($this->cachedItems) ? count($this->cachedItems) : 0
        ]);

        // Use database transaction for consistency
        DB::beginTransaction();

        try {
            // Save items if provided
            if (isset($this->cachedItems) && !empty($this->cachedItems)) {
                Log::info('Saving quotation items', ['count' => count($this->cachedItems)]);

                foreach ($this->cachedItems as $index => $itemData) {
                    $itemData['sort_order'] = $index;
                    $quotation->items()->create($itemData);
                }

                // Recalculate totals after adding items
                $quotation->fresh();
                $quotation->calculateTotals();
                $quotation->save();

                Log::info('Items saved and totals calculated', [
                    'total' => $quotation->total,
                    'items_count' => $quotation->items->count()
                ]);
            }

            // Auto-generate PDF if items were added
            if (isset($this->cachedItems) && !empty($this->cachedItems)) {
                Log::info('Triggering PDF generation');
                $this->generateAndUploadPdf($quotation);
            } else {
                Log::info('PDF generation skipped - no items');
            }

            DB::commit();

            // Show success notification
            if ($quotation->pdf_path) {
                Notification::make()
                    ->title('Preventivo creato con successo!')
                    ->success()
                    ->body('Il preventivo è stato creato e il PDF è stato generato automaticamente.')
                    ->send();
            }

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating quotation with items', [
                'error' => $e->getMessage(),
                'quotation_id' => $quotation->id
            ]);

            // Show error notification but don't fail the creation
            Notification::make()
                ->title('Attenzione')
                ->warning()
                ->body('Il preventivo è stato creato ma si è verificato un errore durante la generazione del PDF. Puoi generarlo manualmente.')
                ->send();
        }
    }

    /**
     * Generate PDF and upload to Nextcloud.
     */
    protected function generateAndUploadPdf($quotation): void
    {
        try {
            $documentService = app(DocumentService::class);
            $pdfPath = $documentService->generateQuotationPdf($quotation);

            Log::info("Quotation PDF generated locally", [
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->number,
                'pdf_path' => $pdfPath
            ]);

            if ($pdfPath && file_exists($pdfPath)) {
                // Upload to Nextcloud
                $nextcloudService = app(NextcloudService::class);

                Log::info("Attempting to upload quotation PDF to Nextcloud", [
                    'quotation_id' => $quotation->id,
                    'customer' => $quotation->customer->company_name ?? 'N/A'
                ]);

                $uploaded = $nextcloudService->uploadQuotation($quotation, $pdfPath);

                if ($uploaded) {
                    $customerPath = $nextcloudService->getCustomerBasePath($quotation->customer);
                    $statusFolder = match($quotation->status) {
                        'draft' => 'Bozze',
                        'sent' => 'Inviati',
                        'accepted' => 'Accettati',
                        'rejected' => 'Rifiutati',
                        'expired' => 'Scaduti',
                        default => 'Bozze',
                    };

                    $nextcloudPath = "{$customerPath}/01_Preventivi/{$statusFolder}/preventivo-{$quotation->number}.pdf";

                    $quotation->update([
                        'pdf_path' => $pdfPath,
                        'pdf_generated_at' => now(),
                        'nextcloud_path' => $nextcloudPath
                    ]);

                    Log::info("Quotation PDF uploaded to Nextcloud successfully", [
                        'quotation_id' => $quotation->id,
                        'nextcloud_path' => $nextcloudPath
                    ]);
                } else {
                    Log::error("Failed to upload quotation PDF to Nextcloud", [
                        'quotation_id' => $quotation->id,
                        'pdf_path' => $pdfPath
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log but don't fail - PDF generation is not critical
            Log::error('Failed to auto-generate PDF on quotation create', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'quotation_id' => $quotation->id
            ]);
        }
    }

    /**
     * Cached items from form data.
     */
    protected array $cachedItems = [];
}
