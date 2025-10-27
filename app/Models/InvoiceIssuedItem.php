<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceIssuedItem extends Model
{
    use HasFactory;

    protected $table = 'invoice_issued_items';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percentage',
        'tax_rate',
        'subtotal',
        'tax_amount',
        'total',
        'component_id',
        'project_bom_item_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateTotals();
        });
    }

    /**
     * Calculate item totals
     */
    public function calculateTotals(): void
    {
        $quantity = $this->quantity ?? 1;
        $unitPrice = $this->unit_price ?? 0;
        $discountPercentage = $this->discount_percentage ?? 0;
        $taxRate = $this->tax_rate ?? 22;

        $subtotal = $quantity * $unitPrice;
        $discountAmount = $subtotal * ($discountPercentage / 100);
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = $taxableAmount * ($taxRate / 100);

        $this->attributes['subtotal'] = round($subtotal - $discountAmount, 2);
        $this->attributes['tax_amount'] = round($taxAmount, 2);
        $this->attributes['total'] = round($taxableAmount + $taxAmount, 2);
    }

    /**
     * Get the invoice
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceIssued::class, 'invoice_id');
    }

    /**
     * Get the component
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the project BOM item
     */
    public function projectBomItem(): BelongsTo
    {
        return $this->belongsTo(ProjectBomItem::class);
    }
}
