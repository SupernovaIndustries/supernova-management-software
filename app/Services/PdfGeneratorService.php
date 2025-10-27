<?php

namespace App\Services;

use App\Models\InvoiceIssued;
use App\Models\CustomerContract;
use App\Models\CompanyProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PdfGeneratorService
{
    protected NextcloudService $nextcloudService;

    public function __construct(NextcloudService $nextcloudService)
    {
        $this->nextcloudService = $nextcloudService;
    }

    /**
     * Generate PDF for Invoice Issued (Fattura Emessa)
     *
     * @param InvoiceIssued $invoice
     * @param bool $uploadToNextcloud
     * @return string Path to generated PDF
     */
    public function generateInvoiceIssuedPdf(InvoiceIssued $invoice, bool $uploadToNextcloud = true): string
    {
        try {
            // Load relationships
            $invoice->load([
                'customer',
                'project',
                'quotation',
                'items.component',
                'paymentTerm',
                'relatedInvoice'
            ]);

            // Get company profile
            $company = CompanyProfile::first();

            // Generate PDF from view
            $pdf = Pdf::loadView('pdf.invoice-issued', [
                'invoice' => $invoice,
                'company' => $company,
            ]);

            // Set PDF options
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', true);

            // Generate filename
            $filename = $this->generateInvoiceFilename($invoice);

            // Save to temporary storage
            $tempPath = storage_path('app/temp/' . $filename);

            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            // Save PDF
            $pdf->save($tempPath);

            // Upload to Nextcloud if requested
            if ($uploadToNextcloud && $invoice->customer) {
                $nextcloudPath = $this->nextcloudService->uploadInvoiceIssued($invoice, $tempPath);

                // Update invoice with Nextcloud path
                $invoice->update([
                    'nextcloud_path' => $nextcloudPath,
                    'pdf_generated_at' => now(),
                ]);
            }

            Log::info("Invoice PDF generated successfully", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'path' => $tempPath
            ]);

            return $tempPath;

        } catch (\Exception $e) {
            Log::error("Failed to generate invoice PDF", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Generate PDF for Customer Contract
     *
     * @param CustomerContract $contract
     * @param bool $uploadToNextcloud
     * @return string Path to generated PDF
     */
    public function generateCustomerContractPdf(CustomerContract $contract, bool $uploadToNextcloud = true): string
    {
        try {
            // Load relationships
            $contract->load('customer');

            // Get company profile
            $company = CompanyProfile::first();

            // Generate PDF from view
            $pdf = Pdf::loadView('pdf.customer-contract', [
                'contract' => $contract,
                'company' => $company,
            ]);

            // Set PDF options
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', true);

            // Generate filename
            $filename = $this->generateContractFilename($contract);

            // Save to temporary storage
            $tempPath = storage_path('app/temp/' . $filename);

            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            // Save PDF
            $pdf->save($tempPath);

            // Upload to Nextcloud if requested
            if ($uploadToNextcloud && $contract->customer) {
                $nextcloudPath = $this->nextcloudService->uploadContract($contract, $tempPath);

                // Update contract with Nextcloud path
                $contract->update([
                    'nextcloud_path' => $nextcloudPath,
                    'pdf_generated_at' => now(),
                ]);
            }

            Log::info("Contract PDF generated successfully", [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'path' => $tempPath
            ]);

            return $tempPath;

        } catch (\Exception $e) {
            Log::error("Failed to generate contract PDF", [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Download PDF from Nextcloud
     *
     * @param string $nextcloudPath
     * @return string Temporary path to downloaded file
     */
    public function downloadFromNextcloud(string $nextcloudPath): string
    {
        // TODO: Implement download from Nextcloud
        // For now, return empty string
        return '';
    }

    /**
     * Generate filename for invoice PDF
     *
     * @param InvoiceIssued $invoice
     * @return string
     */
    protected function generateInvoiceFilename(InvoiceIssued $invoice): string
    {
        // Format: FATT-2025-001_2025-10-04.pdf
        $date = $invoice->issue_date->format('Y-m-d');
        return "{$invoice->invoice_number}_{$date}.pdf";
    }

    /**
     * Generate filename for contract PDF
     *
     * @param CustomerContract $contract
     * @return string
     */
    protected function generateContractFilename(CustomerContract $contract): string
    {
        // Format: CTR-2025-001_2025-10-04.pdf
        $date = $contract->start_date->format('Y-m-d');
        return "{$contract->contract_number}_{$date}.pdf";
    }

    /**
     * Get PDF stream for download (without saving)
     *
     * @param InvoiceIssued $invoice
     * @return \Illuminate\Http\Response
     */
    public function streamInvoiceIssuedPdf(InvoiceIssued $invoice)
    {
        $invoice->load([
            'customer',
            'project',
            'quotation',
            'items.component',
            'paymentTerm',
            'relatedInvoice'
        ]);

        $company = CompanyProfile::first();

        $pdf = Pdf::loadView('pdf.invoice-issued', [
            'invoice' => $invoice,
            'company' => $company,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = $this->generateInvoiceFilename($invoice);

        return $pdf->stream($filename);
    }

    /**
     * Get PDF download response
     *
     * @param InvoiceIssued $invoice
     * @return \Illuminate\Http\Response
     */
    public function downloadInvoiceIssuedPdf(InvoiceIssued $invoice)
    {
        $invoice->load([
            'customer',
            'project',
            'quotation',
            'items.component',
            'paymentTerm',
            'relatedInvoice'
        ]);

        $company = CompanyProfile::first();

        $pdf = Pdf::loadView('pdf.invoice-issued', [
            'invoice' => $invoice,
            'company' => $company,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = $this->generateInvoiceFilename($invoice);

        return $pdf->download($filename);
    }

    /**
     * Get contract PDF stream for download
     *
     * @param CustomerContract $contract
     * @return \Illuminate\Http\Response
     */
    public function streamCustomerContractPdf(CustomerContract $contract)
    {
        $contract->load('customer');
        $company = CompanyProfile::first();

        $pdf = Pdf::loadView('pdf.customer-contract', [
            'contract' => $contract,
            'company' => $company,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = $this->generateContractFilename($contract);

        return $pdf->stream($filename);
    }

    /**
     * Get contract PDF download response
     *
     * @param CustomerContract $contract
     * @return \Illuminate\Http\Response
     */
    public function downloadCustomerContractPdf(CustomerContract $contract)
    {
        $contract->load('customer');
        $company = CompanyProfile::first();

        $pdf = Pdf::loadView('pdf.customer-contract', [
            'contract' => $contract,
            'company' => $company,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = $this->generateContractFilename($contract);

        return $pdf->download($filename);
    }
}
