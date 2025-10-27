<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceComponentMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_received_id',
        'invoice_received_item_id',
        'component_id',
        'quantity',
        'unit_price',
        'total_cost',
        'inventory_movement_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    /**
     * Get the invoice for this mapping.
     */
    public function invoiceReceived(): BelongsTo
    {
        return $this->belongsTo(InvoiceReceived::class, 'invoice_received_id');
    }

    /**
     * Get the invoice item for this mapping.
     */
    public function invoiceReceivedItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceReceivedItem::class, 'invoice_received_item_id');
    }

    /**
     * Get the component for this mapping.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the inventory movement for this mapping.
     */
    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }
}
