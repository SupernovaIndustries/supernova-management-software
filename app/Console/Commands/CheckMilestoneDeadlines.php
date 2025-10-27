<?php

namespace App\Console\Commands;

use App\Models\Milestone;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckMilestoneDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'supernova:check-milestone-deadlines';

    /**
     * The console command description.
     */
    protected $description = 'Check milestone deadlines and send notifications if needed';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Checking milestone deadlines...');

        $milestones = Milestone::query()
            ->where('email_notifications', true)
            ->whereNotNull('deadline')
            ->whereHas('project', function ($query) {
                $query->whereNotNull('client_email');
            })
            ->where('deadline', '>=', now())
            ->with('project.customer')
            ->get();

        $notificationsSent = 0;

        foreach ($milestones as $milestone) {
            $daysUntilDeadline = now()->diffInDays($milestone->deadline, false);
            
            if ($daysUntilDeadline <= $milestone->notification_days_before) {
                if ($notificationService->sendMilestoneDeadlineNotification($milestone)) {
                    $notificationsSent++;
                    $this->line("✓ Notification sent for milestone: {$milestone->name} (Project: {$milestone->project->name})");
                } else {
                    $this->line("✗ Failed to send notification for milestone: {$milestone->name}");
                }
            }
        }

        $this->info("Checked {$milestones->count()} milestones, sent {$notificationsSent} notifications.");

        return Command::SUCCESS;
    }
}