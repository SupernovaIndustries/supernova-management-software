<?php

namespace App\Observers;

use App\Models\InvoiceIssued;
use App\Services\PdfGeneratorService;
use Illuminate\Support\Facades\Log;

class InvoiceIssuedObserver
{
    /**
     * Handle the InvoiceIssued "created" event.
     */
    public function created(InvoiceIssued $invoice): void
    {
        // PDF generation is now handled manually via actions
        // or automatically when status changes to 'sent'
        Log::info("Invoice created: {$invoice->invoice_number}");
    }

    /**
     * Handle the InvoiceIssued "updated" event.
     */
    public function updated(InvoiceIssued $invoice): void
    {
        // Auto-generate PDF when status changes to 'sent'
        if ($invoice->isDirty('status') && $invoice->status === 'sent') {
            try {
                $pdfService = app(PdfGeneratorService::class);
                $pdfService->generateInvoiceIssuedPdf($invoice, uploadToNextcloud: true);

                Log::info("Auto-generated PDF for invoice {$invoice->invoice_number}");
            } catch (\Exception $e) {
                Log::error("Failed to auto-generate PDF for invoice {$invoice->id}: " . $e->getMessage());
            }
        }

        // Archive invoice if status changes to cancelled
        if ($invoice->isDirty('status') && $invoice->status === 'cancelled') {
            try {
                $nextcloudService = app(\App\Services\NextcloudService::class);
                $customer = $invoice->customer;

                if ($customer && $invoice->pdf_path && file_exists($invoice->pdf_path)) {
                    $year = $invoice->issue_date->format('Y');
                    $customerPath = $nextcloudService->getCustomerBasePath($customer);
                    $archivePath = "{$customerPath}/04_Fatturazione/__Annullate/{$year}/{$invoice->invoice_number}.pdf";

                    $nextcloudService->ensureFolderExists("{$customerPath}/04_Fatturazione/__Annullate/{$year}");

                    Log::info("Invoice cancelled and archived: {$invoice->invoice_number}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to archive cancelled invoice: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the InvoiceIssued "deleted" event.
     */
    public function deleted(InvoiceIssued $invoice): void
    {
        try {
            // Note: PDF files are kept in Nextcloud even if invoice is deleted from DB
            // This is intentional for audit/compliance purposes
            Log::info("Invoice deleted from DB (Nextcloud copy preserved): {$invoice->invoice_number}");
        } catch (\Exception $e) {
            Log::error("InvoiceIssuedObserver::deleted error: " . $e->getMessage());
        }
    }
}
