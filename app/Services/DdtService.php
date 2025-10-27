<?php

namespace App\Services;

use App\Models\BoardAssemblyLog;
use App\Models\CompanyProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DdtService
{
    private CompanyProfile $profile;
    private NextcloudService $nextcloudService;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->profile = CompanyProfile::current();
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Generate progressive DDT number.
     * Format: DDT-YYYY-NNNN (e.g., DDT-2025-0001)
     */
    public function generateDdtNumber(): string
    {
        $year = now()->year;

        // Find the highest DDT number for current year
        $lastDdt = BoardAssemblyLog::whereNotNull('ddt_number')
            ->where('ddt_number', 'LIKE', "DDT-{$year}-%")
            ->orderBy('ddt_number', 'desc')
            ->first();

        if ($lastDdt) {
            // Extract number from format DDT-YYYY-NNNN
            $parts = explode('-', $lastDdt->ddt_number);
            $lastNumber = isset($parts[2]) ? intval($parts[2]) : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('DDT-%d-%04d', $year, $nextNumber);
    }

    /**
     * Generate goods description using Claude AI.
     */
    public function generateGoodsDescription(BoardAssemblyLog $log): ?string
    {
        if (!$this->isClaudeEnabled()) {
            // Fallback to basic description
            return $this->generateBasicGoodsDescription($log);
        }

        try {
            $prompt = $this->buildGoodsDescriptionPrompt($log);
            $aiResponse = $this->makeClaudeRequest($prompt, 300);

            if ($aiResponse) {
                // Clean up AI response
                $description = trim($aiResponse);
                // Remove quotes if AI added them
                $description = trim($description, '"\'');

                Log::info('DDT goods description generated with AI', [
                    'assembly_log_id' => $log->id,
                    'description' => $description
                ]);

                return $description;
            }

            // Fallback if AI fails
            return $this->generateBasicGoodsDescription($log);

        } catch (\Exception $e) {
            Log::error('Failed to generate AI goods description for DDT', [
                'assembly_log_id' => $log->id,
                'error' => $e->getMessage()
            ]);

            return $this->generateBasicGoodsDescription($log);
        }
    }

    /**
     * Build AI prompt for goods description.
     */
    private function buildGoodsDescriptionPrompt(BoardAssemblyLog $log): string
    {
        $project = $log->project;
        $projectName = $project->name ?? 'Unknown Project';
        $boardsCount = $log->boards_count;
        $assemblyType = $log->is_prototype ? 'prototipo/test' : 'produzione serie';

        // Extract BOM info
        $bomInfo = '';
        $boms = $project->boms ?? collect();
        if ($boms->isNotEmpty()) {
            $components = $boms->flatMap(function ($bom) {
                return $bom->components ?? collect();
            })->unique('id');

            $componentCount = $components->count();
            $mainCategories = $components->pluck('category.name')->filter()->unique()->take(3)->implode(', ');

            if ($componentCount > 0) {
                $bomInfo = "\n- Contiene {$componentCount} componenti elettronici diversi";
                if ($mainCategories) {
                    $bomInfo .= "\n- Categorie principali: {$mainCategories}";
                }
            }
        }

        return "Sei un esperto di elettronica per Supernova Industries S.R.L.

Devi generare una descrizione BREVE e PROFESSIONALE della merce per un DDT (Documento di Trasporto).

INFORMAZIONI ASSEMBLAGGIO:
- Progetto: {$projectName}
- Numero schede: {$boardsCount}
- Tipo: {$assemblyType}{$bomInfo}

REQUISITI:
1. Descrizione in italiano, professionale e tecnica
2. Massimo 150 caratteri (una riga)
3. Menziona: tipo di dispositivo, funzione principale, quantità
4. NON usare virgolette
5. Esempi:
   - \"Schede elettroniche per controllo motori DC con microcontrollore STM32 (3 pezzi)\"
   - \"PCB assemblati per sistema di acquisizione dati con sensori di temperatura (10 unità)\"
   - \"Dispositivi prototipo di controllo illuminazione LED con interfaccia wireless (2 campioni)\"

Restituisci SOLO la descrizione, nessun commento aggiuntivo.";
    }

    /**
     * Generate basic goods description as fallback.
     */
    private function generateBasicGoodsDescription(BoardAssemblyLog $log): string
    {
        $project = $log->project;
        $boardsCount = $log->boards_count;
        $type = $log->is_prototype ? 'prototipo' : 'produzione';

        $description = "Schede elettroniche assemblate";

        if ($project && $project->name) {
            $description .= " per progetto {$project->name}";
        }

        $description .= " ({$boardsCount} " . ($boardsCount == 1 ? 'pezzo' : 'pezzi') . ", {$type})";

        return $description;
    }

    /**
     * Determine transport reason based on assembly log.
     */
    public function determineTransportReason(BoardAssemblyLog $log): string
    {
        $project = $log->project;
        $totalOrdered = $project->total_boards_ordered ?? 0;
        $alreadyAssembled = $project->boards_assembled ?? 0;
        $currentCount = $log->boards_count;

        if ($log->is_prototype) {
            return "Consegna campione dispositivo elettronico per test e validazione";
        }

        if ($totalOrdered > 0) {
            $totalAfterThis = $alreadyAssembled + $currentCount;

            if ($totalAfterThis < $totalOrdered) {
                // Partial delivery
                return "Consegna parziale dispositivi elettronici ({$currentCount} di {$totalOrdered} totali)";
            }
        }

        // Complete delivery
        return "Consegna dispositivi elettronici";
    }

    /**
     * Determine payment condition based on assembly log.
     */
    public function determinePaymentCondition(BoardAssemblyLog $log): string
    {
        $project = $log->project;
        $totalOrdered = $project->total_boards_ordered ?? 0;
        $alreadyAssembled = $project->boards_assembled ?? 0;
        $currentCount = $log->boards_count;

        // Prototype or test is always "in_conto"
        if ($log->is_prototype) {
            return 'in_conto';
        }

        // If partial delivery, it's "in_conto"
        if ($totalOrdered > 0) {
            $totalAfterThis = $alreadyAssembled + $currentCount;

            if ($totalAfterThis < $totalOrdered) {
                return 'in_conto';
            }
        }

        // Complete delivery is "in_saldo"
        return 'in_saldo';
    }

    /**
     * Generate DDT PDF.
     */
    public function generateDdtPdf(BoardAssemblyLog $log): ?string
    {
        try {
            // Load relationships
            $log->load('project.customer');

            // Generate DDT data if not already set
            if (!$log->ddt_number) {
                $log->ddt_number = $this->generateDdtNumber();
            }
            if (!$log->ddt_date) {
                $log->ddt_date = now();
            }
            if (!$log->ddt_goods_description) {
                $log->ddt_goods_description = $this->generateGoodsDescription($log);
            }
            if (!$log->ddt_reason) {
                $log->ddt_reason = $this->determineTransportReason($log);
            }
            if (!$log->ddt_payment_condition) {
                $log->ddt_payment_condition = $this->determinePaymentCondition($log);
            }

            // Save updated fields
            $log->save();

            // Prepare data for PDF
            $data = [
                'log' => $log,
                'project' => $log->project,
                'customer' => $log->project->customer,
                'company' => $this->profile,
            ];

            // Generate PDF
            $pdf = Pdf::loadView('pdf.ddt-assembly', $data);
            $pdf->setPaper('a4', 'portrait');

            // Generate filename
            $filename = "DDT_{$log->ddt_number}_" . $log->ddt_date->format('Y-m-d') . ".pdf";
            $localPath = storage_path("app/temp/ddt/{$filename}");

            // Ensure directory exists
            $directory = dirname($localPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save PDF locally first
            $pdf->save($localPath);

            Log::info('DDT PDF generated', [
                'assembly_log_id' => $log->id,
                'ddt_number' => $log->ddt_number,
                'filename' => $filename,
                'path' => $localPath
            ]);

            return $localPath;

        } catch (\Exception $e) {
            Log::error('Failed to generate DDT PDF', [
                'assembly_log_id' => $log->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Upload DDT to Nextcloud.
     */
    public function uploadToNextcloud(BoardAssemblyLog $log, string $pdfPath): bool
    {
        try {
            $project = $log->project;

            if (!$project->nextcloud_folder_created) {
                Log::warning('Project Nextcloud folder not created', ['project_id' => $project->id]);
                return false;
            }

            $projectBasePath = $this->nextcloudService->getProjectBasePath($project);

            // Upload path: Clienti/{customer}/01_Progetti/{project}/05_Logistica/DDT/
            $ddtBasePath = "{$projectBasePath}/06_Consegna/DDT";

            // Ensure folder exists
            $this->nextcloudService->ensureFolderExists($ddtBasePath);

            // Remote filename
            $filename = basename($pdfPath);
            $remotePath = "{$ddtBasePath}/{$filename}";

            // Upload file
            $uploaded = $this->nextcloudService->uploadDocument($pdfPath, $remotePath);

            if ($uploaded) {
                // Update log with Nextcloud path
                $log->update(['ddt_pdf_path' => $remotePath]);

                Log::info('DDT uploaded to Nextcloud', [
                    'assembly_log_id' => $log->id,
                    'nextcloud_path' => $remotePath
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to upload DDT to Nextcloud', [
                'assembly_log_id' => $log->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generate and upload DDT (complete workflow).
     */
    public function generateAndUploadDdt(BoardAssemblyLog $log): bool
    {
        try {
            // Generate PDF
            $pdfPath = $this->generateDdtPdf($log);

            if (!$pdfPath || !file_exists($pdfPath)) {
                Log::error('DDT PDF generation failed', ['assembly_log_id' => $log->id]);
                return false;
            }

            // Upload to Nextcloud
            $uploaded = $this->uploadToNextcloud($log, $pdfPath);

            // Update generated_at timestamp
            $log->update(['ddt_generated_at' => now()]);

            // Clean up local file after successful upload
            if ($uploaded && file_exists($pdfPath)) {
                unlink($pdfPath);
            }

            return $uploaded;

        } catch (\Exception $e) {
            Log::error('DDT generation and upload failed', [
                'assembly_log_id' => $log->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Update DDT with signatures.
     */
    public function updateSignatures(BoardAssemblyLog $log, ?string $conductorSignature, ?string $recipientSignature): bool
    {
        try {
            $log->update([
                'ddt_conductor_signature' => $conductorSignature,
                'ddt_recipient_signature' => $recipientSignature,
                'ddt_signed_at' => now(),
            ]);

            // Regenerate PDF with signatures
            $pdfPath = $this->generateDdtPdf($log);

            if ($pdfPath && file_exists($pdfPath)) {
                // Upload signed version
                $project = $log->project;
                $projectBasePath = $this->nextcloudService->getProjectBasePath($project);
                $ddtBasePath = "{$projectBasePath}/06_Consegna/DDT";

                $signedFilename = "DDT_{$log->ddt_number}_" . $log->ddt_date->format('Y-m-d') . "_signed.pdf";
                $remotePath = "{$ddtBasePath}/{$signedFilename}";

                $uploaded = $this->nextcloudService->uploadDocument($pdfPath, $remotePath);

                if ($uploaded) {
                    $log->update(['ddt_signed_pdf_path' => $remotePath]);
                }

                // Clean up
                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }

                return $uploaded;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to update DDT signatures', [
                'assembly_log_id' => $log->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if Claude AI is enabled.
     */
    private function isClaudeEnabled(): bool
    {
        return $this->profile->isClaudeEnabled();
    }

    /**
     * Make request to Claude AI API.
     */
    private function makeClaudeRequest(string $prompt, int $maxTokens = 500): ?string
    {
        if (!$this->isClaudeEnabled()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->profile->claude_api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
                'model' => $this->profile->claude_model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['content'][0]['text'])) {
                    return trim($data['content'][0]['text']);
                }
            }

            Log::error('Claude AI DDT request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500)
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Claude AI DDT service error', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Download DDT PDF for viewing.
     */
    public function downloadDdtPdf(BoardAssemblyLog $log, bool $signed = false): ?string
    {
        try {
            $nextcloudPath = $signed ? $log->ddt_signed_pdf_path : $log->ddt_pdf_path;

            if (!$nextcloudPath) {
                return null;
            }

            $filename = basename($nextcloudPath);
            $localPath = storage_path("app/temp/ddt_downloads/{$filename}");

            // Ensure directory exists
            $directory = dirname($localPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Download from Nextcloud
            $downloaded = $this->nextcloudService->downloadDocument($nextcloudPath, $localPath);

            if ($downloaded && file_exists($localPath)) {
                return $localPath;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to download DDT PDF', [
                'assembly_log_id' => $log->id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }
}
