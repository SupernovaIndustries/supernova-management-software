<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_template_id',
        'title',
        'description',
        'notes',
        'priority',
        'is_blocking',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_blocking' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the checklist template this item belongs to.
     */
    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }

    /**
     * Get project progress entries for this item.
     */
    public function projectProgress(): HasMany
    {
        return $this->hasMany(ProjectChecklistProgress::class);
    }

    /**
     * Scope for active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for critical items.
     */
    public function scopeCritical($query)
    {
        return $query->where('priority', 'critical');
    }

    /**
     * Scope for blocking items.
     */
    public function scopeBlocking($query)
    {
        return $query->where('is_blocking', true);
    }

    /**
     * Scope for ordered items.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Get priority colors.
     */
    public static function getPriorityColors(): array
    {
        return [
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange',
            'critical' => 'red',
        ];
    }

    /**
     * Get priority color for this item.
     */
    public function getPriorityColorAttribute(): string
    {
        return self::getPriorityColors()[$this->priority] ?? 'gray';
    }

    /**
     * Get priority labels.
     */
    public static function getPriorityLabels(): array
    {
        return [
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Critica',
        ];
    }

    /**
     * Get priority label for this item.
     */
    public function getPriorityLabelAttribute(): string
    {
        return self::getPriorityLabels()[$this->priority] ?? 'Media';
    }
}