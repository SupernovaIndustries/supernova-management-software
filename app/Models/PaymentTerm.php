<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTerm extends Model
{
    protected $fillable = [
        'name',
        'description',
        'days',
        'discount_percentage',
        'discount_days',
        'active'
    ];

    protected $casts = [
        'days' => 'integer',
        'discount_percentage' => 'decimal:2',
        'discount_days' => 'integer',
        'active' => 'boolean'
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Get all tranches for this payment term.
     */
    public function tranches(): HasMany
    {
        return $this->hasMany(PaymentTermTranche::class)->orderBy('sort_order');
    }

    /**
     * Check if this payment term has tranches defined.
     */
    public function getHasTranchesAttribute(): bool
    {
        return $this->tranches()->exists();
    }

    /**
     * Get formatted tranches display (e.g., "30/70" or "30/30/40")
     */
    public function getTranchesDisplayAttribute(): string
    {
        if (!$this->has_tranches) {
            return "{$this->days} giorni";
        }

        $percentages = $this->tranches->pluck('percentage')->map(function ($pct) {
            return number_format($pct, 0);
        })->implode('/');

        return $percentages;
    }

    /**
     * Get full description with tranches
     */
    public function getFullDescriptionAttribute(): string
    {
        if (!$this->has_tranches) {
            return "{$this->name} - {$this->days} giorni";
        }

        $tranchesDesc = $this->tranches->map(function ($tranche) {
            return $tranche->full_description;
        })->implode(', ');

        return "{$this->name}: {$tranchesDesc}";
    }
}
