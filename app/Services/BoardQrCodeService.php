<?php

namespace App\Services;

use App\Models\BoardAssemblyLog;
use App\Models\BoardQrCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class BoardQrCodeService
{
    protected NextcloudService $nextcloudService;

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Generate QR codes for all boards in an assembly log.
     * Uses SVG format to avoid imagick/GD dependencies.
     *
     * @param BoardAssemblyLog $assemblyLog
     * @return int Number of QR codes generated
     */
    public function generateQrCodesForAssemblyLog(BoardAssemblyLog $assemblyLog): int
    {
        try {
            $project = $assemblyLog->project;
            $boardsCount = $assemblyLog->boards_count;
            $batchNumber = $assemblyLog->batch_number;
            $assemblyDate = $assemblyLog->assembly_date->format('Ymd');
            $projectCode = $project->code;

            // Check if project has Nextcloud folder
            if (!$project->nextcloud_folder_created) {
                Log::warning('Project Nextcloud folder not created for QR generation', [
                    'project_id' => $project->id,
                    'assembly_log_id' => $assemblyLog->id,
                ]);
                return 0;
            }

            // Base path for QR codes on Nextcloud
            $projectBasePath = $this->nextcloudService->getProjectBasePath($project);
            $qrBasePath = "{$projectBasePath}/03_Produzione/QR_Codes";

            // Ensure QR codes folder exists on Nextcloud
            $this->nextcloudService->ensureFolderExists($qrBasePath);

            $generatedCount = 0;

            // Generate QR code for each board
            for ($boardNumber = 1; $boardNumber <= $boardsCount; $boardNumber++) {
                // Generate QR data
                $qrData = $this->generateQrData($projectCode, $batchNumber, $boardNumber, $assemblyDate);

                // Generate QR code using BaconQrCode directly with SVG backend
                $renderer = new ImageRenderer(
                    new RendererStyle(300),
                    new SvgImageBackEnd()
                );
                $writer = new Writer($renderer);
                $qrImage = $writer->writeString($qrData);

                // Create temporary file
                $tempDir = storage_path('app/temp/qr-codes');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $tempFilename = $this->generateFilename($projectCode, $batchNumber, $boardNumber);
                $tempPath = "{$tempDir}/{$tempFilename}";

                // Save QR code to temporary file
                file_put_contents($tempPath, $qrImage);

                // Upload to Nextcloud
                $nextcloudPath = "{$qrBasePath}/{$tempFilename}";
                $uploaded = $this->nextcloudService->uploadDocument($tempPath, $nextcloudPath);

                if ($uploaded) {
                    // Create database record
                    BoardQrCode::create([
                        'board_assembly_log_id' => $assemblyLog->id,
                        'board_number' => $boardNumber,
                        'qr_data' => $qrData,
                        'qr_file_path' => $nextcloudPath,
                        'generated_at' => now(),
                    ]);

                    $generatedCount++;

                    Log::info('QR code generated and uploaded', [
                        'assembly_log_id' => $assemblyLog->id,
                        'board_number' => $boardNumber,
                        'qr_data' => $qrData,
                        'path' => $nextcloudPath,
                    ]);
                } else {
                    Log::error('Failed to upload QR code to Nextcloud', [
                        'assembly_log_id' => $assemblyLog->id,
                        'board_number' => $boardNumber,
                        'nextcloud_path' => $nextcloudPath,
                    ]);
                }

                // Delete temporary file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

            Log::info('QR codes generation completed', [
                'assembly_log_id' => $assemblyLog->id,
                'boards_count' => $boardsCount,
                'generated_count' => $generatedCount,
            ]);

            return $generatedCount;

        } catch (\Exception $e) {
            Log::error('Error generating QR codes for assembly log', [
                'assembly_log_id' => $assemblyLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 0;
        }
    }

    /**
     * Generate QR data string.
     *
     * Format: PROJECT_CODE-BATCH_NUMBER-BOARD_NUMBER-DATE
     * Example: SMARTVERSE-001-PROD-001-20251006
     *
     * @param string $projectCode
     * @param string $batchNumber
     * @param int $boardNumber
     * @param string $date
     * @return string
     */
    protected function generateQrData(string $projectCode, string $batchNumber, int $boardNumber, string $date): string
    {
        $formattedBoardNumber = sprintf('%03d', $boardNumber);
        return "{$projectCode}-{$batchNumber}-{$formattedBoardNumber}-{$date}";
    }

    /**
     * Generate filename for QR code.
     *
     * Format: PROJECT_CODE_BATCH_BOARD_NUM.svg
     * Example: SMARTVERSE_001-PROD_001.svg
     *
     * @param string $projectCode
     * @param string $batchNumber
     * @param int $boardNumber
     * @return string
     */
    protected function generateFilename(string $projectCode, string $batchNumber, int $boardNumber): string
    {
        $formattedBoardNumber = sprintf('%03d', $boardNumber);
        // Clean batch number for filename (remove invalid characters)
        $cleanBatchNumber = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $batchNumber);
        return "{$projectCode}_{$cleanBatchNumber}_{$formattedBoardNumber}.svg";
    }

    /**
     * Regenerate QR codes for an assembly log.
     * Deletes existing QR codes and generates new ones.
     *
     * @param BoardAssemblyLog $assemblyLog
     * @return int Number of QR codes regenerated
     */
    public function regenerateQrCodes(BoardAssemblyLog $assemblyLog): int
    {
        try {
            // Delete existing QR codes
            $assemblyLog->qrCodes()->delete();

            // Generate new QR codes
            return $this->generateQrCodesForAssemblyLog($assemblyLog);

        } catch (\Exception $e) {
            Log::error('Error regenerating QR codes', [
                'assembly_log_id' => $assemblyLog->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Download QR code from Nextcloud to temporary location.
     *
     * @param BoardQrCode $qrCode
     * @return string|null Path to temporary file or null on failure
     */
    public function downloadQrCode(BoardQrCode $qrCode): ?string
    {
        try {
            $tempDir = storage_path('app/temp/qr-downloads');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempPath = "{$tempDir}/{$qrCode->filename}";

            $downloaded = $this->nextcloudService->downloadDocument($qrCode->qr_file_path, $tempPath);

            if ($downloaded) {
                return $tempPath;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error downloading QR code', [
                'qr_code_id' => $qrCode->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get QR code image as base64 data URL.
     *
     * @param BoardQrCode $qrCode
     * @return string|null Base64 data URL or null on failure
     */
    public function getQrCodeAsBase64(BoardQrCode $qrCode): ?string
    {
        try {
            $tempPath = $this->downloadQrCode($qrCode);

            if ($tempPath && file_exists($tempPath)) {
                $imageData = file_get_contents($tempPath);
                $base64 = base64_encode($imageData);

                // Delete temporary file
                unlink($tempPath);

                return "data:image/svg+xml;base64,{$base64}";
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error getting QR code as base64', [
                'qr_code_id' => $qrCode->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
