<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssemblyChecklistTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'board_type',
        'complexity_level',
        'pcb_specifications',
        'is_active',
        'is_default',
        'estimated_time_minutes',
        'requirements',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'pcb_specifications' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the user who created this template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all items for this template.
     */
    public function items(): HasMany
    {
        return $this->hasMany(AssemblyChecklistItem::class, 'template_id')->orderBy('sort_order');
    }

    /**
     * Get all checklists using this template.
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(AssemblyChecklist::class, 'template_id');
    }

    /**
     * Get board type options.
     */
    public static function getBoardTypeOptions(): array
    {
        return [
            'smd' => 'SMD Components Only',
            'through_hole' => 'Through-Hole Components Only',
            'mixed' => 'Mixed SMD + Through-Hole',
            'prototype' => 'Prototype Assembly',
            'production' => 'Production Assembly',
            'generic' => 'Generic Board',
        ];
    }

    /**
     * Get complexity level options.
     */
    public static function getComplexityOptions(): array
    {
        return [
            'simple' => 'Simple (Basic components, large packages)',
            'medium' => 'Medium (Standard complexity)',
            'complex' => 'Complex (Fine pitch, many components)',
            'expert' => 'Expert (BGA, micro components, specialized)',
        ];
    }

    /**
     * Get items grouped by category.
     */
    public function getItemsByCategory(): array
    {
        $items = $this->items()->get()->groupBy('category');
        
        // Ensure categories are in logical order
        $orderedCategories = ['Pre-assembly', 'SMD Placement', 'Through-Hole', 'Soldering', 'Testing', 'Final Inspection', 'Packaging'];
        $ordered = [];
        
        foreach ($orderedCategories as $category) {
            if ($items->has($category)) {
                $ordered[$category] = $items[$category];
            }
        }
        
        // Add any remaining categories
        foreach ($items as $category => $categoryItems) {
            if (!in_array($category, $orderedCategories)) {
                $ordered[$category ?: 'Uncategorized'] = $categoryItems;
            }
        }
        
        return $ordered;
    }

    /**
     * Get total estimated time in minutes.
     */
    public function getTotalEstimatedTimeAttribute(): int
    {
        return $this->items()->sum('estimated_minutes') ?: $this->estimated_time_minutes ?: 0;
    }

    /**
     * Get critical items count.
     */
    public function getCriticalItemsCountAttribute(): int
    {
        return $this->items()->where('is_critical', true)->count();
    }

    /**
     * Check if template can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->checklists()->count() === 0;
    }

    /**
     * Clone template with all items.
     */
    public function clone(string $newName, ?int $userId = null): self
    {
        $clone = $this->replicate();
        $clone->name = $newName;
        $clone->is_default = false;
        $clone->created_by = $userId ?: auth()->id();
        $clone->save();

        // Clone all items
        foreach ($this->items as $item) {
            $itemClone = $item->replicate();
            $itemClone->template_id = $clone->id;
            $itemClone->save();
        }

        return $clone;
    }

    /**
     * Create checklist instance for a project.
     */
    public function createChecklistForProject(int $projectId, array $options = []): AssemblyChecklist
    {
        $checklist = AssemblyChecklist::create([
            'template_id' => $this->id,
            'project_id' => $projectId,
            'board_serial_number' => $options['board_serial_number'] ?? null,
            'batch_number' => $options['batch_number'] ?? null,
            'board_quantity' => $options['board_quantity'] ?? 1,
            'assigned_to' => $options['assigned_to'] ?? null,
            'supervisor_id' => $options['supervisor_id'] ?? null,
            'total_items' => $this->items()->count(),
            'board_specifications' => $options['board_specifications'] ?? $this->pcb_specifications,
            'requires_supervisor_approval' => $options['requires_supervisor_approval'] ?? $this->complexity_level === 'expert',
        ]);

        // Create response records for all items
        foreach ($this->items as $item) {
            AssemblyChecklistResponse::create([
                'checklist_id' => $checklist->id,
                'item_id' => $item->id,
                'user_id' => $options['assigned_to'] ?? auth()->id(),
                'status' => 'pending',
            ]);
        }

        return $checklist;
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
     * Scope by board type.
     */
    public function scopeForBoardType($query, string $boardType)
    {
        return $query->where('board_type', $boardType);
    }
}
