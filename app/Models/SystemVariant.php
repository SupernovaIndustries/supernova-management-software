<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SystemVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_category_id',
        'name',
        'display_name',
        'description', 
        'specifications',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'specifications' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the category this variant belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SystemCategory::class, 'system_category_id');
    }

    /**
     * Get checklist templates for this variant.
     */
    public function checklistTemplates(): HasMany
    {
        return $this->hasMany(ChecklistTemplate::class);
    }

    /**
     * Get project instances using this variant.
     */
    public function projectInstances(): HasMany
    {
        return $this->hasMany(ProjectSystemInstance::class);
    }

    /**
     * Get components mapped to this variant.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_system_mappings')
            ->withPivot(['is_auto_detected', 'confidence_score'])
            ->withTimestamps();
    }

    /**
     * Get component mappings.
     */
    public function componentMappings(): HasMany
    {
        return $this->hasMany(ComponentSystemMapping::class);
    }

    /**
     * Scope for active variants.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get full display name with category.
     */
    public function getFullNameAttribute(): string
    {
        return $this->category->display_name . ' - ' . $this->display_name;
    }
}