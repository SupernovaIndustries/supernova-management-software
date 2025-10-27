<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChecklistProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_system_instance_id',
        'checklist_template_item_id',
        'system_phase_id',
        'is_completed',
        'completion_notes',
        'completed_by',
        'completed_at',
        'custom_notes',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the project system instance this progress belongs to.
     */
    public function projectSystemInstance(): BelongsTo
    {
        return $this->belongsTo(ProjectSystemInstance::class);
    }

    /**
     * Get the checklist template item.
     */
    public function checklistTemplateItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplateItem::class);
    }

    /**
     * Get the system phase.
     */
    public function systemPhase(): BelongsTo
    {
        return $this->belongsTo(SystemPhase::class);
    }

    /**
     * Get the user who completed this item.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Mark item as completed.
     */
    public function markCompleted(User $user, string $notes = null): void
    {
        $this->update([
            'is_completed' => true,
            'completed_by' => $user->id,
            'completed_at' => now(),
            'completion_notes' => $notes,
        ]);
    }

    /**
     * Mark item as not completed.
     */
    public function markIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_by' => null,
            'completed_at' => null,
            'completion_notes' => null,
        ]);
    }

    /**
     * Scope for completed items.
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope for pending items.
     */
    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope for critical items.
     */
    public function scopeCritical($query)
    {
        return $query->whereHas('checklistTemplateItem', function ($q) {
            $q->where('priority', 'critical');
        });
    }

    /**
     * Scope for blocking items.
     */
    public function scopeBlocking($query)
    {
        return $query->whereHas('checklistTemplateItem', function ($q) {
            $q->where('is_blocking', true);
        });
    }

    /**
     * Check if this item is critical.
     */
    public function getIsCriticalAttribute(): bool
    {
        return $this->checklistTemplateItem->priority === 'critical';
    }

    /**
     * Check if this item is blocking.
     */
    public function getIsBlockingAttribute(): bool
    {
        return $this->checklistTemplateItem->is_blocking;
    }
}