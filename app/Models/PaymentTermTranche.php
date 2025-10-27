<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTermTranche extends Model
{
    protected $fillable = [
        'payment_term_id',
        'name',
        'percentage',
        'days_offset',
        'trigger_event',
        'sort_order',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'days_offset' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the payment term that owns this tranche.
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /**
     * Get formatted percentage display (e.g., "30%")
     */
    public function getPercentageDisplayAttribute(): string
    {
        return number_format($this->percentage, 0) . '%';
    }

    /**
     * Get full description
     */
    public function getFullDescriptionAttribute(): string
    {
        $desc = "{$this->name} ({$this->percentage_display})";

        if ($this->days_offset > 0) {
            $desc .= " - {$this->days_offset} giorni";
        }

        if ($this->trigger_event !== 'contract') {
            $triggerLabel = match($this->trigger_event) {
                'delivery' => 'alla consegna',
                'completion' => 'a completamento',
                'custom' => 'personalizzato',
                default => $this->trigger_event,
            };
            $desc .= " ({$triggerLabel})";
        }

        return $desc;
    }
}
