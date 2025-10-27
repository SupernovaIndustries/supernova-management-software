<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TimeEntry extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'project_task_id',
        'date',
        'start_time',
        'end_time',
        'hours',
        'description',
        'type',
        'is_billable',
        'hourly_rate',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'hours' => 'decimal:2',
        'is_billable' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($entry) {
            // Auto-calculate hours if start/end times provided
            if ($entry->start_time && $entry->end_time && !$entry->hours) {
                $start = Carbon::parse($entry->start_time);
                $end = Carbon::parse($entry->end_time);
                $entry->hours = $end->diffInMinutes($start) / 60;
            }
            
            // Set default hourly rate from user profile if not provided
            if (!$entry->hourly_rate && $entry->user) {
                $entry->hourly_rate = $entry->user->hourly_rate ?? 0;
            }
        });
        
        static::updating(function ($entry) {
            // Recalculate hours if times change
            if ($entry->isDirty(['start_time', 'end_time']) && $entry->start_time && $entry->end_time) {
                $start = Carbon::parse($entry->start_time);
                $end = Carbon::parse($entry->end_time);
                $entry->hours = $end->diffInMinutes($start) / 60;
            }
        });
        
        static::saved(function ($entry) {
            // Update task actual hours when time entry is saved
            if ($entry->project_task_id && $entry->status === 'approved') {
                $entry->updateTaskActualHours();
            }
        });
        
        static::deleted(function ($entry) {
            // Update task actual hours when time entry is deleted
            if ($entry->project_task_id) {
                $entry->updateTaskActualHours();
            }
        });
    }

    /**
     * Get the user who logged this time.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project this time was logged for.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task this time was logged for.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    /**
     * Get the user who approved this entry.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get time type options.
     */
    public static function getTypeOptions(): array
    {
        return [
            'development' => 'Development',
            'testing' => 'Testing',
            'design' => 'Design',
            'meeting' => 'Meeting',
            'documentation' => 'Documentation',
            'research' => 'Research',
            'other' => 'Other',
        ];
    }

    /**
     * Get status options.
     */
    public static function getStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
    }

    /**
     * Calculate billable amount.
     */
    public function getBillableAmountAttribute(): float
    {
        if (!$this->is_billable || !$this->hourly_rate) {
            return 0;
        }
        
        return $this->hours * $this->hourly_rate;
    }

    /**
     * Check if entry can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    /**
     * Check if entry can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Approve the time entry.
     */
    public function approve(int $approvedBy): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    /**
     * Reject the time entry.
     */
    public function reject(int $rejectedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Submit for approval.
     */
    public function submit(): void
    {
        $this->update([
            'status' => 'submitted',
            'rejection_reason' => null,
        ]);
    }

    /**
     * Update task actual hours based on approved time entries.
     */
    protected function updateTaskActualHours(): void
    {
        if (!$this->project_task_id) {
            return;
        }

        $totalHours = static::where('project_task_id', $this->project_task_id)
            ->where('status', 'approved')
            ->sum('hours');

        $this->task()->update(['actual_hours' => $totalHours]);
    }

    /**
     * Get time variance compared to estimate.
     */
    public function getVarianceAttribute(): ?float
    {
        if (!$this->task || !$this->task->estimated_hours) {
            return null;
        }

        $actualHours = $this->task->actual_hours ?? 0;
        return $actualHours - $this->task->estimated_hours;
    }

    /**
     * Get time variance percentage.
     */
    public function getVariancePercentageAttribute(): ?float
    {
        if (!$this->task || !$this->task->estimated_hours) {
            return null;
        }

        $actualHours = $this->task->actual_hours ?? 0;
        return (($actualHours - $this->task->estimated_hours) / $this->task->estimated_hours) * 100;
    }

    /**
     * Scope for entries by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for entries by project.
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope for entries by date range.
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope for billable entries.
     */
    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    /**
     * Scope for approved entries.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for pending approval.
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Get entries summary for a date range.
     */
    public static function getSummary(Carbon $startDate, Carbon $endDate, ?int $userId = null, ?int $projectId = null): array
    {
        $query = static::dateRange($startDate, $endDate);
        
        if ($userId) {
            $query->forUser($userId);
        }
        
        if ($projectId) {
            $query->forProject($projectId);
        }

        $totalHours = $query->sum('hours');
        $billableHours = $query->billable()->sum('hours');
        $totalAmount = $query->billable()->sum(\DB::raw('hours * hourly_rate'));
        $approvedHours = $query->approved()->sum('hours');
        $pendingHours = $query->pendingApproval()->sum('hours');

        return [
            'total_hours' => $totalHours,
            'billable_hours' => $billableHours,
            'non_billable_hours' => $totalHours - $billableHours,
            'total_amount' => $totalAmount,
            'approved_hours' => $approvedHours,
            'pending_hours' => $pendingHours,
            'draft_hours' => $totalHours - $approvedHours - $pendingHours,
        ];
    }
}
