<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskDependency extends Model
{
    protected $fillable = [
        'predecessor_task_id',
        'successor_task_id',
        'dependency_type',
        'lag_days',
    ];

    protected $casts = [
        'lag_days' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($dependency) {
            // Prevent self-referencing dependencies
            if ($dependency->predecessor_task_id === $dependency->successor_task_id) {
                throw new \InvalidArgumentException('A task cannot depend on itself');
            }
            
            // Check for circular dependencies (simplified check)
            if (static::wouldCreateCircularDependency($dependency->predecessor_task_id, $dependency->successor_task_id)) {
                throw new \InvalidArgumentException('This dependency would create a circular dependency');
            }
        });
    }

    /**
     * Get the predecessor task.
     */
    public function predecessorTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'predecessor_task_id');
    }

    /**
     * Get the successor task.
     */
    public function successorTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'successor_task_id');
    }

    /**
     * Check if adding this dependency would create a circular dependency.
     */
    protected static function wouldCreateCircularDependency(int $predecessorId, int $successorId): bool
    {
        // Simple circular dependency check: if successor has predecessor as a successor
        return static::where('predecessor_task_id', $successorId)
            ->where('successor_task_id', $predecessorId)
            ->exists();
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
     * Get the dependency type description.
     */
    public function getDependencyTypeDescription(): string
    {
        return match($this->dependency_type) {
            'finish_to_start' => 'Task B cannot start until Task A finishes',
            'start_to_start' => 'Task B cannot start until Task A starts',
            'finish_to_finish' => 'Task B cannot finish until Task A finishes',
            'start_to_finish' => 'Task B cannot finish until Task A starts',
            default => 'Unknown dependency type',
        };
    }

    /**
     * Calculate the effective date constraint based on dependency type.
     */
    public function calculateConstraintDate(\Carbon\Carbon $predecessorDate): \Carbon\Carbon
    {
        $constraintDate = $predecessorDate->copy();
        
        // Apply lag/lead time
        if ($this->lag_days > 0) {
            $constraintDate->addDays($this->lag_days);
        } elseif ($this->lag_days < 0) {
            $constraintDate->subDays(abs($this->lag_days));
        }
        
        return $constraintDate;
    }
}
