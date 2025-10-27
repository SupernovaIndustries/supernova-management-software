<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'reason',
        'notes',
        'user_id',
        'invoice_number',
        'invoice_path',
        'invoice_date',
        'invoice_total',
        'supplier',
        'source_invoice_id',
        'destination_project_id',
        'allocation_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'invoice_date' => 'date',
        'invoice_total' => 'decimal:2',
    ];

    /**
     * Get the component for this movement.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the user who created this movement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reference model (polymorphic).
     */
    public function reference()
    {
        if ($this->reference_type && $this->reference_id) {
            return $this->morphTo('reference', 'reference_type', 'reference_id');
        }
        return null;
    }

    /**
     * Get the movement direction (positive or negative).
     */
    public function getDirectionAttribute(): string
    {
        return in_array($this->type, ['in', 'return']) ? 'positive' : 'negative';
    }

    /**
     * Get the absolute quantity value.
     */
    public function getAbsoluteQuantityAttribute(): int
    {
        return abs($this->quantity);
    }

    /**
     * Get the total value of the movement.
     */
    public function getTotalValueAttribute(): float
    {
        return $this->absolute_quantity * ($this->unit_cost ?? 0);
    }

    /**
     * Scope for incoming movements.
     */
    public function scopeIncoming($query)
    {
        return $query->whereIn('type', ['in', 'return']);
    }

    /**
     * Scope for outgoing movements.
     */
    public function scopeOutgoing($query)
    {
        return $query->whereIn('type', ['out', 'adjustment']);
    }

    /**
     * Get the source invoice for this movement.
     */
    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InvoiceReceived::class, 'source_invoice_id');
    }

    /**
     * Get the destination project for this movement.
     */
    public function destinationProject(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Project::class, 'destination_project_id');
    }

    /**
     * Get the allocation for this movement.
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProjectComponentAllocation::class, 'allocation_id');
    }
}