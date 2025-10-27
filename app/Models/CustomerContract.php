<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContract extends Model
{
    use HasFactory;

    protected $table = 'customer_contracts';

    protected $fillable = [
        'customer_id',
        'contract_number',
        'title',
        'type',
        'start_date',
        'end_date',
        'signed_at',
        'contract_value',
        'currency',
        'nextcloud_path',
        'pdf_generated_at',
        'status',
        'terms',
        'notes',
        'created_by',
        'ai_analysis_data',
        'ai_extracted_parties',
        'ai_risk_flags',
        'ai_key_dates',
        'ai_analyzed_at',
        'ai_review_data',
        'ai_review_score',
        'ai_review_issues_count',
        'ai_reviewed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_at' => 'date',
        'contract_value' => 'decimal:2',
        'pdf_generated_at' => 'datetime',
        'ai_analysis_data' => 'array',
        'ai_extracted_parties' => 'array',
        'ai_risk_flags' => 'array',
        'ai_key_dates' => 'array',
        'ai_analyzed_at' => 'datetime',
        'ai_review_data' => 'array',
        'ai_reviewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (!$contract->contract_number) {
                $contract->contract_number = static::generateContractNumber();
            }

            if (!isset($contract->created_by)) {
                $contract->created_by = auth()->id();
            }
        });
    }

    /**
     * Generate contract number in format CTR-YYYY-XXX
     */
    protected static function generateContractNumber(): string
    {
        $year = date('Y');
        $lastId = static::whereYear('created_at', $year)->max('id') ?? 0;
        $incrementalId = $lastId + 1;

        return 'CTR-' . $year . '-' . str_pad($incrementalId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get the customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if contract is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->start_date <= now()
            && ($this->end_date === null || $this->end_date >= now());
    }

    /**
     * Check if contract is expired
     */
    public function isExpired(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast();
    }

    /**
     * Get status color for badge
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'active' => 'success',
            'expired' => 'warning',
            'terminated' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get type color for badge
     */
    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'nda' => 'warning',
            'service_agreement' => 'success',
            'supply_contract' => 'info',
            'partnership' => 'primary',
            default => 'gray',
        };
    }

    /**
     * Scope for active contracts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Check if contract has been analyzed by AI
     */
    public function isAnalyzed(): bool
    {
        return $this->ai_analyzed_at !== null;
    }

    /**
     * Check if contract has been reviewed by AI
     */
    public function isReviewed(): bool
    {
        return $this->ai_reviewed_at !== null;
    }

    /**
     * Get review score color
     */
    public function getReviewScoreColorAttribute(): string
    {
        if ($this->ai_review_score === null) {
            return 'gray';
        }

        return match (true) {
            $this->ai_review_score >= 80 => 'success',
            $this->ai_review_score >= 60 => 'warning',
            default => 'danger',
        };
    }

    /**
     * Check if contract has high risk flags
     */
    public function hasHighRiskFlags(): bool
    {
        if (empty($this->ai_risk_flags)) {
            return false;
        }

        foreach ($this->ai_risk_flags as $risk) {
            if (isset($risk['gravita']) && $risk['gravita'] === 'alta') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get count of risk flags by severity
     */
    public function getRiskCountBySeverity(): array
    {
        $counts = ['alta' => 0, 'media' => 0, 'bassa' => 0];

        if (empty($this->ai_risk_flags)) {
            return $counts;
        }

        foreach ($this->ai_risk_flags as $risk) {
            $severity = $risk['gravita'] ?? 'bassa';
            if (isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }

        return $counts;
    }
}
