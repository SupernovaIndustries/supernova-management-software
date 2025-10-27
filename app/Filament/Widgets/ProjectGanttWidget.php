<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\Widget;
use Carbon\Carbon;

class ProjectGanttWidget extends Widget
{
    protected static string $view = 'filament.widgets.project-gantt-widget';
    
    protected int | string | array $columnSpan = 'full';

    public function getProjects()
    {
        return Project::with(['customer', 'progress'])
            ->whereNotNull('start_date')
            ->whereNotNull('due_date')
            ->where('project_status', 'active')
            ->orderBy('start_date')
            ->get()
            ->map(function ($project) {
                $startDate = Carbon::parse($project->start_date);
                $dueDate = Carbon::parse($project->due_date);
                $totalDays = $startDate->diffInDays($dueDate);
                $progressPercentage = $project->progress?->percentage ?? 0;
                $progressDays = $totalDays * ($progressPercentage / 100);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'customer' => $project->customer->company_name ?? $project->customer->name ?? 'N/A',
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'progress' => $progressPercentage,
                    'status' => $project->status,
                    'days_from_start' => $startDate->diffInDays(now()),
                    'total_days' => $totalDays,
                    'progress_days' => $progressDays,
                    'is_overdue' => $dueDate->isPast() && $project->status !== 'completed',
                    'status_color' => match($project->status) {
                        'planning' => 'bg-blue-500',
                        'in_progress' => 'bg-yellow-500',
                        'testing' => 'bg-purple-500',
                        'consegna_prototipo_test' => 'bg-orange-500',
                        'completed' => 'bg-green-500',
                        'on_hold' => 'bg-gray-500',
                        'cancelled' => 'bg-red-500',
                        default => 'bg-gray-400',
                    }
                ];
            });
    }

    public function getEarliestDate()
    {
        $projects = $this->getProjects();
        return $projects->min('start_date') ?? now()->subDays(30);
    }

    public function getLatestDate()
    {
        $projects = $this->getProjects();
        return $projects->max('due_date') ?? now()->addDays(30);
    }

    public function getTotalTimespan()
    {
        return $this->getEarliestDate()->diffInDays($this->getLatestDate());
    }
}