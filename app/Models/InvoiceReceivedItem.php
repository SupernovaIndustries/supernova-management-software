<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReceivedItem extends Model
{
    use HasFactory;

    protected $table = 'invoice_received_items';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'subtotal',
        'tax_amount',
        'total',
        'component_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
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
        $taxRate = $this->tax_rate ?? 22;

        $subtotal = $quantity * $unitPrice;
        $taxAmount = $subtotal * ($taxRate / 100);

        $this->attributes['subtotal'] = round($subtotal, 2);
        $this->attributes['tax_amount'] = round($taxAmount, 2);
        $this->attributes['total'] = round($subtotal + $taxAmount, 2);
    }

    /**
     * Get the invoice
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceReceived::class, 'invoice_id');
    }

    /**
     * Get the component
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
