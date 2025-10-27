<?php

namespace App\Services;

use App\Helpers\NextcloudHelper;
use App\Models\Customer;
use App\Models\Project;
use App\Models\InvoiceIssued;
use App\Models\InvoiceReceived;
use App\Models\F24Form;
use App\Models\CustomerContract;
use App\Models\Quotation;
use App\Models\ProjectDocument;
use App\Models\BillingAnalysis;
use App\Models\Component;
use Illuminate\Support\Facades\Log;

class NextcloudService
{
    protected NextcloudHelper $helper;

    public function __construct()
    {
        $this->helper = new NextcloudHelper();
    }

    // ==================== CUSTOMER FOLDER MANAGEMENT ====================

    /**
     * Create complete folder structure for customer
     */
    public function createCustomerFolderStructure(Customer $customer): bool
    {
        try {
            $basePath = $this->getCustomerBasePath($customer);

            $folders = [
                $basePath,
                "{$basePath}/01_Anagrafica",
                "{$basePath}/01_Anagrafica/Visura_Camerale",
                "{$basePath}/01_Anagrafica/Documenti_Identita",
                "{$basePath}/01_Anagrafica/Certificazioni",
                "{$basePath}/01_Anagrafica/Contratti",
                "{$basePath}/01_Preventivi",
                "{$basePath}/01_Preventivi/Bozze",
                "{$basePath}/01_Preventivi/Inviati",
                "{$basePath}/01_Preventivi/Accettati",
                "{$basePath}/01_Preventivi/Rifiutati",
                "{$basePath}/01_Preventivi/Scaduti",
                "{$basePath}/02_Comunicazioni",
                "{$basePath}/02_Comunicazioni/Email",
                "{$basePath}/02_Comunicazioni/Lettere",
                "{$basePath}/02_Comunicazioni/Verbali_Riunioni",
                "{$basePath}/03_Progetti",
                "{$basePath}/04_Fatturazione",
                "{$basePath}/04_Fatturazione/Fatture_Emesse",
                "{$basePath}/04_Fatturazione/Fatture_Ricevute",
                "{$basePath}/04_Fatturazione/F24",
                "{$basePath}/04_Fatturazione/Note_Credito",
                "{$basePath}/04_Fatturazione/Pagamenti",
                "{$basePath}/04_Fatturazione/Pagamenti/Ricevute",
                "{$basePath}/04_Fatturazione/Pagamenti/Bonifici",
            ];

            foreach ($folders as $folder) {
                if (!$this->ensureFolderExists($folder)) {
                    Log::error("Failed to create folder: {$folder}");
                    return false;
                }
            }

            Log::info("Customer folder structure created: {$basePath}");
            return true;

        } catch (\Exception $e) {
            Log::error("Error creating customer folder structure: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate customer info JSON file
     */
    public function generateCustomerInfoJson(Customer $customer): bool
    {
        try {
            // Ensure payment term relationship is loaded
            $customer->load('paymentTerm.tranches');

            $basePath = $this->getCustomerBasePath($customer);
            $jsonPath = "{$basePath}/_customer_info.json";

            // Prepare payment terms data
            $paymentTermsData = null;
            if ($customer->paymentTerm) {
                $paymentTerm = $customer->paymentTerm;

                if ($paymentTerm->tranches->isNotEmpty()) {
                    // Payment with tranches
                    $paymentTermsData = [
                        'type' => 'tranches',
                        'name' => $paymentTerm->name,
                        'tranches' => $paymentTerm->tranches->map(function ($tranche) {
                            return [
                                'name' => $tranche->name,
                                'percentage' => (float) $tranche->percentage,
                                'trigger' => $tranche->trigger_event,
                                'days_offset' => $tranche->days_offset,
                            ];
                        })->toArray(),
                    ];
                } else {
                    // Single payment
                    $paymentTermsData = [
                        'type' => 'single',
                        'name' => $paymentTerm->name,
                        'days' => $paymentTerm->days,
                    ];
                }
            }

            $data = [
                'customer_code' => $customer->code,
                'company_name' => $customer->company_name,
                'vat_number' => $customer->vat_number,
                'tax_code' => $customer->tax_code,
                'sdi_code' => $customer->sdi_code,
                'email' => $customer->email,
                'pec_email' => $customer->pec_email,
                'phone' => $customer->phone,
                'mobile' => $customer->mobile,
                'address' => $customer->address,
                'city' => $customer->city,
                'postal_code' => $customer->postal_code,
                'province' => $customer->province,
                'country' => $customer->country,
                'billing_email' => $customer->billing_email,
                'billing_contact' => $customer->billing_contact_name,
                'payment_terms' => $paymentTermsData,
                'credit_limit' => $customer->credit_limit ? (float) $customer->credit_limit : null,
                'created_at' => $customer->created_at?->toIso8601String(),
                'nextcloud_folder_created_at' => now()->toIso8601String(),
            ];

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return $this->helper->uploadContent($jsonContent, $jsonPath);

        } catch (\Exception $e) {
            Log::error("Error generating customer info JSON: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload invoice issued PDF
     */
    public function uploadInvoiceIssued(InvoiceIssued $invoice, string $pdfPath): bool
    {
        try {
            $customer = $invoice->customer;
            if (!$customer) {
                Log::error("Invoice has no customer");
                return false;
            }

            $year = $invoice->issue_date->format('Y');
            $customerPath = $this->getCustomerBasePath($customer);
            $remotePath = "{$customerPath}/04_Fatturazione/Fatture_Emesse/{$year}/{$invoice->invoice_number}.pdf";

            // Ensure year folder exists
            $this->ensureFolderExists("{$customerPath}/04_Fatturazione/Fatture_Emesse/{$year}");

            return $this->helper->uploadFile($pdfPath, $remotePath);

        } catch (\Exception $e) {
            Log::error("Error uploading invoice issued: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload invoice received PDF (customer-specific)
     */
    public function uploadInvoiceReceived(InvoiceReceived $invoice, string $pdfPath): bool
    {
        try {
            if (!$invoice->customer_id) {
                Log::warning("Invoice received has no customer_id, skipping customer upload");
                return false;
            }

            $customer = $invoice->customer;
            $year = $invoice->issue_date->format('Y');
            $customerPath = $this->getCustomerBasePath($customer);
            $remotePath = "{$customerPath}/04_Fatturazione/Fatture_Ricevute/{$year}/{$invoice->invoice_number}.pdf";

            $this->ensureFolderExists("{$customerPath}/04_Fatturazione/Fatture_Ricevute/{$year}");

            return $this->helper->uploadFile($pdfPath, $remotePath);

        } catch (\Exception $e) {
            Log::error("Error uploading invoice received: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload F24 form
     */
    public function uploadF24(F24Form $f24, string $pdfPath): bool
    {
        try {
            if ($f24->customer_id) {
                $customer = $f24->customer;
                $customerPath = $this->getCustomerBasePath($customer);
                $year = $f24->reference_year;
                $remotePath = "{$customerPath}/04_Fatturazione/F24/{$year}/{$f24->form_number}.pdf";

                $this->ensureFolderExists("{$customerPath}/04_Fatturazione/F24/{$year}");

                return $this->helper->uploadFile($pdfPath, $remotePath);
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Error uploading F24: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload customer contract
     */
    public function uploadContract(CustomerContract $contract, string $pdfPath): bool
    {
        try {
            $customer = $contract->customer;
            $customerPath = $this->getCustomerBasePath($customer);
            $remotePath = "{$customerPath}/01_Anagrafica/Contratti/{$contract->contract_number}.pdf";

            return $this->helper->uploadFile($pdfPath, $remotePath);

        } catch (\Exception $e) {
            Log::error("Error uploading contract: " . $e->getMessage());
            return false;
        }
    }

    // ==================== PROJECT FOLDER MANAGEMENT ====================

    /**
     * Create complete folder structure for project
     */
    public function createProjectFolderStructure(Project $project): bool
    {
        try {
            $basePath = $this->getProjectBasePath($project);

            // Ottimizzazione: creo solo le cartelle più profonde (leaf folders)
            // createDirectory crea automaticamente i genitori
            $leafFolders = [
                // Preventivi (5 leaf folders)
                "{$basePath}/01_Preventivi/Bozze",
                "{$basePath}/01_Preventivi/Inviati",
                "{$basePath}/01_Preventivi/Accettati",
                "{$basePath}/01_Preventivi/Rifiutati",
                "{$basePath}/01_Preventivi/Scaduti",

                // Progettazione - KiCad/libraries (3 leaf folders)
                "{$basePath}/02_Progettazione/KiCad/libraries/symbols",
                "{$basePath}/02_Progettazione/KiCad/libraries/footprints",
                "{$basePath}/02_Progettazione/KiCad/libraries/3d_models",

                // Progettazione - KiCad/SystemEngineering (5 leaf folders)
                "{$basePath}/02_Progettazione/KiCad/SystemEngineering/Architecture",
                "{$basePath}/02_Progettazione/KiCad/SystemEngineering/Requirements",
                "{$basePath}/02_Progettazione/KiCad/SystemEngineering/Specifications",
                "{$basePath}/02_Progettazione/KiCad/SystemEngineering/Testing",
                "{$basePath}/02_Progettazione/KiCad/SystemEngineering/Validation",

                // Progettazione - Altre cartelle
                "{$basePath}/02_Progettazione/Gerber",
                "{$basePath}/02_Progettazione/BOM",

                // Progettazione - 3D Models (3 leaf folders)
                "{$basePath}/02_Progettazione/3D_Models/PCB",
                "{$basePath}/02_Progettazione/3D_Models/Enclosure",
                "{$basePath}/02_Progettazione/3D_Models/Assembly",

                // Progettazione - Datasheet
                "{$basePath}/02_Progettazione/Datasheet/Component_Datasheets",

                // Progettazione - Firmware
                "{$basePath}/02_Progettazione/Firmware",

                // Progettazione - Mechanical (2 leaf folders)
                "{$basePath}/02_Progettazione/Mechanical/CAD_Drawings",
                "{$basePath}/02_Progettazione/Mechanical/Technical_Drawings",

                // Produzione (5 leaf folders)
                "{$basePath}/03_Produzione/Ordini_PCB",
                "{$basePath}/03_Produzione/Ordini_Componenti",
                "{$basePath}/03_Produzione/Assembly_Instructions",
                "{$basePath}/03_Produzione/Test_Reports",
                "{$basePath}/03_Produzione/Production_Logs",

                // Certificazioni (6 leaf folders)
                "{$basePath}/04_Certificazioni_Conformita/CE_Marking",
                "{$basePath}/04_Certificazioni_Conformita/RoHS",
                "{$basePath}/04_Certificazioni_Conformita/FCC",
                "{$basePath}/04_Certificazioni_Conformita/EMC_Tests",
                "{$basePath}/04_Certificazioni_Conformita/Safety_Tests",
                "{$basePath}/04_Certificazioni_Conformita/Declarations_of_Conformity",

                // Documentazione - User Manuals (2 leaf folders)
                "{$basePath}/05_Documentazione/User_Manuals/IT",
                "{$basePath}/05_Documentazione/User_Manuals/EN",

                // Documentazione - Altre cartelle
                "{$basePath}/05_Documentazione/Service_Manuals",
                "{$basePath}/05_Documentazione/Quick_Start_Guides",
                "{$basePath}/05_Documentazione/Video_Tutorials",

                // Consegna (3 leaf folders)
                "{$basePath}/06_Consegna/DDT",
                "{$basePath}/06_Consegna/Packing_Lists",
                "{$basePath}/06_Consegna/Delivery_Photos",

                // Assistenza (4 leaf folders)
                "{$basePath}/07_Assistenza/Reclami",
                "{$basePath}/07_Assistenza/RMA",
                "{$basePath}/07_Assistenza/Error_Reports",
                "{$basePath}/07_Assistenza/Firmware_Updates",
            ];

            // Creo solo le leaf folders - i genitori vengono creati automaticamente
            foreach ($leafFolders as $folder) {
                if (!$this->helper->createDirectory($folder)) {
                    Log::error("Failed to create folder: {$folder}");
                    return false;
                }
            }

            Log::info("Project folder structure created: {$basePath} (optimized: " . count($leafFolders) . " folders)");
            return true;

        } catch (\Exception $e) {
            Log::error("Error creating project folder structure: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate project info JSON file
     */
    public function generateProjectInfoJson(Project $project): bool
    {
        try {
            $basePath = $this->getProjectBasePath($project);
            $jsonPath = "{$basePath}/_project_info.json";

            $data = [
                'project_code' => $project->code,
                'project_name' => $project->name,
                'customer_code' => $project->customer->code ?? null,
                'customer_name' => $project->customer->company_name ?? null,
                'status' => $project->status,
                'start_date' => $project->start_date?->toDateString(),
                'total_boards_ordered' => $project->total_boards_ordered ?? 0,
                'boards_produced' => $project->boards_produced ?? 0,
                'boards_assembled' => $project->boards_assembled ?? 0,
                'created_at' => $project->created_at?->toIso8601String(),
            ];

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return $this->helper->uploadContent($jsonContent, $jsonPath);

        } catch (\Exception $e) {
            Log::error("Error generating project info JSON: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate components used JSON file
     */
    public function generateComponentsUsedJson(Project $project): bool
    {
        try {
            $basePath = $this->getProjectBasePath($project);
            $jsonPath = "{$basePath}/_components_used.json";

            // Get component allocations
            $allocations = $project->componentAllocations ?? collect();

            $components = $allocations->map(function ($allocation) {
                return [
                    'component_code' => $allocation->component->code ?? 'N/A',
                    'description' => $allocation->component->description ?? '',
                    'quantity_allocated' => (float) $allocation->quantity_allocated,
                    'quantity_used' => (float) $allocation->quantity_used,
                    'unit_cost' => (float) $allocation->unit_cost,
                    'total_cost' => (float) $allocation->total_cost,
                    'source_invoice' => $allocation->sourceInvoice->invoice_number ?? null,
                ];
            })->toArray();

            $data = [
                'project_code' => $project->code,
                'last_updated' => now()->toIso8601String(),
                'components' => $components,
                'total_cost' => (float) ($project->total_components_cost ?? 0),
            ];

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return $this->helper->uploadContent($jsonContent, $jsonPath);

        } catch (\Exception $e) {
            Log::error("Error generating components used JSON: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload quotation PDF to customer folder based on status
     */
    public function uploadQuotation(Quotation $quotation, string $pdfPath): bool
    {
        try {
            $customer = $quotation->customer;
            if (!$customer) {
                Log::error("Quotation has no customer", ['quotation_id' => $quotation->id]);
                return false;
            }

            if (!file_exists($pdfPath)) {
                Log::error("Quotation PDF file not found", [
                    'quotation_id' => $quotation->id,
                    'pdf_path' => $pdfPath
                ]);
                return false;
            }

            $customerPath = $this->getCustomerBasePath($customer);

            // Determine folder based on status
            $statusFolder = match($quotation->status) {
                'draft' => 'Bozze',
                'sent' => 'Inviati',
                'accepted' => 'Accettati',
                'rejected' => 'Rifiutati',
                'expired' => 'Scaduti',
                default => 'Bozze',
            };

            // Create safe filename
            $filename = "preventivo-{$quotation->number}.pdf";
            $remotePath = "{$customerPath}/01_Preventivi/{$statusFolder}/{$filename}";

            Log::info("Uploading quotation PDF to Nextcloud", [
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->number,
                'customer_path' => $customerPath,
                'remote_path' => $remotePath,
                'pdf_size' => filesize($pdfPath)
            ]);

            // Ensure folder exists
            $folderCreated = $this->ensureFolderExists("{$customerPath}/01_Preventivi/{$statusFolder}");
            Log::info("Nextcloud folder status", [
                'created' => $folderCreated,
                'path' => "{$customerPath}/01_Preventivi/{$statusFolder}"
            ]);

            // Upload file
            $uploaded = $this->helper->uploadFile($pdfPath, $remotePath);

            if ($uploaded) {
                Log::info("Quotation PDF uploaded successfully", [
                    'quotation' => $quotation->number,
                    'path' => $remotePath,
                ]);
            } else {
                Log::error("Quotation PDF upload failed", [
                    'quotation' => $quotation->number,
                    'path' => $remotePath,
                ]);
            }

            return $uploaded;

        } catch (\Exception $e) {
            Log::error("Error uploading quotation", [
                'quotation_id' => $quotation->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Move quotation PDF when status changes
     */
    public function moveQuotationPdf(Quotation $quotation, string $oldStatus, string $newStatus): bool
    {
        try {
            if (!$quotation->nextcloud_path) {
                Log::warning("Quotation has no nextcloud_path to move");
                return false;
            }

            $customer = $quotation->customer;
            $customerPath = $this->getCustomerBasePath($customer);

            $oldFolder = match($oldStatus) {
                'draft' => 'Bozze',
                'sent' => 'Inviati',
                'accepted' => 'Accettati',
                'rejected' => 'Rifiutati',
                'expired' => 'Scaduti',
                default => 'Bozze',
            };

            $newFolder = match($newStatus) {
                'draft' => 'Bozze',
                'sent' => 'Inviati',
                'accepted' => 'Accettati',
                'rejected' => 'Rifiutati',
                'expired' => 'Scaduti',
                default => 'Bozze',
            };

            $filename = "preventivo-{$quotation->number}.pdf";
            $oldPath = "{$customerPath}/01_Preventivi/{$oldFolder}/{$filename}";
            $newPath = "{$customerPath}/01_Preventivi/{$newFolder}/{$filename}";

            // Ensure new folder exists
            $this->ensureFolderExists("{$customerPath}/01_Preventivi/{$newFolder}");

            // Move file
            $moved = $this->helper->moveFile($oldPath, $newPath);

            if ($moved) {
                Log::info("Quotation PDF moved", [
                    'quotation' => $quotation->number,
                    'from' => $oldPath,
                    'to' => $newPath,
                ]);
            }

            return $moved;

        } catch (\Exception $e) {
            Log::error("Error moving quotation PDF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create symlink (copy) for accepted quotation in project folder
     */
    public function createQuotationSymlink(Quotation $quotation, Project $project): bool
    {
        try {
            if (!$quotation->nextcloud_path) {
                Log::warning("Quotation has no PDF to link");
                return false;
            }

            $customerPath = $this->getCustomerBasePath($quotation->customer);
            $projectPath = $this->getProjectBasePath($project);

            $sourceFile = "{$customerPath}/01_Preventivi/Accettati/preventivo-{$quotation->number}.pdf";
            $symlinkPath = "{$projectPath}/01_Preventivi/Accettati/preventivo-{$quotation->number}.pdf";

            // Ensure project preventivi folder exists
            $this->ensureFolderExists("{$projectPath}/01_Preventivi/Accettati");

            // In WebDAV/Nextcloud, we can't create symlinks directly
            // Instead, copy the file to create a "reference"
            if ($this->helper->fileExists($sourceFile)) {
                // Download from source
                $tempPath = storage_path("app/temp/quotation-{$quotation->number}.pdf");
                if ($this->helper->downloadFile($sourceFile, $tempPath)) {
                    // Upload to project folder
                    $copied = $this->helper->uploadFile($tempPath, $symlinkPath);

                    // Clean up temp file
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }

                    if ($copied) {
                        Log::info("Quotation copied to project folder", [
                            'quotation' => $quotation->number,
                            'project' => $project->code,
                            'path' => $symlinkPath,
                        ]);
                    }

                    return $copied;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Error creating quotation symlink: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload project document
     */
    public function uploadProjectDocument(ProjectDocument $document, string $filePath): bool
    {
        try {
            $project = $document->project;
            $basePath = $this->getProjectBasePath($project);

            // Determine subfolder based on document type
            $subfolder = $this->getProjectDocumentSubfolder($document->type);
            $remotePath = "{$basePath}/{$subfolder}/{$document->filename}";

            return $this->helper->uploadFile($filePath, $remotePath);

        } catch (\Exception $e) {
            Log::error("Error uploading project document: " . $e->getMessage());
            return false;
        }
    }

    // ==================== WAREHOUSE MANAGEMENT ====================

    /**
     * Upload warehouse invoice (components, equipment, etc.)
     */
    public function uploadWarehouseInvoice(InvoiceReceived $invoice, string $pdfPath): bool
    {
        try {
            $year = $invoice->issue_date->format('Y');
            $supplierName = $invoice->supplier->name ?? $invoice->supplier_name;
            $date = $invoice->issue_date->format('Y-m-d');

            $subfolder = match($invoice->category) {
                'components' => 'Fornitori',
                'customs' => 'Dogana',
                'equipment' => 'Macchinari',
                default => 'Generali',
            };

            $remotePath = "Magazzino/Fatture_Magazzino/{$subfolder}/{$year}/{$supplierName}_{$date}.pdf";

            $this->ensureFolderExists("Magazzino/Fatture_Magazzino/{$subfolder}/{$year}");

            return $this->helper->uploadFile($pdfPath, $remotePath);

        } catch (\Exception $e) {
            Log::error("Error uploading warehouse invoice: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate invoice components JSON
     */
    public function generateInvoiceComponentsJson(InvoiceReceived $invoice): bool
    {
        try {
            if ($invoice->category !== 'components') {
                return false;
            }

            $year = $invoice->issue_date->format('Y');
            $supplierName = $invoice->supplier->name ?? $invoice->supplier_name;
            $date = $invoice->issue_date->format('Y-m-d');

            $remotePath = "Magazzino/Fatture_Magazzino/Fornitori/{$year}/{$supplierName}_{$date}_components.json";

            $components = $invoice->items->map(function ($item) {
                return [
                    'component_code' => $item->component->code ?? 'N/A',
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                ];
            })->toArray();

            $data = [
                'invoice_number' => $invoice->invoice_number,
                'supplier' => $supplierName,
                'date' => $invoice->issue_date->toDateString(),
                'components' => $components,
                'total' => (float) $invoice->total,
            ];

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return $this->helper->uploadContent($jsonContent, $remotePath);

        } catch (\Exception $e) {
            Log::error("Error generating invoice components JSON: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update component info on Nextcloud
     */
    public function updateComponentInfo(Component $component): bool
    {
        try {
            // This could be used to maintain a component catalog on Nextcloud
            // Implementation depends on specific requirements
            return true;

        } catch (\Exception $e) {
            Log::error("Error updating component info: " . $e->getMessage());
            return false;
        }
    }

    // ==================== ANALYTICS ====================

    /**
     * Generate billing analytics JSON
     */
    public function generateBillingAnalyticsJson(BillingAnalysis $analysis): bool
    {
        try {
            $year = $analysis->period_start->format('Y');
            $month = $analysis->period_start->format('m');

            $remotePath = "Analytics/Billing/{$year}/billing_analysis_{$year}_{$month}.json";

            $this->ensureFolderExists("Analytics/Billing/{$year}");

            $data = [
                'period_start' => $analysis->period_start->toDateString(),
                'period_end' => $analysis->period_end->toDateString(),
                'total_revenue' => (float) $analysis->total_revenue,
                'total_costs' => (float) $analysis->total_costs,
                'gross_profit' => (float) $analysis->gross_profit,
                'net_profit' => (float) $analysis->net_profit,
                'profit_margin' => (float) $analysis->profit_margin,
                'details' => $analysis->details ?? [],
            ];

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return $this->helper->uploadContent($jsonContent, $remotePath);

        } catch (\Exception $e) {
            Log::error("Error generating billing analytics JSON: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate monthly reports
     */
    public function generateMonthlyReports(int $year, int $month): bool
    {
        try {
            // Implementation for monthly consolidated reports
            return true;

        } catch (\Exception $e) {
            Log::error("Error generating monthly reports: " . $e->getMessage());
            return false;
        }
    }

    // ==================== UTILITY METHODS ====================

    /**
     * Get customer base path on Nextcloud
     */
    public function getCustomerBasePath(Customer $customer): string
    {
        return "Clienti/{$customer->code} - {$customer->company_name}";
    }

    /**
     * Get project base path on Nextcloud
     */
    public function getProjectBasePath(Project $project): string
    {
        $customer = $project->customer;
        $customerPath = $this->getCustomerBasePath($customer);
        return "{$customerPath}/03_Progetti/{$project->code} - {$project->name}";
    }

    /**
     * Ensure folder exists
     */
    public function ensureFolderExists(string $path): bool
    {
        try {
            return $this->helper->createDirectory($path);
        } catch (\Exception $e) {
            Log::error("Error ensuring folder exists: {$path} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move folder to archive (for inactive/deleted items)
     */
    public function moveToArchive(string $sourcePath, string $archiveType = 'Archiviati'): bool
    {
        try {
            // Extract folder name from source path
            $folderName = basename($sourcePath);
            $parentFolder = dirname($sourcePath);

            // Create archive destination path
            $archivePath = "{$parentFolder}/__{$archiveType}/{$folderName}";

            // Ensure archive folder exists
            $this->ensureFolderExists("{$parentFolder}/__{$archiveType}");

            // Move folder using helper
            return $this->helper->moveFolder($sourcePath, $archivePath);

        } catch (\Exception $e) {
            Log::error("Error moving to archive: {$sourcePath} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete folder completely
     */
    public function deleteFolder(string $path): bool
    {
        try {
            return $this->helper->deleteFolder($path);
        } catch (\Exception $e) {
            Log::error("Error deleting folder: {$path} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move customer folder to inactive archive
     */
    public function archiveInactiveCustomer(Customer $customer): bool
    {
        try {
            $basePath = $this->getCustomerBasePath($customer);
            return $this->moveToArchive($basePath, 'Inattivi');
        } catch (\Exception $e) {
            Log::error("Error archiving inactive customer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move customer folder to deleted archive
     */
    public function archiveDeletedCustomer(Customer $customer): bool
    {
        try {
            $basePath = $this->getCustomerBasePath($customer);
            return $this->moveToArchive($basePath, 'Eliminati');
        } catch (\Exception $e) {
            Log::error("Error archiving deleted customer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore customer folder from archive
     */
    public function restoreCustomerFromArchive(Customer $customer, string $archiveType = 'Inattivi'): bool
    {
        try {
            $folderName = "{$customer->code} - {$customer->company_name}";
            $archivePath = "Clienti/__{$archiveType}/{$folderName}";
            $activePath = "Clienti/{$folderName}";

            return $this->helper->moveFolder($archivePath, $activePath);
        } catch (\Exception $e) {
            Log::error("Error restoring customer from archive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move project folder to archive
     */
    public function archiveProject(Project $project, string $archiveType = 'Archiviati'): bool
    {
        try {
            $basePath = $this->getProjectBasePath($project);
            return $this->moveToArchive($basePath, $archiveType);
        } catch (\Exception $e) {
            Log::error("Error archiving project: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get project document subfolder based on type
     */
    protected function getProjectDocumentSubfolder(string $type): string
    {
        return match($type) {
            // Invoices and financial
            'invoice_received', 'invoice_issued' => '04_Certificazioni_Conformita',
            'customs' => '03_Produzione/Ordini_Componenti',

            // KiCad and design files
            'kicad_project' => '02_Progettazione/KiCad',
            'kicad_library' => '02_Progettazione/KiCad/libraries',
            'gerber' => '02_Progettazione/Gerber',
            'bom' => '02_Progettazione/BOM',
            'bom_interactive' => '02_Progettazione/BOM',

            // 3D models and CAD
            '3d_model' => '02_Progettazione/3D_Models/PCB',
            '3d_case' => '02_Progettazione/3D_Models/Enclosure',
            '3d_mechanical' => '02_Progettazione/3D_Models/Assembly',
            'cad_drawing' => '02_Progettazione/Mechanical/CAD_Drawings',

            // Firmware and datasheets
            'firmware' => '02_Progettazione/Firmware',
            'datasheet' => '02_Progettazione/Datasheet/Component_Datasheets',

            // Quality and issues
            'complaint' => '07_Assistenza/Reclami',
            'error_report' => '07_Assistenza/Error_Reports',

            // Production and testing
            'assembly_instructions' => '03_Produzione/Assembly_Instructions',
            'test_report' => '03_Produzione/Test_Reports',

            // Certifications and documentation
            'certification' => '04_Certificazioni_Conformita',

            // Default fallback
            default => '05_Documentazione',
        };
    }

    // ==================== GENERIC DOCUMENT MANAGEMENT ====================

    /**
     * Upload a generic document file to Nextcloud
     *
     * @param string $localFilePath Full path to local file
     * @param string $nextcloudPath Relative path on Nextcloud where file should be uploaded
     * @return bool Success status
     */
    public function uploadDocument(string $localFilePath, string $nextcloudPath): bool
    {
        try {
            if (!file_exists($localFilePath)) {
                Log::error("Document file not found for upload", ['path' => $localFilePath]);
                return false;
            }

            $result = $this->helper->uploadFile($localFilePath, $nextcloudPath);

            if ($result) {
                Log::info("Document uploaded to Nextcloud", [
                    'local' => $localFilePath,
                    'nextcloud' => $nextcloudPath
                ]);
            } else {
                Log::error("Failed to upload document to Nextcloud", [
                    'local' => $localFilePath,
                    'nextcloud' => $nextcloudPath
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Exception uploading document", [
                'error' => $e->getMessage(),
                'local' => $localFilePath,
                'nextcloud' => $nextcloudPath
            ]);
            return false;
        }
    }

    /**
     * Delete local file after successful Nextcloud upload
     *
     * @param string $localFilePath Full path to local file
     * @return bool Success status
     */
    public function deleteLocalFile(string $localFilePath): bool
    {
        try {
            if (file_exists($localFilePath)) {
                $deleted = unlink($localFilePath);

                if ($deleted) {
                    Log::info("Local file deleted after Nextcloud upload", ['path' => $localFilePath]);
                } else {
                    Log::warning("Failed to delete local file", ['path' => $localFilePath]);
                }

                return $deleted;
            }

            return true; // File already doesn't exist
        } catch (\Exception $e) {
            Log::error("Exception deleting local file", [
                'error' => $e->getMessage(),
                'path' => $localFilePath
            ]);
            return false;
        }
    }

    /**
     * Download document from Nextcloud to temporary location
     *
     * @param string $nextcloudPath Path on Nextcloud
     * @param string $localTempPath Local temporary path to download to
     * @return bool Success status
     */
    public function downloadDocument(string $nextcloudPath, string $localTempPath): bool
    {
        try {
            $result = $this->helper->downloadFile($nextcloudPath, $localTempPath);

            if ($result) {
                Log::info("Document downloaded from Nextcloud", [
                    'nextcloud' => $nextcloudPath,
                    'local' => $localTempPath
                ]);
            } else {
                Log::error("Failed to download document from Nextcloud", [
                    'nextcloud' => $nextcloudPath
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Exception downloading document", [
                'error' => $e->getMessage(),
                'nextcloud' => $nextcloudPath
            ]);
            return false;
        }
    }
}
