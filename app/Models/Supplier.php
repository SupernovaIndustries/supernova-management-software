<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'api_name',
        'website',
        'email',
        'phone',
        'address',
        'vat_number',
        'api_credentials',
        'api_settings',
        'api_enabled',
        'last_api_sync',
        'is_active',
    ];

    protected $casts = [
        'api_credentials' => 'array',
        'api_settings' => 'array',
        'api_enabled' => 'boolean',
        'is_active' => 'boolean',
        'last_api_sync' => 'datetime',
    ];

    /**
     * Get the supplier's display name with code.
     */
    public function getDisplayNameAttribute(): string
    {
        return "[{$this->code}] {$this->name}";
    }

    /**
     * Check if API integration is available.
     */
    public function hasApiIntegration(): bool
    {
        return $this->api_enabled && !empty($this->api_name) && !empty($this->api_credentials);
    }

    /**
     * Scope for active suppliers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for suppliers with API integration.
     */
    public function scopeWithApi($query)
    {
        return $query->where('api_enabled', true)->whereNotNull('api_name');
    }

    /**
     * Get risk assessments for this supplier.
     */
    public function riskAssessments(): HasMany
    {
        return $this->hasMany(SupplierRiskAssessment::class);
    }

    /**
     * Get the latest risk assessment for this supplier.
     */
    public function latestRiskAssessment(): HasOne
    {
        return $this->hasOne(SupplierRiskAssessment::class)->latestOfMany('assessment_date');
    }

    /**
     * Get CSV mappings for this supplier.
     */
    public function csvMappings(): HasMany
    {
        return $this->hasMany(SupplierCsvMapping::class);
    }

    /**
     * Get active CSV mappings for this supplier.
     */
    public function activeCsvMappings(): HasMany
    {
        return $this->hasMany(SupplierCsvMapping::class)->where('is_active', true);
    }
}