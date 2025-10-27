<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentService
{
    /**
     * Generate quotation PDF
     */
    public function generateQuotationPdf(Quotation $quotation): string
    {
        $data = [
            'quotation' => $quotation,
            'customer' => $quotation->customer,
            'items' => $quotation->items,
            'company' => $this->getCompanyInfo(),
        ];

        $pdf = PDF::loadView('pdf.quotation', $data);

        // Calculate payment split
        $quotation->materials_deposit = $quotation->total * 0.4; // 40% deposit
        $quotation->development_balance = $quotation->total * 0.6; // 60% balance
        $quotation->save();

        // Generate filename: preventivo-{number}-dd-mm-yyyy.pdf
        $date = now()->format('d-m-Y');
        $filename = "preventivo-{$quotation->number}-{$date}.pdf";

        // Save to temporary storage, let caller handle Nextcloud upload
        $tempPath = storage_path("app/temp/quotations/{$filename}");

        // Ensure directory exists
        $directory = dirname($tempPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Save file
        file_put_contents($tempPath, $pdf->output());

        return $tempPath;
    }

    /**
     * Generate DDT (Documento di Trasporto) PDF
     */
    public function generateDdtPdf(Project $project): string
    {
        // Get next DDT number
        $lastDdt = $this->getLastDdtNumber();
        $ddtNumber = str_pad($lastDdt + 1, 3, '0', STR_PAD_LEFT);
        $date = now()->format('dmy');

        $data = [
            'project' => $project,
            'customer' => $project->customer,
            'ddtNumber' => $ddtNumber,
            'date' => now(),
            'items' => $this->getProjectDeliveryItems($project),
            'company' => $this->getCompanyInfo(),
        ];

        $pdf = PDF::loadView('pdf.ddt', $data);

        // Generate filename: ddt-001-290425.pdf
        $filename = "ddt-{$ddtNumber}-{$date}.pdf";

        // Save to customer folder
        $path = $this->saveToCustomerFolder(
            $project->customer,
            "Documenti di Trasporto/{$filename}",
            $pdf->output()
        );

        // Update project status
        $project->update(['project_status' => 'consegna_prototipo']);

        return $path;
    }

    /**
     * Save file to customer folder
     */
    protected function saveToCustomerFolder(Customer $customer, string $relativePath, string $content): string
    {
        if (!$customer->folder) {
            throw new \Exception('Customer folder not set');
        }

        $disk = app('syncthing.paths')->disk('clients');
        $fullPath = $customer->folder . '/' . $relativePath;

        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        // Save file
        $disk->put($fullPath, $content);

        return $fullPath;
    }

    /**
     * Get last DDT number from database or files
     */
    protected function getLastDdtNumber(): int
    {
        // TODO: Implement logic to track DDT numbers
        // For now, scan existing DDT files
        try {
            $disk = app('syncthing.paths')->disk('clients');
            $files = $disk->allFiles();
            
            $maxNumber = 0;
            foreach ($files as $file) {
                if (preg_match('/ddt-(\d{3})-\d{6}\.pdf$/i', $file, $matches)) {
                    $number = (int)$matches[1];
                    if ($number > $maxNumber) {
                        $maxNumber = $number;
                    }
                }
            }
            
            return $maxNumber;
        } catch (\Exception $e) {
            Log::warning('Failed to get last DDT number', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get project delivery items
     */
    protected function getProjectDeliveryItems(Project $project): array
    {
        $items = [];

        // Add PCB if exists
        if ($project->pcbFiles->count() > 0) {
            $items[] = [
                'description' => 'PCB Prototipo - ' . $project->name,
                'quantity' => 1,
                'notes' => 'Versione: ' . ($project->pcbFiles->first()->version ?? 'v1'),
            ];
        }

        // Add components if BOM is allocated
        $latestBom = $project->latestBom;
        if ($latestBom && $latestBom->isFullyAllocated()) {
            $items[] = [
                'description' => 'Componenti elettronici come da BOM allegata',
                'quantity' => $latestBom->items->sum('quantity'),
                'notes' => 'Riferimento BOM: ' . basename($latestBom->file_path ?? 'N/A'),
            ];
        }

        // Add documentation
        $items[] = [
            'description' => 'Documentazione tecnica',
            'quantity' => 1,
            'notes' => 'Schemi, datasheet e manuali',
        ];

        return $items;
    }

    /**
     * Get company information
     */
    protected function getCompanyInfo(): array
    {
        return [
            'name' => 'Supernova Industries S.r.l.',
            'address' => 'Via dell\'Innovazione, 123',
            'city' => '20100 Milano (MI)',
            'vat' => 'IT12345678901',
            'email' => 'info@supernovaindustries.it',
            'phone' => '+39 02 1234567',
            'pec' => 'supernova@pec.it',
            'sdi' => 'ABC1234',
        ];
    }
}