<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentAlternative extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_component_id',
        'alternative_component_id',
        'alternative_type',
        'compatibility_score',
        'compatibility_notes',
        'differences',
        'is_recommended',
    ];

    protected $casts = [
        'compatibility_score' => 'decimal:2',
        'differences' => 'array',
        'is_recommended' => 'boolean',
    ];

    public function originalComponent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'original_component_id');
    }

    public function alternativeComponent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'alternative_component_id');
    }

    /**
     * Get compatibility score as percentage
     */
    public function getCompatibilityPercentageAttribute(): int
    {
        return (int) ($this->compatibility_score * 100);
    }

    /**
     * Get compatibility level description
     */
    public function getCompatibilityLevelAttribute(): string
    {
        $score = $this->compatibility_score;
        
        if ($score >= 0.95) return 'Excellent';
        if ($score >= 0.85) return 'Good';
        if ($score >= 0.70) return 'Fair';
        return 'Poor';
    }

    /**
     * Get alternative type label
     */
    public function getAlternativeTypeLabelAttribute(): string
    {
        return match($this->alternative_type) {
            'direct_replacement' => 'Direct Replacement',
            'functional_equivalent' => 'Functional Equivalent',
            'pin_compatible' => 'Pin Compatible',
            'form_factor_compatible' => 'Form Factor Compatible',
            default => 'Unknown'
        };
    }

    /**
     * Scope for recommended alternatives
     */
    public function scopeRecommended($query)
    {
        return $query->where('is_recommended', true);
    }

    /**
     * Scope for high compatibility alternatives
     */
    public function scopeHighCompatibility($query, float $threshold = 0.85)
    {
        return $query->where('compatibility_score', '>=', $threshold);
    }

    /**
     * Scope by alternative type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('alternative_type', $type);
    }
}