<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'brand',
        'model',
        'color',
        'diameter',
        'material_type',
        'weight_kg',
        'length_m',
        'unit_price',
        'currency',
        'stock_quantity',
        'min_stock_level',
        'unit_of_measure',
        'storage_location',
        'status',
        'supplier',
        'supplier_code',
        'purchase_date',
        'expiry_date',
        'temperature_storage_min',
        'temperature_storage_max',
        'notes',
        'specifications',
        'image_path',
        'datasheet_path',
    ];

    protected $casts = [
        'diameter' => 'decimal:2',
        'weight_kg' => 'decimal:3',
        'length_m' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'temperature_storage_min' => 'decimal:2',
        'temperature_storage_max' => 'decimal:2',
        'specifications' => 'array',
    ];

    /**
     * Get category labels in Italian.
     */
    public static function getCategoryLabels(): array
    {
        return [
            'filament' => 'Filamenti 3D',
            'resin' => 'Resine',
            'stationery' => 'Cancelleria',
            'consumable' => 'Materiali di Consumo',
            'chemical' => 'Prodotti Chimici',
            'packaging' => 'Materiali Imballaggio',
            'other' => 'Altri Materiali',
        ];
    }

    /**
     * Get status labels in Italian.
     */
    public static function getStatusLabels(): array
    {
        return [
            'active' => 'Attivo',
            'inactive' => 'Inattivo',
            'discontinued' => 'Dismesso',
        ];
    }

    /**
     * Get unit of measure options.
     */
    public static function getUnitOfMeasureOptions(): array
    {
        return [
            'pcs' => 'Pezzi',
            'kg' => 'Chilogrammi',
            'm' => 'Metri',
            'l' => 'Litri',
            'roll' => 'Rotoli',
            'bottle' => 'Bottiglie',
            'pack' => 'Confezioni',
        ];
    }

    /**
     * Check if material is low on stock.
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }

    /**
     * Check if material is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date < now();
    }

    /**
     * Scope for active materials.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for low stock materials.
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_level');
    }

    /**
     * Scope for expired materials.
     */
    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
