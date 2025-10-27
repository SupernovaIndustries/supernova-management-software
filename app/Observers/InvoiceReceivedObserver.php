<?php

namespace App\Observers;

use App\Models\InvoiceReceived;
use App\Services\NextcloudService;
use Illuminate\Support\Facades\Log;

class InvoiceReceivedObserver
{
    protected NextcloudService $nextcloudService;

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Handle the InvoiceReceived "created" event.
     */
    public function created(InvoiceReceived $invoice): void
    {
        try {
            $pdfPath = $this->getInvoicePdfPath($invoice);

            if ($pdfPath && file_exists($pdfPath)) {
                // Determine upload location based on type
                $uploaded = false;
                $nextcloudPath = '';

                if ($invoice->category === 'components' ||
                    $invoice->category === 'customs' ||
                    $invoice->category === 'equipment' ||
                    $invoice->category === 'general') {

                    // Upload to warehouse
                    $uploaded = $this->nextcloudService->uploadWarehouseInvoice($invoice, $pdfPath);

                    if ($uploaded) {
                        $year = $invoice->issue_date->format('Y');
                        $supplierName = $invoice->supplier->name ?? $invoice->supplier_name;
                        $date = $invoice->issue_date->format('Y-m-d');

                        $subfolder = match($invoice->category) {
                            'components' => 'Fornitori',
                            'customs' => 'Dogana',
                            'equipment' => 'Macchinari',
                            default => 'Generali',
                        };

                        $nextcloudPath = "Magazzino/Fatture_Magazzino/{$subfolder}/{$year}/{$supplierName}_{$date}.pdf";

                        // Generate components JSON if type = components
                        if ($invoice->category === 'components') {
                            $this->nextcloudService->generateInvoiceComponentsJson($invoice);
                        }
                    }
                } elseif ($invoice->customer_id && $invoice->customer) {
                    // Upload to customer folder if invoice is customer-specific
                    $uploaded = $this->nextcloudService->uploadInvoiceReceived($invoice, $pdfPath);

                    if ($uploaded) {
                        $year = $invoice->issue_date->format('Y');
                        $customerPath = $this->nextcloudService->getCustomerBasePath($invoice->customer);
                        $nextcloudPath = "{$customerPath}/04_Fatturazione/Fatture_Ricevute/{$year}/{$invoice->invoice_number}.pdf";
                    }
                }

                if ($uploaded) {
                    $invoice->update(['nextcloud_path' => $nextcloudPath]);
                    Log::info("Invoice received uploaded to Nextcloud: {$invoice->invoice_number}");
                }
            } else {
                Log::warning("PDF not found for invoice received: {$invoice->invoice_number}");
            }
        } catch (\Exception $e) {
            Log::error("InvoiceReceivedObserver::created error: " . $e->getMessage());
        }
    }

    /**
     * Handle the InvoiceReceived "updated" event.
     */
    public function updated(InvoiceReceived $invoice): void
    {
        try {
            // Re-upload if important fields change
            if ($invoice->isDirty(['total', 'payment_status']) && $invoice->nextcloud_path) {
                $pdfPath = $this->getInvoicePdfPath($invoice);

                if ($pdfPath && file_exists($pdfPath)) {
                    // Re-upload based on category
                    if ($invoice->category === 'components' ||
                        $invoice->category === 'customs' ||
                        $invoice->category === 'equipment' ||
                        $invoice->category === 'general') {

                        $this->nextcloudService->uploadWarehouseInvoice($invoice, $pdfPath);
                    } elseif ($invoice->customer_id) {
                        $this->nextcloudService->uploadInvoiceReceived($invoice, $pdfPath);
                    }

                    Log::info("Invoice received re-uploaded to Nextcloud: {$invoice->invoice_number}");
                }
            }
        } catch (\Exception $e) {
            Log::error("InvoiceReceivedObserver::updated error: " . $e->getMessage());
        }
    }

    /**
     * Handle the InvoiceReceived "deleted" event.
     */
    public function deleted(InvoiceReceived $invoice): void
    {
        try {
            // Note: PDF files are kept in Nextcloud even if invoice is deleted from DB
            // This is intentional for audit/compliance purposes
            Log::info("Invoice received deleted from DB (Nextcloud copy preserved): {$invoice->invoice_number}");
        } catch (\Exception $e) {
            Log::error("InvoiceReceivedObserver::deleted error: " . $e->getMessage());
        }
    }

    /**
     * Get invoice PDF path (assuming it's stored or uploaded)
     */
    protected function getInvoicePdfPath(InvoiceReceived $invoice): ?string
    {
        // Check if invoice has attached PDF
        if ($invoice->pdf_path && file_exists($invoice->pdf_path)) {
            return $invoice->pdf_path;
        }

        // Check in storage
        $storagePath = storage_path("app/invoices/received/{$invoice->invoice_number}.pdf");
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        return null;
    }
}
