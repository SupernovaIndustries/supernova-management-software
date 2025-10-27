<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentSystemMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'system_variant_id',
        'is_auto_detected',
        'confidence_score',
    ];

    protected $casts = [
        'is_auto_detected' => 'boolean',
        'confidence_score' => 'integer',
    ];

    /**
     * Get the component.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the system variant.
     */
    public function systemVariant(): BelongsTo
    {
        return $this->belongsTo(SystemVariant::class);
    }

    /**
     * Scope for auto-detected mappings.
     */
    public function scopeAutoDetected($query)
    {
        return $query->where('is_auto_detected', true);
    }

    /**
     * Scope for manual mappings.
     */
    public function scopeManual($query)
    {
        return $query->where('is_auto_detected', false);
    }

    /**
     * Scope for high confidence mappings.
     */
    public function scopeHighConfidence($query)
    {
        return $query->where('confidence_score', '>=', 80);
    }
}