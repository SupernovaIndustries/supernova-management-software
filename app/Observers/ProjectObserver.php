<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\NextcloudService;
use App\Mail\ProjectStatusChangedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProjectObserver
{
    protected NextcloudService $nextcloudService;

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        // Set timeout to 5 minutes for folder creation
        set_time_limit(300);

        try {
            // Ensure customer has Nextcloud folder first - CREATE IT IF MISSING
            if (!$project->customer->nextcloud_folder_created) {
                Log::info("Creating customer folder for project: {$project->code}");
                $this->nextcloudService->createCustomerFolderStructure($project->customer);
                $this->nextcloudService->generateCustomerInfoJson($project->customer);
                $project->customer->update(['nextcloud_folder_created' => true]);
            }

            // Create complete folder structure on Nextcloud
            $folderCreated = $this->nextcloudService->createProjectFolderStructure($project);

            if ($folderCreated) {
                // Generate _project_info.json
                $projectInfoCreated = $this->nextcloudService->generateProjectInfoJson($project);

                // Generate empty _components_used.json
                $componentsJsonCreated = $this->nextcloudService->generateComponentsUsedJson($project);

                if ($projectInfoCreated && $componentsJsonCreated) {
                    // Update project with Nextcloud information
                    $project->update([
                        'nextcloud_base_path' => $this->nextcloudService->getProjectBasePath($project),
                        'nextcloud_folder_created' => true,
                    ]);

                    Log::info("Nextcloud folder created for project: {$project->code}");
                } else {
                    Log::warning("Failed to create JSON files for project: {$project->code}");
                }
            } else {
                Log::error("Failed to create Nextcloud folders for project: {$project->code}");
            }

            // Auto-generate milestones if enabled in company profile
            $companyProfile = \App\Models\CompanyProfile::current();
            if ($companyProfile->auto_generate_milestones && $project->description) {
                Log::info("Auto-generating milestones for project: {$project->code}");

                // Dispatch synchronously to ensure milestones are attached before email
                try {
                    $job = new \App\Jobs\GenerateProjectMilestones($project);
                    $job->handle();

                    // Refresh project to get attached milestones
                    $project->refresh();

                    Log::info("Milestones auto-generated and attached for project: {$project->code}", [
                        'milestones_count' => $project->milestones()->count()
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to auto-generate milestones in observer", [
                        'project_id' => $project->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Send "Project Created" email (always to admin, CC to client if available)
            // This happens AFTER milestone generation so they're included in the email
            $this->sendProjectCreatedEmail($project);

        } catch (\Exception $e) {
            Log::error("ProjectObserver::created error: " . $e->getMessage());
        }
    }

    /**
     * Handle the Project "updated" event.
     */
    public function updated(Project $project): void
    {
        // Set timeout to 5 minutes for JSON updates
        set_time_limit(300);

        try {
            // Check if project status changed and send email notification
            if ($project->isDirty('status')) {
                $oldStatus = $project->getOriginal('status');
                $newStatus = $project->status;

                // Send email notification (always to admin, CC to client if available)
                $this->sendStatusChangeEmail($project, $oldStatus, $newStatus);

                // Archive project folder if status changed to completed or cancelled
                if ($project->nextcloud_folder_created && in_array($newStatus, ['completed', 'cancelled'])) {
                    $archiveType = $newStatus === 'completed' ? 'Completati' : 'Cancellati';
                    $this->nextcloudService->archiveProject($project, $archiveType);
                    Log::info("Project folder moved to {$archiveType}: {$project->code}");
                }
            }

            // Handle first board produced (test board)
            if ($project->isDirty('boards_produced')) {
                $oldValue = $project->getOriginal('boards_produced') ?? 0;
                $newValue = $project->boards_produced ?? 0;

                // First board produced (test board)
                if ($oldValue == 0 && $newValue >= 1) {
                    Log::info("First test board produced for project: {$project->code}");
                    // Note: Notification can be added here if needed
                }
            }

            // Auto-update completion percentage when milestones change
            if ($project->isDirty('completion_percentage')) {
                $this->updateProjectStatusFromCompletion($project);
            }

            // Update _project_info.json if key fields change
            if ($project->nextcloud_folder_created && $project->isDirty(['name', 'description', 'status', 'total_boards_ordered', 'boards_produced', 'boards_assembled', 'completion_percentage'])) {
                $this->nextcloudService->generateProjectInfoJson($project);
                Log::info("Project info JSON updated for: {$project->code}");
            }

            // Regenerate _components_used.json if components tracking is enabled
            if ($project->nextcloud_folder_created && $project->components_tracked && $project->isDirty(['total_components_cost'])) {
                $this->nextcloudService->generateComponentsUsedJson($project);
                Log::info("Components used JSON updated for: {$project->code}");
            }
        } catch (\Exception $e) {
            Log::error("ProjectObserver::updated error: " . $e->getMessage());
        }
    }

    /**
     * Update project status based on completion percentage.
     */
    private function updateProjectStatusFromCompletion(Project $project): void
    {
        $percentage = $project->completion_percentage;

        // Don't auto-update if project is completed or cancelled
        if (in_array($project->status, ['completed', 'cancelled'])) {
            return;
        }

        // Auto-update status based on completion
        if ($percentage == 0 && $project->status !== 'planning') {
            // Keep current status if already started
        } elseif ($percentage > 0 && $percentage < 100 && $project->status === 'planning') {
            $project->status = 'in_progress';
            Log::info("Auto-updated project status to in_progress", [
                'project_id' => $project->id,
                'completion' => $percentage
            ]);
        } elseif ($percentage == 100 && $project->status !== 'completed') {
            $project->status = 'completed';
            $project->completed_date = now();
            Log::info("Auto-updated project status to completed", [
                'project_id' => $project->id
            ]);
        }
    }

    /**
     * Send email notification when project is created.
     */
    private function sendProjectCreatedEmail(Project $project): void
    {
        try {
            $adminEmail = 'alessandro.cursoli@supernovaindustries.it';

            // Get client email from project or customer
            $clientEmail = $project->client_email ?: $project->customer->email;

            // Always send to admin
            $mail = Mail::to($adminEmail);

            // CC to client if email is available
            if ($clientEmail) {
                $mail->cc($clientEmail);
            }

            $mail->send(new ProjectStatusChangedMail($project, null, $project->status));

            Log::info("Project created email sent", [
                'project_id' => $project->id,
                'project_code' => $project->code,
                'status' => $project->status,
                'admin_email' => $adminEmail,
                'client_email' => $clientEmail ?? 'none',
                'from_customer' => !$project->client_email && $clientEmail ? 'yes' : 'no',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send project created email", [
                'project_id' => $project->id,
                'project_code' => $project->code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification when project status changes.
     */
    private function sendStatusChangeEmail(Project $project, string $oldStatus, string $newStatus): void
    {
        try {
            $adminEmail = 'alessandro.cursoli@supernovaindustries.it';

            // Get client email from project or customer
            $clientEmail = $project->client_email ?: $project->customer->email;

            // Always send to admin
            $mail = Mail::to($adminEmail);

            // CC to client if email is available
            if ($clientEmail) {
                $mail->cc($clientEmail);
            }

            $mail->send(new ProjectStatusChangedMail($project, $oldStatus, $newStatus));

            Log::info("Project status change email sent", [
                'project_id' => $project->id,
                'project_code' => $project->code,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'admin_email' => $adminEmail,
                'client_email' => $clientEmail ?? 'none',
                'from_customer' => !$project->client_email && $clientEmail ? 'yes' : 'no',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send project status change email", [
                'project_id' => $project->id,
                'project_code' => $project->code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        // Set timeout to 5 minutes for folder operations
        set_time_limit(300);

        try {
            if ($project->nextcloud_folder_created) {
                // Move project folder to Eliminati archive
                $archived = $this->nextcloudService->archiveProject($project, 'Eliminati');

                if ($archived) {
                    Log::info("Project folder archived to Eliminati: {$project->code}");
                } else {
                    Log::error("Failed to archive deleted project folder: {$project->code}");
                }
            }
        } catch (\Exception $e) {
            Log::error("ProjectObserver::deleted error: " . $e->getMessage());
        }
    }

    /**
     * Handle the Project "forceDeleted" event (hard delete).
     */
    public function forceDeleted(Project $project): void
    {
        // Set timeout to 5 minutes for folder operations
        set_time_limit(300);

        try {
            if ($project->nextcloud_folder_created) {
                // Permanently delete project folder
                $basePath = $this->nextcloudService->getProjectBasePath($project);
                $deleted = $this->nextcloudService->deleteFolder($basePath);

                if ($deleted) {
                    Log::info("Project folder permanently deleted: {$project->code}");
                } else {
                    Log::error("Failed to delete project folder: {$project->code}");
                }
            }
        } catch (\Exception $e) {
            Log::error("ProjectObserver::forceDeleted error: " . $e->getMessage());
        }
    }
}
