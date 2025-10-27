<?php

namespace App\Observers;

use App\Models\Document;
use App\Models\Project;
use App\Models\Customer;
use App\Services\NextcloudService;
use Illuminate\Support\Facades\Log;

class DocumentObserver
{
    protected NextcloudService $nextcloudService;

    // Files that should NOT be uploaded to Nextcloud (user files, avatars, logos, etc.)
    protected array $excludedPaths = [
        'avatars/',
        'profile-photos/',
        'logos/',
        'public/images/',
        'public/logos/',
        'temp/',
    ];

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Handle the Document "created" event.
     */
    public function created(Document $document): void
    {
        // Set timeout for file upload
        set_time_limit(300);

        try {
            // Skip if no file_path or excluded path
            if (!$document->file_path || $this->shouldExclude($document->file_path)) {
                return;
            }

            $localPath = storage_path('app/' . $document->file_path);

            if (!file_exists($localPath)) {
                Log::warning("Document file not found locally", [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path
                ]);
                return;
            }

            // Determine Nextcloud path based on documentable type
            $nextcloudPath = $this->getNextcloudPath($document);

            if (!$nextcloudPath) {
                Log::info("Document type not configured for Nextcloud upload", [
                    'document_id' => $document->id,
                    'documentable_type' => $document->documentable_type
                ]);
                return;
            }

            // Ensure parent folder exists
            $this->ensureParentFolderExists($document);

            // Upload to Nextcloud
            $uploaded = $this->nextcloudService->uploadDocument($localPath, $nextcloudPath);

            if ($uploaded) {
                // Update document with Nextcloud path
                $document->update([
                    'nextcloud_path' => $nextcloudPath,
                    'uploaded_to_nextcloud' => true,
                ]);

                Log::info("Document uploaded to Nextcloud", [
                    'document_id' => $document->id,
                    'documentable_type' => $document->documentable_type,
                    'nextcloud_path' => $nextcloudPath
                ]);

                // Delete local file after successful upload
                $this->deleteLocalFile($localPath, $document);
            } else {
                Log::error("Failed to upload Document to Nextcloud", [
                    'document_id' => $document->id,
                    'documentable_type' => $document->documentable_type
                ]);
            }
        } catch (\Exception $e) {
            Log::error("DocumentObserver::created error", [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle the Document "updated" event.
     */
    public function updated(Document $document): void
    {
        // If file_path changed, upload new file
        if ($document->isDirty('file_path') && $document->file_path) {
            $this->created($document);
        }
    }

    /**
     * Handle the Document "deleted" event.
     */
    public function deleted(Document $document): void
    {
        try {
            // Delete from Nextcloud if path exists
            if ($document->nextcloud_path) {
                $deleted = $this->nextcloudService->deleteFile($document->nextcloud_path);

                if ($deleted) {
                    Log::info("Document deleted from Nextcloud", [
                        'document_id' => $document->id,
                        'nextcloud_path' => $document->nextcloud_path
                    ]);
                }
            }

            // Delete local file if still exists
            if ($document->file_path && !$this->shouldExclude($document->file_path)) {
                $localPath = storage_path('app/' . $document->file_path);
                if (file_exists($localPath)) {
                    unlink($localPath);
                    Log::info("Local Document file deleted", [
                        'document_id' => $document->id,
                        'file_path' => $document->file_path
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("DocumentObserver::deleted error", [
                'error' => $e->getMessage(),
                'document_id' => $document->id
            ]);
        }
    }

    /**
     * Check if file path should be excluded from Nextcloud upload.
     */
    private function shouldExclude(string $filePath): bool
    {
        foreach ($this->excludedPaths as $excluded) {
            if (str_starts_with($filePath, $excluded)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get Nextcloud path for document based on its type and owner.
     */
    private function getNextcloudPath(Document $document): ?string
    {
        $filename = basename($document->file_path);
        $documentable = $document->documentable;

        if (!$documentable) {
            return null;
        }

        // Project documents
        if ($documentable instanceof Project) {
            $projectBasePath = $this->nextcloudService->getProjectBasePath($documentable);
            $subFolder = $this->getDocumentTypeFolder($document->type);
            return "{$projectBasePath}/{$subFolder}/{$filename}";
        }

        // Customer documents
        if ($documentable instanceof Customer) {
            $customerBasePath = $this->nextcloudService->getCustomerBasePath($documentable);
            $subFolder = $this->getCustomerDocumentFolder($document->type);
            return "{$customerBasePath}/{$subFolder}/{$filename}";
        }

        // Add more document types as needed
        return null;
    }

    /**
     * Get folder name for document type (for projects).
     */
    private function getDocumentTypeFolder(string $type): string
    {
        return match($type) {
            'contract' => '01_Contratti',
            'invoice' => '06_Fatture',
            'quotation' => '01_Preventivi',
            'technical' => '02_Progettazione',
            'certification' => '04_Certificazioni_Conformita',
            'production' => '03_Produzione',
            'support' => '07_Assistenza',
            default => '05_Documentazione',
        };
    }

    /**
     * Get folder name for customer document type.
     */
    private function getCustomerDocumentFolder(string $type): string
    {
        return match($type) {
            'contract' => '01_Anagrafica/Contratti',
            'certification' => '01_Anagrafica/Certificazioni',
            'identity' => '01_Anagrafica/Documenti_Identita',
            'communication' => '02_Comunicazioni',
            default => '01_Anagrafica',
        };
    }

    /**
     * Ensure parent Nextcloud folder exists for document.
     */
    private function ensureParentFolderExists(Document $document): void
    {
        $documentable = $document->documentable;

        if (!$documentable) {
            return;
        }

        // For projects, ensure project folder is created
        if ($documentable instanceof Project) {
            if (!$documentable->nextcloud_folder_created) {
                $this->nextcloudService->createProjectFolderStructure($documentable);
                $documentable->update(['nextcloud_folder_created' => true]);
            }
        }

        // For customers, ensure customer folder is created
        if ($documentable instanceof Customer) {
            if (!$documentable->nextcloud_folder_created) {
                $this->nextcloudService->createCustomerFolderStructure($documentable);
                $documentable->update(['nextcloud_folder_created' => true]);
            }
        }
    }

    /**
     * Delete local file after successful Nextcloud upload.
     */
    private function deleteLocalFile(string $localPath, Document $document): void
    {
        try {
            if (file_exists($localPath)) {
                $deleted = unlink($localPath);

                if ($deleted) {
                    Log::info("Local Document file deleted after Nextcloud upload", [
                        'document_id' => $document->id,
                        'local_path' => $localPath
                    ]);

                    // Mark as cloud-only
                    $document->update(['local_file_deleted' => true]);
                } else {
                    Log::warning("Failed to delete local Document file", [
                        'document_id' => $document->id,
                        'local_path' => $localPath
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error deleting local Document file", [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'local_path' => $localPath
            ]);
        }
    }
}
