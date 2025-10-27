<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ComponentLifecycleStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'lifecycle_stage',
        'eol_announcement_date',
        'eol_date',
        'last_time_buy_date',
        'eol_reason',
        'manufacturer_notes',
    ];

    protected $casts = [
        'eol_announcement_date' => 'date',
        'eol_date' => 'date',
        'last_time_buy_date' => 'date',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get urgency level based on lifecycle stage and dates
     */
    public function getUrgencyLevelAttribute(): string
    {
        if ($this->lifecycle_stage === 'obsolete') {
            return 'critical';
        }

        if ($this->lifecycle_stage === 'eol' || 
            ($this->eol_date && $this->eol_date->isPast())) {
            return 'critical';
        }

        if ($this->lifecycle_stage === 'eol_announced') {
            if ($this->eol_date && $this->eol_date->diffInMonths() < 6) {
                return 'high';
            }
            return 'medium';
        }

        if ($this->lifecycle_stage === 'nrnd') {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get days until EOL
     */
    public function getDaysUntilEolAttribute(): ?int
    {
        return $this->eol_date ? now()->diffInDays($this->eol_date, false) : null;
    }

    /**
     * Check if component is at risk
     */
    public function isAtRisk(): bool
    {
        return in_array($this->urgency_level, ['medium', 'high', 'critical']);
    }

    /**
     * Scope for components at risk
     */
    public function scopeAtRisk($query)
    {
        return $query->whereIn('lifecycle_stage', ['nrnd', 'eol_announced', 'eol', 'obsolete']);
    }

    /**
     * Scope for urgent components (EOL soon)
     */
    public function scopeUrgent($query)
    {
        return $query->where(function ($q) {
            $q->where('lifecycle_stage', 'eol')
              ->orWhere('lifecycle_stage', 'obsolete')
              ->orWhere(function ($sq) {
                  $sq->where('lifecycle_stage', 'eol_announced')
                     ->where('eol_date', '<=', now()->addMonths(6));
              });
        });
    }
}