<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceReceived extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'supplier_id',
        'supplier_name',
        'supplier_vat',
        'type',
        'category',
        'description',
        'project_id',
        'customer_id',
        'issue_date',
        'due_date',
        'received_date',
        'subtotal',
        'tax_amount',
        'total',
        'currency',
        'payment_status',
        'amount_paid',
        'paid_at',
        'payment_method',
        'nextcloud_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'received_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the supplier for this invoice.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the project for this invoice (if applicable).
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the customer for this invoice (if applicable).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all items for this invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceReceivedItem::class, 'invoice_id');
    }

    /**
     * Get all component mappings for this invoice.
     */
    public function componentMappings(): HasMany
    {
        return $this->hasMany(InvoiceComponentMapping::class, 'invoice_received_id');
    }

    /**
     * Get all project component allocations sourced from this invoice.
     */
    public function projectAllocations(): HasMany
    {
        return $this->hasMany(ProjectComponentAllocation::class, 'source_invoice_id');
    }

    /**
     * Get all inventory movements linked to this invoice.
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'source_invoice_id');
    }

    /**
     * Get the user who created this invoice.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date < now() && $this->payment_status !== 'paid';
    }

    /**
     * Calculate remaining amount.
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->total - $this->amount_paid;
    }

    /**
     * Scope for unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', 'paid');
    }

    /**
     * Scope for overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('payment_status', '!=', 'paid');
    }

    /**
     * Scope by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by supplier.
     */
    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Calculate totals from items.
     */
    public function calculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum('subtotal');
        $this->tax_amount = $items->sum('tax_amount');
        $this->total = $items->sum('total');

        $this->save();
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(float $amount = null, string $paymentMethod = null): void
    {
        $this->amount_paid = $amount ?? $this->total;
        $this->payment_status = 'paid';
        $this->paid_at = now();
        $this->payment_method = $paymentMethod;
        $this->save();
    }

    /**
     * Link components from items.
     */
    public function linkComponents(): void
    {
        foreach ($this->items as $item) {
            if ($item->component_id) {
                InvoiceComponentMapping::firstOrCreate([
                    'invoice_received_id' => $this->id,
                    'invoice_received_item_id' => $item->id,
                    'component_id' => $item->component_id,
                ], [
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_cost' => $item->total,
                ]);
            }
        }
    }
}
