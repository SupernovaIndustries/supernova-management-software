<?php

namespace App\Console\Commands;

use App\Services\ProjectManagementAiService;
use Illuminate\Console\Command;

class CalculateProjectPriorities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:calculate-priorities
                            {--show-details : Show detailed priority breakdown}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate AI-based priority scores for all active projects';

    /**
     * Execute the console command.
     */
    public function handle(ProjectManagementAiService $service): int
    {
        $this->info('🤖 Starting AI project priority calculation...');
        $this->newLine();

        try {
            $result = $service->calculateProjectPriorities();

            if (!$result['success']) {
                $this->error('Failed to calculate project priorities');
                return Command::FAILURE;
            }

            // Display summary
            $this->info("✅ Analysis completed successfully!");
            $this->info("   Projects analyzed: {$result['projects_analyzed']}");

            if ($result['errors'] > 0) {
                $this->warn("   Errors encountered: {$result['errors']}");
            }

            $this->newLine();

            // Display conflicts
            if (!empty($result['conflicts'])) {
                $this->warn("⚠️  High Priority Projects ({$result['projects_analyzed']} found):");
                $this->newLine();

                $headers = ['Project', 'Priority Score', 'Reason'];
                $rows = [];

                foreach ($result['conflicts'] as $conflict) {
                    $rows[] = [
                        $conflict['project'],
                        $conflict['score'] . '/100',
                        $conflict['reason'],
                    ];
                }

                $this->table($headers, $rows);
                $this->newLine();
            } else {
                $this->info('✓ No critical priority conflicts detected');
                $this->newLine();
            }

            // Display AI suggestions
            if (!empty($result['ai_suggestions'])) {
                $this->info('💡 AI Optimization Suggestions:');
                $this->newLine();
                $this->line($result['ai_suggestions']);
                $this->newLine();
            }

            // Show detailed breakdown if requested
            if ($this->option('show-details')) {
                $this->showDetailedBreakdown();
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Show detailed priority breakdown for all projects.
     */
    private function showDetailedBreakdown(): void
    {
        $this->info('📊 Detailed Priority Breakdown:');
        $this->newLine();

        $projects = \App\Models\Project::active()
            ->whereNotNull('ai_priority_score')
            ->orderBy('ai_priority_score', 'desc')
            ->get();

        if ($projects->isEmpty()) {
            $this->warn('No projects with calculated priorities found');
            return;
        }

        $headers = ['Code', 'Project', 'Score', 'Completion', 'Deadline', 'Status'];
        $rows = [];

        foreach ($projects as $project) {
            $daysLeft = $project->getDaysUntilDeadline();
            $deadlineText = $daysLeft !== null
                ? ($daysLeft < 0 ? "OVERDUE by " . abs($daysLeft) . "d" : "{$daysLeft} days")
                : 'N/A';

            $rows[] = [
                $project->code,
                \Illuminate\Support\Str::limit($project->name, 30),
                $project->ai_priority_score,
                number_format($project->completion_percentage ?? 0, 1) . '%',
                $deadlineText,
                $project->status,
            ];
        }

        $this->table($headers, $rows);
    }
}
