<?php

namespace App\Observers;

use App\Models\ProjectDocument;
use App\Services\NextcloudService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentObserver
{
    protected NextcloudService $nextcloudService;

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Handle the ProjectDocument "created" event.
     */
    public function created(ProjectDocument $document): void
    {
        // Set timeout for file upload
        set_time_limit(300);

        try {
            // Only upload if file_path exists and file is present locally
            if (!$document->file_path) {
                return;
            }

            $localPath = storage_path('app/' . $document->file_path);

            if (!file_exists($localPath)) {
                Log::warning("ProjectDocument file not found locally", [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path
                ]);
                return;
            }

            // Get project and ensure Nextcloud folder exists
            $project = $document->project;
            if (!$project) {
                Log::warning("ProjectDocument has no project", ['document_id' => $document->id]);
                return;
            }

            // Ensure project Nextcloud folder is created
            if (!$project->nextcloud_folder_created) {
                Log::info("Creating Nextcloud folder for project during document upload", [
                    'project_code' => $project->code
                ]);
                $this->nextcloudService->createProjectFolderStructure($project);
                $project->update(['nextcloud_folder_created' => true]);
            }

            // Determine Nextcloud subfolder based on document type
            $projectBasePath = $this->nextcloudService->getProjectBasePath($project);
            $subFolder = $this->nextcloudService->getProjectDocumentSubfolder($document->type);

            // Ensure subfolder exists
            $fullSubfolderPath = "{$projectBasePath}/{$subFolder}";
            $this->nextcloudService->createFolder($fullSubfolderPath);

            // Build final Nextcloud path with original filename
            $filename = $document->original_filename ?: basename($document->file_path);
            $nextcloudPath = "{$fullSubfolderPath}/{$filename}";

            // Upload to Nextcloud
            $uploaded = $this->nextcloudService->uploadDocument($localPath, $nextcloudPath);

            if ($uploaded) {
                // Update document with Nextcloud path
                $document->update([
                    'nextcloud_path' => $nextcloudPath,
                    'uploaded_to_nextcloud' => true,
                ]);

                Log::info("ProjectDocument uploaded to Nextcloud", [
                    'document_id' => $document->id,
                    'project_code' => $project->code,
                    'nextcloud_path' => $nextcloudPath
                ]);

                // Delete local file after successful upload
                $this->deleteLocalFile($localPath, $document);
            } else {
                Log::error("Failed to upload ProjectDocument to Nextcloud", [
                    'document_id' => $document->id,
                    'project_code' => $project->code
                ]);
            }
        } catch (\Exception $e) {
            Log::error("ProjectDocumentObserver::created error", [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle the ProjectDocument "updated" event.
     */
    public function updated(ProjectDocument $document): void
    {
        // If file_path changed, upload new file
        if ($document->isDirty('file_path') && $document->file_path) {
            $this->created($document);
        }
    }

    /**
     * Handle the ProjectDocument "deleted" event.
     */
    public function deleted(ProjectDocument $document): void
    {
        try {
            // Delete from Nextcloud if path exists
            if ($document->nextcloud_path) {
                $deleted = $this->nextcloudService->deleteFile($document->nextcloud_path);

                if ($deleted) {
                    Log::info("ProjectDocument deleted from Nextcloud", [
                        'document_id' => $document->id,
                        'nextcloud_path' => $document->nextcloud_path
                    ]);
                }
            }

            // Delete local file if still exists
            if ($document->file_path) {
                $localPath = storage_path('app/' . $document->file_path);
                if (file_exists($localPath)) {
                    unlink($localPath);
                    Log::info("Local ProjectDocument file deleted", [
                        'document_id' => $document->id,
                        'file_path' => $document->file_path
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("ProjectDocumentObserver::deleted error", [
                'error' => $e->getMessage(),
                'document_id' => $document->id
            ]);
        }
    }

    /**
     * Delete local file after successful Nextcloud upload.
     */
    private function deleteLocalFile(string $localPath, ProjectDocument $document): void
    {
        try {
            if (file_exists($localPath)) {
                $deleted = unlink($localPath);

                if ($deleted) {
                    Log::info("Local ProjectDocument file deleted after Nextcloud upload", [
                        'document_id' => $document->id,
                        'local_path' => $localPath
                    ]);

                    // Update document to clear local file_path (now only on Nextcloud)
                    // Keep the reference but mark it's on cloud only
                    $document->update(['local_file_deleted' => true]);
                } else {
                    Log::warning("Failed to delete local ProjectDocument file", [
                        'document_id' => $document->id,
                        'local_path' => $localPath
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error deleting local ProjectDocument file", [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'local_path' => $localPath
            ]);
        }
    }
}
