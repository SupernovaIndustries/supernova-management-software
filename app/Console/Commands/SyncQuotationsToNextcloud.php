<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Quotation;
use App\Services\NextcloudService;
use App\Services\DocumentService;

class SyncQuotationsToNextcloud extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quotations:sync-nextcloud {--quotation-id= : Sync specific quotation ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync quotations PDFs to Nextcloud (creates customer folders and uploads PDFs)';

    protected NextcloudService $nextcloudService;
    protected DocumentService $documentService;

    public function __construct()
    {
        parent::__construct();
        $this->nextcloudService = new NextcloudService();
        $this->documentService = new DocumentService();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $quotationId = $this->option('quotation-id');

        if ($quotationId) {
            // Sync specific quotation
            $quotation = Quotation::find($quotationId);

            if (!$quotation) {
                $this->error("Quotation #{$quotationId} not found!");
                return 1;
            }

            $this->syncQuotation($quotation);
        } else {
            // Sync all quotations with PDFs but no nextcloud_path
            $quotations = Quotation::whereNotNull('pdf_path')
                ->where(function($q) {
                    $q->whereNull('nextcloud_path')
                      ->orWhere('nextcloud_path', '');
                })
                ->with('customer')
                ->get();

            if ($quotations->isEmpty()) {
                $this->info('No quotations to sync!');
                return 0;
            }

            $this->info("Found {$quotations->count()} quotations to sync...");

            $bar = $this->output->createProgressBar($quotations->count());
            $bar->start();

            $synced = 0;
            $failed = 0;

            foreach ($quotations as $quotation) {
                if ($this->syncQuotation($quotation, false)) {
                    $synced++;
                } else {
                    $failed++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ Synced: {$synced}");
            if ($failed > 0) {
                $this->warn("⚠️  Failed: {$failed}");
            }
        }

        return 0;
    }

    private function syncQuotation(Quotation $quotation, bool $verbose = true): bool
    {
        try {
            if ($verbose) {
                $this->info("Syncing quotation: {$quotation->number}");
            }

            // 1. Ensure customer has Nextcloud folder
            if (!$quotation->customer->nextcloud_folder_created) {
                if ($verbose) {
                    $this->line("  Creating customer folder...");
                }
                $this->nextcloudService->createCustomerFolderStructure($quotation->customer);
                $this->nextcloudService->generateCustomerInfoJson($quotation->customer);
                $quotation->customer->update(['nextcloud_folder_created' => true]);
            }

            // 2. Upload PDF if exists locally
            $pdfFullPath = storage_path('app/' . $quotation->pdf_path);
            if ($quotation->pdf_path && file_exists($pdfFullPath)) {
                if ($verbose) {
                    $this->line("  Uploading PDF to Nextcloud...");
                }

                $uploaded = $this->nextcloudService->uploadQuotation($quotation, $pdfFullPath);

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

                    if ($verbose) {
                        $this->info("  ✅ Synced successfully!");
                    }
                    return true;
                } else {
                    if ($verbose) {
                        $this->error("  ❌ Upload failed!");
                    }
                    return false;
                }
            } else {
                if ($verbose) {
                    $this->warn("  ⚠️  PDF file not found locally: {$pdfFullPath}");
                }
                return false;
            }
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  ❌ Error: " . $e->getMessage());
            }
            return false;
        }
    }
}
