<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssemblyChecklist extends Model
{
    protected $fillable = [
        'template_id',
        'project_id',
        'board_assembly_log_id',
        'board_serial_number',
        'batch_number',
        'board_quantity',
        'status',
        'assigned_to',
        'supervisor_id',
        'started_at',
        'completed_at',
        'completion_percentage',
        'total_items',
        'completed_items',
        'failed_items',
        'notes',
        'board_specifications',
        'requires_supervisor_approval',
        'approved_by',
        'approved_at',
        'failure_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'completion_percentage' => 'decimal:2',
        'board_specifications' => 'array',
        'requires_supervisor_approval' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::updated(function ($checklist) {
            // Auto-update completion percentage when items change
            $checklist->updateCompletionStats();
        });
    }

    /**
     * Get the template for this checklist.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AssemblyChecklistTemplate::class, 'template_id');
    }

    /**
     * Get the project this checklist belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the board assembly log this checklist belongs to.
     */
    public function boardAssemblyLog(): BelongsTo
    {
        return $this->belongsTo(BoardAssemblyLog::class, 'board_assembly_log_id');
    }

    /**
     * Get the assigned user.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the supervisor.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Get the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get all responses for this checklist.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(AssemblyChecklistResponse::class, 'checklist_id');
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
            'failed' => 'Failed',
            'on_hold' => 'On Hold',
        ];
    }

    /**
     * Update completion statistics.
     */
    public function updateCompletionStats(): void
    {
        $responses = $this->responses();
        $total = $responses->count();
        $completed = $responses->where('status', 'completed')->count();
        $failed = $responses->where('status', 'failed')->count();
        
        $this->update([
            'total_items' => $total,
            'completed_items' => $completed,
            'failed_items' => $failed,
            'completion_percentage' => $total > 0 ? ($completed / $total) * 100 : 0,
        ]);
    }

    /**
     * Start the checklist.
     */
    public function start(?int $userId = null): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'assigned_to' => $userId ?: $this->assigned_to ?: auth()->id(),
        ]);
    }

    /**
     * Complete the checklist.
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_percentage' => 100,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    /**
     * Put on hold.
     */
    public function putOnHold(string $reason): void
    {
        $this->update([
            'status' => 'on_hold',
            'notes' => ($this->notes ? $this->notes . "\n\n" : '') . "On Hold: " . $reason,
        ]);
    }

    /**
     * Resume from hold.
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'in_progress',
        ]);
    }

    /**
     * Check if checklist can be started.
     */
    public function canBeStarted(): bool
    {
        return $this->status === 'not_started';
    }

    /**
     * Check if checklist can be completed.
     */
    public function canBeCompleted(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        // Check if all critical items are completed
        $criticalItems = $this->template->items()->where('is_critical', true)->pluck('id');
        $completedCritical = $this->responses()
            ->whereIn('item_id', $criticalItems)
            ->where('status', 'completed')
            ->count();

        return $completedCritical === $criticalItems->count();
    }

    /**
     * Get completion percentage by category.
     */
    public function getCompletionByCategory(): array
    {
        $categories = [];
        $items = $this->template->items()->with('responses')->get();
        
        foreach ($items->groupBy('category') as $category => $categoryItems) {
            $total = $categoryItems->count();
            $completed = $categoryItems->filter(function ($item) {
                return $item->responses->where('checklist_id', $this->id)
                    ->where('status', 'completed')->count() > 0;
            })->count();
            
            $categories[$category ?: 'Uncategorized'] = [
                'total' => $total,
                'completed' => $completed,
                'percentage' => $total > 0 ? ($completed / $total) * 100 : 0,
            ];
        }
        
        return $categories;
    }

    /**
     * Get next pending item.
     */
    public function getNextPendingItem(): ?AssemblyChecklistItem
    {
        $nextResponse = $this->responses()
            ->where('status', 'pending')
            ->with('item')
            ->orderBy('id')
            ->first();
            
        return $nextResponse?->item;
    }

    /**
     * Get failed items that need attention.
     */
    public function getFailedItems(): array
    {
        return $this->responses()
            ->where('status', 'failed')
            ->with('item')
            ->get()
            ->map(function ($response) {
                return [
                    'item' => $response->item,
                    'failure_reason' => $response->failure_reason,
                    'failed_at' => $response->updated_at,
                ];
            })
            ->toArray();
    }

    /**
     * Check if supervisor approval is required and pending.
     */
    public function needsSupervisorApproval(): bool
    {
        return $this->requires_supervisor_approval && 
               $this->status === 'completed' && 
               !$this->approved_by;
    }

    /**
     * Approve by supervisor.
     */
    public function approveBySuperviosr(int $supervisorId): void
    {
        $this->update([
            'approved_by' => $supervisorId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Get estimated time remaining.
     */
    public function getEstimatedTimeRemaining(): int
    {
        $pendingItems = $this->responses()
            ->where('status', 'pending')
            ->with('item')
            ->get();
            
        return $pendingItems->sum(function ($response) {
            return $response->item->estimated_minutes ?? 0;
        });
    }

    /**
     * Get actual time spent so far.
     */
    public function getActualTimeSpent(): int
    {
        $completedResponses = $this->responses()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->get();
            
        $totalMinutes = 0;
        foreach ($completedResponses as $response) {
            if ($response->created_at && $response->completed_at) {
                $totalMinutes += $response->created_at->diffInMinutes($response->completed_at);
            }
        }
        
        return $totalMinutes;
    }

    /**
     * Generate QR code for mobile access.
     */
    public function generateQRCode(): string
    {
        $url = route('mobile.checklist', $this->id);
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($url);
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope active checklists.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['not_started', 'in_progress', 'on_hold']);
    }

    /**
     * Scope completed checklists.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
