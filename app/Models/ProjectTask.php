<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ProjectTask extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'duration_days',
        'progress_percentage',
        'status',
        'priority',
        'assigned_to',
        'sort_order',
        'color',
        'is_milestone',
        'gantt_position',
        'estimated_hours',
        'actual_hours',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'progress_percentage' => 'decimal:2',
        'is_milestone' => 'boolean',
        'gantt_position' => 'array',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($task) {
            // Auto-calculate duration if not set
            if (!$task->duration_days && $task->start_date && $task->end_date) {
                $task->duration_days = $task->start_date->diffInDays($task->end_date) + 1;
            }
        });
        
        static::updating(function ($task) {
            // Update duration when dates change
            if ($task->isDirty(['start_date', 'end_date']) && $task->start_date && $task->end_date) {
                $task->duration_days = $task->start_date->diffInDays($task->end_date) + 1;
            }
        });
    }

    /**
     * Get the project this task belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user assigned to this task.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get tasks that this task depends on (predecessors).
     */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjectTask::class,
            'project_task_dependencies',
            'successor_task_id',
            'predecessor_task_id'
        )->withPivot(['dependency_type', 'lag_days'])->withTimestamps();
    }

    /**
     * Get tasks that depend on this task (successors).
     */
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjectTask::class,
            'project_task_dependencies',
            'predecessor_task_id',
            'successor_task_id'
        )->withPivot(['dependency_type', 'lag_days'])->withTimestamps();
    }

    /**
     * Get all dependency relationships where this task is involved.
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(ProjectTaskDependency::class, 'predecessor_task_id');
    }

    /**
     * Get all time entries for this task.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'project_task_id');
    }

    /**
     * Get status options.
     */
    public static function getStatusOptions(): array
    {
        return [
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled',
        ];
    }

    /**
     * Get priority options.
     */
    public static function getPriorityOptions(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];
    }

    /**
     * Get dependency type options.
     */
    public static function getDependencyTypeOptions(): array
    {
        return [
            'finish_to_start' => 'Finish to Start (FS)',
            'start_to_start' => 'Start to Start (SS)',
            'finish_to_finish' => 'Finish to Finish (FF)',
            'start_to_finish' => 'Start to Finish (SF)',
        ];
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'completed') {
            return false;
        }
        
        return $this->end_date->isPast();
    }

    /**
     * Check if the task can start based on dependencies.
     */
    public function canStart(): bool
    {
        foreach ($this->predecessors as $predecessor) {
            $dependencyType = $predecessor->pivot->dependency_type;
            
            switch ($dependencyType) {
                case 'finish_to_start':
                    if ($predecessor->status !== 'completed') {
                        return false;
                    }
                    break;
                case 'start_to_start':
                    if (!in_array($predecessor->status, ['in_progress', 'completed'])) {
                        return false;
                    }
                    break;
                // Add logic for other dependency types as needed
            }
        }
        
        return true;
    }

    /**
     * Calculate the critical path for this task.
     */
    public function isOnCriticalPath(): bool
    {
        // Simplified critical path calculation
        // A task is on critical path if delay would delay the project
        return $this->getSlack() === 0;
    }

    /**
     * Get task slack (float) time.
     */
    public function getSlack(): int
    {
        // Simplified slack calculation
        // This would need more complex logic for proper critical path method
        return 0;
    }

    /**
     * Get progress color based on status and progress.
     */
    public function getProgressColor(): string
    {
        return match($this->status) {
            'not_started' => '#94a3b8',
            'in_progress' => $this->isOverdue() ? '#ef4444' : '#3b82f6',
            'completed' => '#22c55e',
            'on_hold' => '#f59e0b',
            'cancelled' => '#6b7280',
            default => '#94a3b8',
        };
    }

    /**
     * Get priority color.
     */
    public function getPriorityColor(): string
    {
        return match($this->priority) {
            'low' => '#22c55e',
            'medium' => '#f59e0b',
            'high' => '#ef4444',
            'critical' => '#991b1b',
            default => '#6b7280',
        };
    }

    /**
     * Update progress percentage and adjust status accordingly.
     */
    public function updateProgress(float $percentage): void
    {
        $this->progress_percentage = max(0, min(100, $percentage));
        
        if ($percentage >= 100) {
            $this->status = 'completed';
            $this->actual_end_date = now()->toDateString();
        } elseif ($percentage > 0 && $this->status === 'not_started') {
            $this->status = 'in_progress';
            if (!$this->actual_start_date) {
                $this->actual_start_date = now()->toDateString();
            }
        }
        
        $this->save();
    }

    /**
     * Get Gantt chart data for this task.
     */
    public function getGanttData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start' => $this->start_date->format('Y-m-d'),
            'end' => $this->end_date->format('Y-m-d'),
            'progress' => $this->progress_percentage,
            'status' => $this->status,
            'priority' => $this->priority,
            'color' => $this->color,
            'assigned_to' => $this->assignedUser?->name,
            'is_milestone' => $this->is_milestone,
            'dependencies' => $this->predecessors->map(function ($predecessor) {
                return [
                    'id' => $predecessor->id,
                    'type' => $predecessor->pivot->dependency_type,
                    'lag' => $predecessor->pivot->lag_days,
                ];
            })->toArray(),
        ];
    }

    /**
     * Scope for tasks in a specific project.
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope for active tasks (not cancelled).
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /**
     * Scope for overdue tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->where('end_date', '<', now())
                    ->whereNotIn('status', ['completed', 'cancelled']);
    }
}
