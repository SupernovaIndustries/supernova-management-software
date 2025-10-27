<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_variant_id',
        'system_phase_id',
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the system variant this template belongs to.
     */
    public function systemVariant(): BelongsTo
    {
        return $this->belongsTo(SystemVariant::class);
    }

    /**
     * Get the system phase this template is for.
     */
    public function systemPhase(): BelongsTo
    {
        return $this->belongsTo(SystemPhase::class);
    }

    /**
     * Get the checklist items for this template.
     */
    public function checklistTemplateItems(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('sort_order');
    }

    /**
     * Get active checklist items only.
     */
    public function activeItems(): HasMany
    {
        return $this->checklistTemplateItems()->where('is_active', true);
    }

    /**
     * Get critical items.
     */
    public function criticalItems(): HasMany
    {
        return $this->checklistTemplateItems()->where('priority', 'critical');
    }

    /**
     * Get blocking items.
     */
    public function blockingItems(): HasMany
    {
        return $this->checklistTemplateItems()->where('is_blocking', true);
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get full template name with variant and phase.
     */
    public function getFullNameAttribute(): string
    {
        return $this->systemVariant->display_name . ' - ' . $this->systemPhase->display_name . ' - ' . $this->name;
    }
}