<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRiskAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'risk_level',
        'risk_factors',
        'financial_score',
        'delivery_score',
        'quality_score',
        'geographic_diversification',
        'assessment_notes',
        'assessment_date',
        'next_review_date',
        'assessed_by',
    ];

    protected $casts = [
        'risk_factors' => 'array',
        'financial_score' => 'decimal:2',
        'delivery_score' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'geographic_diversification' => 'decimal:2',
        'assessment_date' => 'date',
        'next_review_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    /**
     * Get overall risk score (0-1)
     */
    public function getOverallRiskScoreAttribute(): float
    {
        $scores = [
            $this->financial_score ?? 0.5,
            $this->delivery_score ?? 0.5,
            $this->quality_score ?? 0.5,
            $this->geographic_diversification ?? 0.5,
        ];

        return array_sum($scores) / count($scores);
    }

    /**
     * Get risk level color
     */
    public function getRiskLevelColorAttribute(): string
    {
        return match($this->risk_level) {
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get risk level based on overall score
     */
    public function getCalculatedRiskLevelAttribute(): string
    {
        $score = $this->overall_risk_score;
        
        if ($score >= 0.8) return 'low';
        if ($score >= 0.6) return 'medium';
        if ($score >= 0.4) return 'high';
        return 'critical';
    }

    /**
     * Check if assessment is overdue
     */
    public function isOverdue(): bool
    {
        return $this->next_review_date && $this->next_review_date->isPast();
    }

    /**
     * Get days until next review
     */
    public function getDaysUntilReviewAttribute(): ?int
    {
        return $this->next_review_date ? now()->diffInDays($this->next_review_date, false) : null;
    }

    /**
     * Get common risk factors
     */
    public static function getCommonRiskFactors(): array
    {
        return [
            'geographic_concentration' => 'Geographic Concentration',
            'single_source' => 'Single Source Dependency',
            'financial_instability' => 'Financial Instability',
            'quality_issues' => 'Quality Issues',
            'delivery_delays' => 'Delivery Delays',
            'capacity_constraints' => 'Capacity Constraints',
            'regulatory_changes' => 'Regulatory Changes',
            'political_instability' => 'Political Instability',
            'natural_disasters' => 'Natural Disaster Risk',
            'cyber_security' => 'Cyber Security Risk',
        ];
    }

    /**
     * Scope for high risk suppliers
     */
    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    /**
     * Scope for overdue assessments
     */
    public function scopeOverdue($query)
    {
        return $query->where('next_review_date', '<', now());
    }

    /**
     * Scope for recent assessments
     */
    public function scopeRecent($query, int $days = 90)
    {
        return $query->where('assessment_date', '>=', now()->subDays($days));
    }
}