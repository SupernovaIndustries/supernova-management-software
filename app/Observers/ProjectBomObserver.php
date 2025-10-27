<?php

namespace App\Observers;

use App\Models\ProjectBom;
use App\Services\NextcloudService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProjectBomObserver
{
    protected NextcloudService $nextcloudService;

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Handle the ProjectBom "created" event.
     * Upload BOM file to Nextcloud if a file was uploaded.
     */
    public function created(ProjectBom $bom): void
    {
        try {
            $project = $bom->project;

            // Ensure project has Nextcloud folder
            if (!$project->nextcloud_folder_created) {
                Log::warning('Project Nextcloud folder not created for BOM upload', [
                    'project_id' => $project->id,
                    'bom_id' => $bom->id,
                ]);
                return;
            }

            // Check if there's an uploaded file to process
            $uploadedFilePath = $bom->uploaded_file_path ?? null;

            if ($uploadedFilePath) {
                $this->uploadBomToNextcloud($bom, $uploadedFilePath);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process BOM after creation', [
                'bom_id' => $bom->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Upload BOM file to Nextcloud.
     */
    protected function uploadBomToNextcloud(ProjectBom $bom, string $localFilePath): void
    {
        try {
            $project = $bom->project;

            // Check if path is absolute or relative
            $fullLocalPath = $localFilePath;
            if (!file_exists($fullLocalPath)) {
                // Try as Laravel storage path
                $fullLocalPath = Storage::disk('local')->path($localFilePath);
            }

            if (!file_exists($fullLocalPath)) {
                Log::error('BOM file not found for upload', [
                    'bom_id' => $bom->id,
                    'path' => $localFilePath,
                    'tried_path' => $fullLocalPath,
                ]);
                return;
            }

            // Determine filename
            $filename = $bom->file_path ?: basename($localFilePath);

            // Generate unique filename with timestamp if needed
            $timestamp = now()->format('Ymd_His');
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $uniqueFilename = "{$nameWithoutExt}_{$timestamp}.{$extension}";

            // Build Nextcloud path
            $projectBasePath = $this->nextcloudService->getProjectBasePath($project);
            $bomFolder = "{$projectBasePath}/02_Progettazione/BOM";
            $nextcloudPath = "{$bomFolder}/{$uniqueFilename}";

            // Ensure BOM folder exists
            $this->nextcloudService->ensureFolderExists($bomFolder);

            // Upload to Nextcloud
            $uploaded = $this->nextcloudService->uploadDocument($fullLocalPath, $nextcloudPath);

            if ($uploaded) {
                // Update BOM with Nextcloud path
                $bom->update([
                    'file_path' => $uniqueFilename,
                    'folder_path' => '02_Progettazione/BOM',
                    'nextcloud_path' => $nextcloudPath,
                ]);

                Log::info('BOM file uploaded to Nextcloud', [
                    'bom_id' => $bom->id,
                    'project_code' => $project->code,
                    'nextcloud_path' => $nextcloudPath,
                ]);

                // Delete local temporary file
                if (file_exists($fullLocalPath)) {
                    unlink($fullLocalPath);
                    Log::info('Temporary BOM file deleted', [
                        'bom_id' => $bom->id,
                        'local_path' => $fullLocalPath,
                    ]);
                }
            } else {
                Log::error('Failed to upload BOM to Nextcloud', [
                    'bom_id' => $bom->id,
                    'nextcloud_path' => $nextcloudPath,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error uploading BOM to Nextcloud', [
                'bom_id' => $bom->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle the ProjectBom "updated" event.
     */
    public function updated(ProjectBom $bom): void
    {
        // If a new file was uploaded during edit, process it
        if ($bom->isDirty('uploaded_file_path') && $bom->uploaded_file_path) {
            $this->uploadBomToNextcloud($bom, $bom->uploaded_file_path);
        }
    }

    /**
     * Handle the ProjectBom "deleted" event.
     * Note: We don't delete from Nextcloud, just log.
     */
    public function deleted(ProjectBom $bom): void
    {
        Log::info('BOM deleted from database', [
            'bom_id' => $bom->id,
            'project_id' => $bom->project_id,
            'nextcloud_path' => $bom->nextcloud_path ?? $bom->file_path,
        ]);

        // Note: We intentionally don't delete from Nextcloud to preserve history
    }
}
