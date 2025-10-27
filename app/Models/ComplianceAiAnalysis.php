<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ComplianceAiAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'analyzable_type',
        'analyzable_id',
        'input_data',
        'ai_recommendations',
        'detected_standards',
        'risk_assessment',
        'ai_reasoning',
        'confidence_score',
        'analyzed_by',
        'analyzed_at',
    ];

    protected $casts = [
        'input_data' => 'array',
        'ai_recommendations' => 'array',
        'detected_standards' => 'array',
        'risk_assessment' => 'array',
        'confidence_score' => 'float',
        'analyzed_at' => 'datetime',
    ];

    /**
     * Get the model that was analyzed.
     */
    public function analyzable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the analysis.
     */
    public function analyzedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyzed_by');
    }

    /**
     * Get confidence level as text.
     */
    public function getConfidenceLevelAttribute(): string
    {
        return match(true) {
            $this->confidence_score >= 90 => 'Molto Alta',
            $this->confidence_score >= 75 => 'Alta',
            $this->confidence_score >= 50 => 'Media',
            $this->confidence_score >= 25 => 'Bassa',
            default => 'Molto Bassa',
        };
    }

    /**
     * Get confidence color for badges.
     */
    public function getConfidenceColorAttribute(): string
    {
        return match(true) {
            $this->confidence_score >= 90 => 'success',
            $this->confidence_score >= 75 => 'info',
            $this->confidence_score >= 50 => 'warning',
            $this->confidence_score >= 25 => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get high priority recommendations.
     */
    public function getHighPriorityRecommendationsAttribute(): array
    {
        return collect($this->ai_recommendations ?? [])
            ->filter(fn($rec) => ($rec['priority'] ?? 'low') === 'high')
            ->values()
            ->toArray();
    }

    /**
     * Get mandatory standards.
     */
    public function getMandatoryStandardsAttribute(): array
    {
        return collect($this->detected_standards ?? [])
            ->filter(fn($std) => ($std['mandatory'] ?? false) === true)
            ->values()
            ->toArray();
    }

    /**
     * Get high risk items.
     */
    public function getHighRiskItemsAttribute(): array
    {
        if (!is_array($this->risk_assessment)) {
            return [];
        }
        
        return collect($this->risk_assessment)
            ->filter(fn($risk) => str_contains(strtolower($risk), 'high') || str_contains(strtolower($risk), 'alto'))
            ->values()
            ->toArray();
    }

    /**
     * Check if analysis needs action.
     */
    public function needsAction(): bool
    {
        return !empty($this->getHighPriorityRecommendationsAttribute()) ||
               !empty($this->getMandatoryStandardsAttribute()) ||
               !empty($this->getHighRiskItemsAttribute());
    }

    /**
     * Get action priority level.
     */
    public function getActionPriorityAttribute(): string
    {
        if (!empty($this->getMandatoryStandardsAttribute()) || !empty($this->getHighRiskItemsAttribute())) {
            return 'urgent';
        }
        
        if (!empty($this->getHighPriorityRecommendationsAttribute())) {
            return 'high';
        }
        
        if (!empty($this->ai_recommendations)) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Get action priority color.
     */
    public function getActionPriorityColorAttribute(): string
    {
        return match($this->getActionPriorityAttribute()) {
            'urgent' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'success',
            default => 'gray',
        };
    }

    /**
     * Get recommended standards codes.
     */
    public function getRecommendedStandardCodesAttribute(): array
    {
        return collect($this->detected_standards ?? [])
            ->pluck('code')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get summary of analysis results.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'total_recommendations' => count($this->ai_recommendations ?? []),
            'high_priority_count' => count($this->getHighPriorityRecommendationsAttribute()),
            'mandatory_standards_count' => count($this->getMandatoryStandardsAttribute()),
            'total_standards' => count($this->detected_standards ?? []),
            'risk_level' => !empty($this->getHighRiskItemsAttribute()) ? 'high' : 'low',
            'confidence_level' => $this->getConfidenceLevelAttribute(),
            'needs_action' => $this->needsAction(),
        ];
    }

    /**
     * Generate action items from analysis.
     */
    public function generateActionItems(): array
    {
        $actions = [];
        
        // Add mandatory standards as actions
        foreach ($this->getMandatoryStandardsAttribute() as $standard) {
            $actions[] = [
                'type' => 'mandatory_compliance',
                'priority' => 'urgent',
                'title' => "Conformità obbligatoria: {$standard['code']}",
                'description' => $standard['reasoning'] ?? 'Standard obbligatorio rilevato',
                'estimated_cost' => $standard['estimated_cost'] ?? null,
                'timeframe' => $standard['timeframe'] ?? null,
            ];
        }
        
        // Add high priority recommendations
        foreach ($this->getHighPriorityRecommendationsAttribute() as $rec) {
            $actions[] = [
                'type' => 'recommendation',
                'priority' => $rec['priority'] ?? 'high',
                'title' => $rec['description'] ?? 'Raccomandazione AI',
                'description' => $rec['reason'] ?? '',
                'estimated_cost' => null,
                'timeframe' => null,
            ];
        }
        
        return $actions;
    }

    /**
     * Check if analysis is recent (within last 30 days).
     */
    public function isRecent(): bool
    {
        return $this->analyzed_at && $this->analyzed_at->greaterThan(now()->subDays(30));
    }

    /**
     * Check if analysis is outdated (older than 90 days).
     */
    public function isOutdated(): bool
    {
        return $this->analyzed_at && $this->analyzed_at->lessThan(now()->subDays(90));
    }
}
