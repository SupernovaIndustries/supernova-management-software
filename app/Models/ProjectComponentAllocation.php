<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectComponentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'component_id',
        'quantity_allocated',
        'quantity_used',
        'quantity_remaining',
        'project_bom_item_id',
        'status',
        'unit_cost',
        'total_cost',
        'source_invoice_id',
        'allocated_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'quantity_allocated' => 'decimal:2',
        'quantity_used' => 'decimal:2',
        'quantity_remaining' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'allocated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the project for this allocation.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the component for this allocation.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the source invoice for this allocation.
     */
    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceReceived::class, 'source_invoice_id');
    }

    /**
     * Get the BOM item for this allocation.
     */
    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(ProjectBomItem::class, 'project_bom_item_id');
    }

    /**
     * Get all inventory movements for this allocation.
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'allocation_id');
    }

    /**
     * Scope for allocated status.
     */
    public function scopeAllocated($query)
    {
        return $query->where('status', 'allocated');
    }

    /**
     * Scope for in-use status.
     */
    public function scopeInUse($query)
    {
        return $query->where('status', 'in_use');
    }

    /**
     * Scope for completed status.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for returned status.
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    /**
     * Check if allocation is fully used.
     */
    public function isFullyUsed(): bool
    {
        return $this->quantity_remaining <= 0;
    }

    /**
     * Get usage percentage.
     */
    public function getUsagePercentageAttribute(): float
    {
        if ($this->quantity_allocated == 0) {
            return 0;
        }

        return round(($this->quantity_used / $this->quantity_allocated) * 100, 2);
    }

    /**
     * Allocate components to project.
     */
    public function allocate(): void
    {
        $this->status = 'allocated';
        $this->allocated_at = now();
        $this->quantity_remaining = $this->quantity_allocated - $this->quantity_used;
        $this->save();
    }

    /**
     * Use allocated components.
     */
    public function use(float $quantity): void
    {
        $this->quantity_used += $quantity;
        $this->quantity_remaining = $this->quantity_allocated - $this->quantity_used;

        if ($this->quantity_remaining <= 0) {
            $this->status = 'completed';
            $this->completed_at = now();
        } else {
            $this->status = 'in_use';
        }

        $this->save();
    }

    /**
     * Complete the allocation.
     */
    public function complete(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Return unused components.
     */
    public function returnComponents(float $quantity): void
    {
        $this->quantity_used -= $quantity;
        $this->quantity_remaining = $this->quantity_allocated - $this->quantity_used;
        $this->status = 'returned';
        $this->save();
    }
}
