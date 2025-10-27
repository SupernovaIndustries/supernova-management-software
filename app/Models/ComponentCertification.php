<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ComponentCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'certification_type',
        'certificate_number',
        'issuing_authority',
        'issue_date',
        'expiry_date',
        'status',
        'scope',
        'test_standards',
        'certificate_file_path',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'test_standards' => 'array',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get certification status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'valid' => 'success',
            'pending' => 'warning',
            'expired' => 'danger',
            'revoked' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->expiry_date ? now()->diffInDays($this->expiry_date, false) : null;
    }

    /**
     * Check if certification is expiring soon
     */
    public function isExpiringSoon(int $days = 90): bool
    {
        return $this->expiry_date && 
               $this->expiry_date->diffInDays(now()) <= $days && 
               $this->status === 'valid';
    }

    /**
     * Check if certification is valid
     */
    public function isValid(): bool
    {
        return $this->status === 'valid' && 
               (!$this->expiry_date || $this->expiry_date->isFuture());
    }

    /**
     * Get common certification types for CE marking
     */
    public static function getCeRelevantTypes(): array
    {
        return [
            'CE' => 'CE Marking',
            'EMC' => 'Electromagnetic Compatibility',
            'LVD' => 'Low Voltage Directive',
            'RoHS' => 'Restriction of Hazardous Substances',
            'REACH' => 'Registration, Evaluation, Authorisation of Chemicals',
            'RED' => 'Radio Equipment Directive',
            'MD' => 'Machinery Directive',
            'PED' => 'Pressure Equipment Directive',
        ];
    }

    /**
     * Scope for valid certifications
     */
    public function scopeValid($query)
    {
        return $query->where('status', 'valid')
                     ->where(function ($q) {
                         $q->whereNull('expiry_date')
                           ->orWhere('expiry_date', '>', now());
                     });
    }

    /**
     * Scope for expiring certifications
     */
    public function scopeExpiringSoon($query, int $days = 90)
    {
        return $query->where('status', 'valid')
                     ->where('expiry_date', '<=', now()->addDays($days))
                     ->where('expiry_date', '>', now());
    }

    /**
     * Scope by certification type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('certification_type', $type);
    }

    /**
     * Scope for CE relevant certifications
     */
    public function scopeCeRelevant($query)
    {
        return $query->whereIn('certification_type', array_keys(self::getCeRelevantTypes()));
    }
}