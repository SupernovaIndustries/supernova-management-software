<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($quotationItem) {
            // Auto-populate fields based on item type
            if ($quotationItem->item_type) {
                $quotationItem->populateByItemType();
                $quotationItem->calculateTotalByType();
            }
        });
        
        static::updating(function ($quotationItem) {
            // Recalculate total when updating
            if ($quotationItem->item_type) {
                $quotationItem->calculateTotalByType();
            }
        });
    }

    protected $fillable = [
        'quotation_id',
        'type',
        'item_type',
        'component_id',
        'description',
        'custom_description',
        'quantity',
        'hours',
        'hourly_rate',
        'material_cost',
        'is_from_inventory',
        'unit_price',
        'discount_rate',
        'discount_amount',
        'total',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'material_cost' => 'decimal:2',
        'is_from_inventory' => 'boolean',
        'unit_price' => 'decimal:4',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Get the quotation this item belongs to.
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Get the component for this item.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Calculate the total for this item.
     */
    public function calculateTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_price;
        $discountAmount = $subtotal * ($this->discount_rate / 100);
        
        $this->update([
            'discount_amount' => $discountAmount,
            'total' => $subtotal - $discountAmount,
        ]);
    }

    /**
     * Get the subtotal before discount.
     */
    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Get available item types.
     */
    public static function getItemTypes(): array
    {
        return [
            'design' => 'Progettazione',
            'electronics_materials' => 'Materiali Elettronica',
            'pcb_production' => 'Produzione PCB',
            'assembly' => 'Assemblaggio',
            'housing_design' => 'Progettazione Housing',
            'housing_production' => 'Produzione Housing',
            'housing_materials' => 'Materiali Housing',
            'custom' => 'Personalizzato',
        ];
    }

    /**
     * Calculate total based on item type.
     */
    public function calculateTotalByType(): void
    {
        $total = 0;
        
        switch ($this->item_type) {
            case 'design':
            case 'assembly':
            case 'housing_design':
                // Hours * hourly rate
                $total = ($this->hours ?? 0) * ($this->hourly_rate ?? 0);
                break;
                
            case 'electronics_materials':
            case 'housing_materials':
                // Material cost from inventory or manual
                $total = $this->material_cost ?? 0;
                break;
                
            case 'pcb_production':
                // Standard PCB cost
                $profile = \App\Models\CompanyProfile::current();
                $total = $profile->pcb_standard_cost * ($this->quantity ?? 1);
                break;
                
            case 'housing_production':
            case 'custom':
            default:
                // Standard quantity * unit price
                $total = ($this->quantity ?? 0) * ($this->unit_price ?? 0);
                break;
        }
        
        // Apply discount
        $discountAmount = $total * (($this->discount_rate ?? 0) / 100);
        
        $this->attributes['discount_amount'] = round($discountAmount, 2);
        $this->attributes['total'] = round($total - $discountAmount, 2);
        $this->attributes['unit_price'] = $this->quantity > 0 ? round($total / $this->quantity, 4) : $total;
    }

    /**
     * Auto-populate fields based on item type.
     */
    public function populateByItemType(): void
    {
        $profile = \App\Models\CompanyProfile::current();
        
        switch ($this->item_type) {
            case 'design':
                $this->attributes['description'] = 'Ore di progettazione elettronica';
                $this->attributes['hourly_rate'] = $profile->hourly_rate_design;
                $this->attributes['quantity'] = 1;
                break;
                
            case 'assembly':
                $this->attributes['description'] = 'Ore di assemblaggio';
                $this->attributes['hourly_rate'] = $profile->hourly_rate_assembly;
                $this->attributes['quantity'] = 1;
                break;
                
            case 'pcb_production':
                $this->attributes['description'] = "Produzione PCB + spedizione ({$profile->pcb_standard_quantity} schede)";
                $this->attributes['unit_price'] = $profile->pcb_standard_cost;
                $this->attributes['quantity'] = 1;
                break;
                
            case 'electronics_materials':
                $this->attributes['description'] = 'Materiali elettronici (da BOM)';
                $this->attributes['is_from_inventory'] = true;
                break;
                
            case 'housing_design':
                $this->attributes['description'] = 'Progettazione Housing/Box 3D';
                $this->attributes['hourly_rate'] = $profile->hourly_rate_design;
                $this->attributes['quantity'] = 1;
                break;
                
            case 'housing_production':
                $this->attributes['description'] = 'Stampa 3D Housing';
                break;
                
            case 'housing_materials':
                $this->attributes['description'] = 'Materiali per Housing (filamenti/resine)';
                $this->attributes['is_from_inventory'] = true;
                break;
        }
    }
}