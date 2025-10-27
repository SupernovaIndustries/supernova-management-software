<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'brand',
        'model',
        'serial_number',
        'purchase_price',
        'currency',
        'purchase_date',
        'supplier',
        'invoice_reference',
        'status',
        'location',
        'responsible_user',
        'warranty_expiry',
        'last_maintenance',
        'next_maintenance',
        'maintenance_interval_months',
        'specifications',
        'technical_specs',
        'notes',
        'image_path',
        'manual_path',
        'qr_code',
        'calibration_required',
        'last_calibration',
        'next_calibration',
        'calibration_interval_months',
        'depreciation_rate',
        'current_value',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'current_value' => 'decimal:2',
        'depreciation_rate' => 'decimal:2',
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'last_maintenance' => 'date',
        'next_maintenance' => 'date',
        'last_calibration' => 'date',
        'next_calibration' => 'date',
        'calibration_required' => 'boolean',
        'maintenance_interval_months' => 'integer',
        'calibration_interval_months' => 'integer',
        'technical_specs' => 'array',
    ];

    /**
     * Get category labels in Italian.
     */
    public static function getCategoryLabels(): array
    {
        return [
            'computer' => 'Computer e Laptop',
            'soldering' => 'Saldatori e Stazioni',
            'reflow' => 'Forni Reflow',
            'cnc' => 'Macchine CNC',
            '3d_printer' => 'Stampanti 3D',
            'laser' => 'Laser Cutter/Engraver',
            'measurement' => 'Strumenti di Misura',
            'power_supply' => 'Alimentatori',
            'oscilloscope' => 'Oscilloscopi',
            'multimeter' => 'Multimetri',
            'generator' => 'Generatori di Segnale',
            'microscope' => 'Microscopi',
            'camera' => 'Fotocamere e Videocamere',
            'tool' => 'Utensili Vari',
            'furniture' => 'Mobili e Scaffalature',
            'other' => 'Altre Attrezzature',
        ];
    }

    /**
     * Get status labels in Italian.
     */
    public static function getStatusLabels(): array
    {
        return [
            'active' => 'In Uso',
            'maintenance' => 'In Manutenzione',
            'broken' => 'Guasto',
            'retired' => 'Dismesso',
            'sold' => 'Venduto',
        ];
    }

    /**
     * Check if equipment needs maintenance.
     */
    public function needsMaintenance(): bool
    {
        return $this->next_maintenance && $this->next_maintenance <= now();
    }

    /**
     * Scope to filter equipment that needs maintenance.
     */
    public function scopeNeedsMaintenance($query)
    {
        return $query->whereNotNull('next_maintenance')
            ->where('next_maintenance', '<=', now());
    }

    /**
     * Check if equipment needs calibration.
     */
    public function needsCalibration(): bool
    {
        return $this->calibration_required &&
               $this->next_calibration &&
               $this->next_calibration <= now();
    }

    /**
     * Check if warranty is expired.
     */
    public function isWarrantyExpired(): bool
    {
        return $this->warranty_expiry && $this->warranty_expiry < now();
    }

    /**
     * Calculate current value based on depreciation.
     */
    public function calculateCurrentValue(): float
    {
        if (!$this->purchase_price || !$this->purchase_date || !$this->depreciation_rate) {
            return $this->current_value ?? $this->purchase_price ?? 0;
        }

        $yearsElapsed = $this->purchase_date->diffInYears(now());
        $depreciationAmount = $this->purchase_price * ($this->depreciation_rate / 100) * $yearsElapsed;
        
        return max(0, $this->purchase_price - $depreciationAmount);
    }

    /**
     * Scope for active equipment.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for equipment needing maintenance.
     */
    public function scopeNeedsMaintenance($query)
    {
        return $query->where('next_maintenance', '<=', now());
    }

    /**
     * Scope for equipment needing calibration.
     */
    public function scopeNeedsCalibration($query)
    {
        return $query->where('calibration_required', true)
                    ->where('next_calibration', '<=', now());
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by location.
     */
    public function scopeByLocation($query, $location)
    {
        return $query->where('location', $location);
    }
}
