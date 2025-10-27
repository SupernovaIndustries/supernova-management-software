<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckProjectDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'supernova:check-project-deadlines';

    /**
     * The console command description.
     */
    protected $description = 'Check project deadlines and send notifications if needed';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Checking project deadlines...');

        $projects = Project::query()
            ->where('email_notifications', true)
            ->whereNotNull('end_date')
            ->whereNotNull('client_email')
            ->where('end_date', '>=', now())
            ->get();

        $notificationsSent = 0;

        foreach ($projects as $project) {
            $daysUntilDeadline = now()->diffInDays($project->end_date, false);
            
            if ($daysUntilDeadline <= $project->notification_days_before) {
                if ($notificationService->sendProjectDeadlineNotification($project)) {
                    $notificationsSent++;
                    $this->line("✓ Notification sent for project: {$project->name}");
                } else {
                    $this->line("✗ Failed to send notification for project: {$project->name}");
                }
            }
        }

        $this->info("Checked {$projects->count()} projects, sent {$notificationsSent} notifications.");

        return Command::SUCCESS;
    }
}