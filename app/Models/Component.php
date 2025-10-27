<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Component extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'sku',
        'manufacturer_part_number',
        'name',
        'description',
        'category_id',
        'manufacturer',
        'package',
        'specifications',
        'datasheet_url',
        'image_url',
        'unit_price',
        'currency',
        'invoice_reference',
        'invoice_pdf_path',
        'purchase_date',
        'supplier',
        'stock_quantity',
        'min_stock_level',
        'reorder_quantity',
        'storage_location',
        'status',
        'supplier_links',
        'aruco_code',
        'aruco_image_path',
        'aruco_generated_at',
        // Technical specifications
        'value',
        'tolerance',
        'voltage_rating',
        'current_rating',
        'power_rating',
        'package_type',
        'mounting_type',
        'case_style',
        'dielectric',
        'temperature_coefficient',
        'operating_temperature',
        'technical_attributes',
    ];

    protected $casts = [
        'specifications' => 'array',
        'supplier_links' => 'array',
        'technical_attributes' => 'array',
        'unit_price' => 'decimal:4',
        'purchase_date' => 'date',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'reorder_quantity' => 'integer',
        'aruco_generated_at' => 'datetime',
    ];

    /**
     * Get the category this component belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the lifecycle status for this component.
     */
    public function lifecycleStatus(): HasOne
    {
        return $this->hasOne(ComponentLifecycleStatus::class);
    }

    /**
     * Get alternative components for this component.
     */
    public function alternatives(): HasMany
    {
        return $this->hasMany(ComponentAlternative::class, 'original_component_id');
    }

    /**
     * Get components that this component is an alternative for.
     */
    public function alternativeFor(): HasMany
    {
        return $this->hasMany(ComponentAlternative::class, 'alternative_component_id');
    }

    /**
     * Get obsolescence alerts for this component.
     */
    public function obsolescenceAlerts(): HasMany
    {
        return $this->hasMany(ObsolescenceAlert::class);
    }

    /**
     * Get certifications for this component.
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(ComponentCertification::class);
    }

    /**
     * Get all inventory movements for this component.
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get all documents for this component.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get all ArUco markers for this component.
     */
    public function arucoMarkers(): MorphMany
    {
        return $this->morphMany(ArUcoMarker::class, 'trackable');
    }

    /**
     * Get all quotation items for this component.
     */
    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /**
     * Check if component is low on stock.
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }

    /**
     * Check if component needs reorder.
     */
    public function needsReorder(): bool
    {
        return $this->isLowStock() && $this->reorder_quantity > 0;
    }

    /**
     * Get the searchable array for Scout.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'manufacturer_part_number' => $this->manufacturer_part_number,
            'name' => $this->name,
            'description' => $this->description,
            'manufacturer' => $this->manufacturer,
            'package' => $this->package,
        ];
    }

    /**
     * Scope for active components.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for low stock components.
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_level');
    }

    /**
     * Get all project component allocations for this component.
     */
    public function projectAllocations()
    {
        return $this->hasMany(\App\Models\ProjectComponentAllocation::class);
    }

    /**
     * Get all invoice component mappings for this component.
     */
    public function invoiceComponentMappings()
    {
        return $this->hasMany(\App\Models\InvoiceComponentMapping::class);
    }
}