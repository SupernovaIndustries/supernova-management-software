<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSystemInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'system_variant_id',
        'component_id',
        'instance_name',
        'custom_notes',
        'custom_specifications',
        'is_active',
    ];

    protected $casts = [
        'custom_specifications' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the project this instance belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the system variant.
     */
    public function systemVariant(): BelongsTo
    {
        return $this->belongsTo(SystemVariant::class);
    }

    /**
     * Get the component that triggered this system (if any).
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get checklist progress for this instance.
     */
    public function checklistProgress(): HasMany
    {
        return $this->hasMany(ProjectChecklistProgress::class);
    }

    /**
     * Calculate completion percentage.
     */
    public function getCompletionPercentageAttribute(): float
    {
        $total = $this->checklistProgress()->count();
        if ($total === 0) return 0;
        
        $completed = $this->checklistProgress()->where('is_completed', true)->count();
        return round(($completed / $total) * 100, 1);
    }

    /**
     * Get completion status by phase.
     */
    public function getPhaseCompletionAttribute(): array
    {
        $phases = [];
        $progressByPhase = $this->checklistProgress()
            ->with('systemPhase')
            ->get()
            ->groupBy('system_phase_id');

        foreach ($progressByPhase as $phaseId => $items) {
            $total = $items->count();
            $completed = $items->where('is_completed', true)->count();
            $phase = $items->first()->systemPhase;
            
            $phases[] = [
                'phase' => $phase,
                'total' => $total,
                'completed' => $completed,
                'percentage' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        }

        return $phases;
    }

    /**
     * Check if all critical items are completed.
     */
    public function getCriticalItemsCompletedAttribute(): bool
    {
        $criticalItems = $this->checklistProgress()
            ->whereHas('checklistTemplateItem', function ($query) {
                $query->where('priority', 'critical')->where('is_blocking', true);
            })
            ->get();

        return $criticalItems->every('is_completed');
    }

    /**
     * Scope for active instances.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}