<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\Widget;
use Carbon\Carbon;

class ProjectsTimelineWidget extends Widget
{
    protected static string $view = 'filament.widgets.projects-timeline-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function getProjects()
    {
        return Project::active()
            ->with(['customer', 'milestones'])
            ->whereNotNull('start_date')
            ->whereNotNull('due_date')
            ->orderBy('ai_priority_score', 'desc')
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($project) {
                $startDate = Carbon::parse($project->start_date);
                $dueDate = Carbon::parse($project->due_date);
                $totalDays = $startDate->diffInDays($dueDate) ?: 1;
                $daysFromStart = max(0, $startDate->diffInDays(now(), false));
                $daysUntilDeadline = $project->getDaysUntilDeadline();

                // Get upcoming milestones (next 7 days)
                $upcomingMilestones = $project->milestones()
                    ->wherePivot('is_completed', false)
                    ->wherePivot('target_date', '<=', now()->addDays(7))
                    ->wherePivot('target_date', '>=', now())
                    ->orderByPivot('target_date')
                    ->get();

                return [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'customer' => $project->customer->company_name ?? $project->customer->name ?? 'N/A',
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'completion_percentage' => $project->completion_percentage ?? 0,
                    'ai_priority_score' => $project->ai_priority_score,
                    'status' => $project->status,
                    'days_until_deadline' => $daysUntilDeadline,
                    'total_days' => $totalDays,
                    'days_from_start' => $daysFromStart,
                    'is_overdue' => $daysUntilDeadline !== null && $daysUntilDeadline < 0,
                    'is_nearing_deadline' => $project->isNearingDeadline(7),
                    'upcoming_milestones' => $upcomingMilestones->map(fn($m) => [
                        'name' => $m->name,
                        'target_date' => Carbon::parse($m->pivot->target_date)->format('d/m/Y'),
                        'days_left' => Carbon::parse($m->pivot->target_date)->diffInDays(now(), false),
                    ]),
                    'status_color' => $this->getStatusColor($project),
                    'priority_color' => $this->getPriorityColor($project->ai_priority_score),
                ];
            });
    }

    /**
     * Get overlapping projects (projects running in the same time period).
     */
    public function getOverlappingProjects()
    {
        $projects = $this->getProjects();
        $overlaps = [];

        foreach ($projects as $i => $project1) {
            foreach ($projects as $j => $project2) {
                if ($i >= $j) continue;

                $start1 = $project1['start_date'];
                $end1 = $project1['due_date'];
                $start2 = $project2['start_date'];
                $end2 = $project2['due_date'];

                // Check for overlap
                if ($start1 <= $end2 && $end1 >= $start2) {
                    $overlaps[] = [
                        'project1' => $project1['code'],
                        'project2' => $project2['code'],
                        'overlap_start' => max($start1, $start2)->format('d/m/Y'),
                        'overlap_end' => min($end1, $end2)->format('d/m/Y'),
                    ];
                }
            }
        }

        return $overlaps;
    }

    /**
     * Get upcoming deadlines (next 7 days).
     */
    public function getUpcomingDeadlines()
    {
        return Project::active()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->orderBy('due_date')
            ->get()
            ->map(fn($p) => [
                'code' => $p->code,
                'name' => $p->name,
                'due_date' => $p->due_date->format('d/m/Y'),
                'days_left' => $p->getDaysUntilDeadline(),
                'completion' => $p->completion_percentage ?? 0,
            ]);
    }

    /**
     * Get status color for project.
     */
    private function getStatusColor(Project $project): string
    {
        if ($project->getDaysUntilDeadline() !== null && $project->getDaysUntilDeadline() < 0) {
            return 'danger'; // Overdue
        }

        return match($project->status) {
            'planning' => 'gray',
            'in_progress' => 'primary',
            'testing' => 'warning',
            'consegna_prototipo_test' => 'info',
            'completed' => 'success',
            'on_hold' => 'gray',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get priority color based on AI score.
     */
    private function getPriorityColor(?int $score): string
    {
        if ($score === null) return 'gray';

        if ($score >= 90) return 'danger';
        if ($score >= 70) return 'warning';
        if ($score >= 50) return 'info';
        return 'success';
    }
}
