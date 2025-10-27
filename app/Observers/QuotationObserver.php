<?php

namespace App\Observers;

use App\Models\Quotation;
use App\Models\Project;
use App\Services\NextcloudService;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Log;

class QuotationObserver
{
    protected NextcloudService $nextcloudService;
    protected DocumentService $documentService;

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
        $this->documentService = new DocumentService();
    }

    /**
     * Handle the Quotation "created" event.
     */
    public function created(Quotation $quotation): void
    {
        set_time_limit(300); // 5 minutes for folder operations

        $this->updateProjectBoardCounts($quotation);

        // Ensure customer has Nextcloud folder - CREATE IT IF MISSING
        if (!$quotation->customer->nextcloud_folder_created) {
            Log::info("Creating customer folder for quotation: {$quotation->number}");
            $this->nextcloudService->createCustomerFolderStructure($quotation->customer);
            $this->nextcloudService->generateCustomerInfoJson($quotation->customer);
            $quotation->customer->update(['nextcloud_folder_created' => true]);
        }

        // If PDF was uploaded manually, upload to Nextcloud
        if ($quotation->pdf_uploaded_manually && $quotation->pdf_path && file_exists($quotation->pdf_path)) {
            $this->uploadQuotationPdf($quotation);
        }

        // Auto-generate PDF on create if items exist and no PDF yet
        // Note: The CreateQuotation page handles this in afterCreate(), but this observer
        // provides a fallback for programmatic creation or if the page hook fails
        if ($quotation->items()->count() > 0 &&
            !$quotation->pdf_path &&
            !$quotation->pdf_uploaded_manually) {

            Log::info("Observer: Auto-generating PDF for quotation with items", [
                'quotation_id' => $quotation->id,
                'items_count' => $quotation->items()->count()
            ]);

            $this->generateAndUploadPdf($quotation);
        }
    }

    /**
     * Handle the Quotation "updated" event.
     */
    public function updated(Quotation $quotation): void
    {
        set_time_limit(300); // 5 minutes for folder operations

        $this->updateProjectBoardCounts($quotation);

        // Check if status changed
        if ($quotation->isDirty('status')) {
            $oldStatus = $quotation->getOriginal('status');
            $newStatus = $quotation->status;

            // Auto-generate PDF if status changed from draft and no PDF exists
            if ($oldStatus === 'draft' && !$quotation->pdf_path && !$quotation->pdf_uploaded_manually) {
                $this->generateAndUploadPdf($quotation);
            }

            // Move PDF to correct folder based on new status
            if ($quotation->pdf_path || $quotation->nextcloud_path) {
                $this->nextcloudService->moveQuotationPdf($quotation, $oldStatus, $newStatus);

                // Update nextcloud_path
                $customerPath = $this->nextcloudService->getCustomerBasePath($quotation->customer);
                $statusFolder = match($newStatus) {
                    'draft' => 'Bozze',
                    'sent' => 'Inviati',
                    'accepted' => 'Accettati',
                    'rejected' => 'Rifiutati',
                    'expired' => 'Scaduti',
                    default => 'Bozze',
                };
                $quotation->update([
                    'nextcloud_path' => "{$customerPath}/01_Preventivi/{$statusFolder}/preventivo-{$quotation->number}.pdf"
                ]);
            }

            // Create symlink in project folder when accepted and has project
            if ($newStatus === 'accepted') {
                $this->handleAcceptedQuotation($quotation);
            }
        }

        // If PDF manually uploaded, process it
        if ($quotation->isDirty('pdf_path') && $quotation->pdf_uploaded_manually) {
            $this->uploadQuotationPdf($quotation);
        }
    }

    /**
     * Handle the Quotation "deleted" event.
     */
    public function deleted(Quotation $quotation): void
    {
        $this->updateProjectBoardCounts($quotation);

        // Note: PDF files are kept in Nextcloud even if quotation deleted from DB
        // This is intentional for audit purposes
        Log::info("Quotation deleted from DB (Nextcloud copy preserved): {$quotation->number}");
    }

    /**
     * Update board counts for related projects.
     */
    private function updateProjectBoardCounts(Quotation $quotation): void
    {
        $projectsToUpdate = collect();

        // Add direct project relation
        if ($quotation->project_id) {
            $project = Project::find($quotation->project_id);
            if ($project) {
                $projectsToUpdate->push($project);
            }
        }

        // Add linked projects
        foreach ($quotation->linkedProjects as $project) {
            $projectsToUpdate->push($project);
        }

        // Update each unique project only once
        $projectsToUpdate->unique('id')->each(function ($project) {
            $project->updateTotalBoardsOrdered();
            $project->updateBudgetFromQuotations();
        });
    }

    /**
     * Generate PDF and upload to Nextcloud
     */
    private function generateAndUploadPdf(Quotation $quotation): void
    {
        try {
            // Generate PDF
            $pdfPath = $this->documentService->generateQuotationPdf($quotation);

            if ($pdfPath && file_exists($pdfPath)) {
                // Upload to Nextcloud
                $uploaded = $this->nextcloudService->uploadQuotation($quotation, $pdfPath);

                if ($uploaded) {
                    $customerPath = $this->nextcloudService->getCustomerBasePath($quotation->customer);
                    $statusFolder = match($quotation->status) {
                        'draft' => 'Bozze',
                        'sent' => 'Inviati',
                        'accepted' => 'Accettati',
                        'rejected' => 'Rifiutati',
                        'expired' => 'Scaduti',
                        default => 'Bozze',
                    };

                    $quotation->update([
                        'pdf_path' => $pdfPath,
                        'pdf_generated_at' => now(),
                        'nextcloud_path' => "{$customerPath}/01_Preventivi/{$statusFolder}/preventivo-{$quotation->number}.pdf"
                    ]);

                    Log::info("Quotation PDF auto-generated and uploaded: {$quotation->number}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Error auto-generating quotation PDF: " . $e->getMessage());
        }
    }

    /**
     * Upload existing PDF to Nextcloud
     */
    private function uploadQuotationPdf(Quotation $quotation): void
    {
        try {
            if (!$quotation->pdf_path || !file_exists($quotation->pdf_path)) {
                return;
            }

            $uploaded = $this->nextcloudService->uploadQuotation($quotation, $quotation->pdf_path);

            if ($uploaded) {
                $customerPath = $this->nextcloudService->getCustomerBasePath($quotation->customer);
                $statusFolder = match($quotation->status) {
                    'draft' => 'Bozze',
                    'sent' => 'Inviati',
                    'accepted' => 'Accettati',
                    'rejected' => 'Rifiutati',
                    'expired' => 'Scaduti',
                    default => 'Bozze',
                };

                $quotation->update([
                    'nextcloud_path' => "{$customerPath}/01_Preventivi/{$statusFolder}/preventivo-{$quotation->number}.pdf"
                ]);

                Log::info("Quotation PDF uploaded to Nextcloud: {$quotation->number}");
            }
        } catch (\Exception $e) {
            Log::error("Error uploading quotation PDF: " . $e->getMessage());
        }
    }

    /**
     * Handle accepted quotation - create symlink in project
     */
    private function handleAcceptedQuotation(Quotation $quotation): void
    {
        try {
            // Get all linked projects (direct + pivot)
            $projects = collect();

            if ($quotation->project_id) {
                $projects->push(Project::find($quotation->project_id));
            }

            foreach ($quotation->linkedProjects as $project) {
                $projects->push($project);
            }

            // Create symlink for each project
            $projects->filter()->unique('id')->each(function ($project) use ($quotation) {
                $this->nextcloudService->createQuotationSymlink($quotation, $project);
            });
        } catch (\Exception $e) {
            Log::error("Error handling accepted quotation: " . $e->getMessage());
        }
    }
}