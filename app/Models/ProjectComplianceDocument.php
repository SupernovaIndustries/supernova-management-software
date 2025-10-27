<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ProjectComplianceDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'compliance_standard_id',
        'compliance_template_id',
        'document_type',
        'title',
        'document_number',
        'description',
        'compliance_data',
        'file_path',
        'file_format',
        'file_size',
        'status',
        'issue_date',
        'expiry_date',
        'notes',
        'issued_by',
        'approved_by',
        'issued_at',
        'approved_at',
    ];

    protected $casts = [
        'compliance_data' => 'array',
        'file_size' => 'integer',
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'issued_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the project this document belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the compliance standard.
     */
    public function complianceStandard(): BelongsTo
    {
        return $this->belongsTo(ComplianceStandard::class);
    }

    /**
     * Get the template used.
     */
    public function complianceTemplate(): BelongsTo
    {
        return $this->belongsTo(ComplianceTemplate::class);
    }

    /**
     * Get the user who issued the document.
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the user who approved the document.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get all compliance tests for this document.
     */
    public function complianceTests(): HasMany
    {
        return $this->hasMany(ComplianceTest::class);
    }

    /**
     * Get all renewal records for this document.
     */
    public function complianceRenewals(): HasMany
    {
        return $this->hasMany(ComplianceRenewal::class);
    }

    /**
     * Scope for active documents.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'pending']);
    }

    /**
     * Scope for expired documents.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiry_date', '<', now()->toDate())
                    ->where('status', '!=', 'expired');
    }

    /**
     * Scope for expiring soon documents.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereBetween('expiry_date', [
            now()->toDate(),
            now()->addDays($days)->toDate()
        ])->where('status', 'approved');
    }

    /**
     * Scope by document type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'approved' => 'success',
            'pending' => 'warning',
            'draft' => 'info',
            'expired' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get formatted document type.
     */
    public function getDocumentTypeNameAttribute(): string
    {
        return match($this->document_type) {
            'declaration' => 'Dichiarazione di Conformità',
            'certificate' => 'Certificato',
            'technical_file' => 'File Tecnico',
            default => ucfirst($this->document_type),
        };
    }

    /**
     * Check if document is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if document is expiring soon.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date && 
               $this->expiry_date->isFuture() && 
               $this->expiry_date->lte(now()->addDays($days));
    }

    /**
     * Check if document needs renewal.
     */
    public function needsRenewal(): bool
    {
        return $this->isExpired() || $this->isExpiringSoon();
    }

    /**
     * Get days until expiry.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) return null;
        
        return now()->diffInDays($this->expiry_date, false);
    }

    /**
     * Get human readable file size.
     */
    public function getHumanFileSizeAttribute(): string
    {
        if (!$this->file_size) return 'N/A';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file exists.
     */
    public function fileExists(): bool
    {
        return $this->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->file_path);
    }

    /**
     * Get download URL.
     */
    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->fileExists()) return null;
        
        return route('compliance.documents.download', $this->id);
    }

    /**
     * Get full document identifier.
     */
    public function getFullIdentifierAttribute(): string
    {
        $parts = [];
        
        if ($this->document_number) {
            $parts[] = $this->document_number;
        }
        
        if ($this->complianceStandard) {
            $parts[] = $this->complianceStandard->code;
        }
        
        $parts[] = $this->project->code ?? 'Unknown';
        
        return implode(' / ', $parts);
    }

    /**
     * Get compliance progress percentage.
     */
    public function getComplianceProgressAttribute(): float
    {
        $tests = $this->complianceTests;
        
        if ($tests->isEmpty()) {
            return $this->status === 'approved' ? 100.0 : 0.0;
        }
        
        $passedTests = $tests->where('status', 'passed')->count();
        $totalTests = $tests->count();
        
        return round(($passedTests / $totalTests) * 100, 1);
    }

    /**
     * Get next renewal date.
     */
    public function getNextRenewalDateAttribute(): ?\Carbon\Carbon
    {
        return $this->complianceRenewals()
                   ->where('status', 'pending')
                   ->orderBy('renewal_due_date')
                   ->first()
                   ?->renewal_due_date;
    }

    /**
     * Get required tests that are missing.
     */
    public function getMissingTestsAttribute(): array
    {
        $requiredTests = $this->complianceStandard?->required_tests ?? [];
        $existingTests = $this->complianceTests->pluck('test_type')->toArray();
        
        return array_diff($requiredTests, $existingTests);
    }

    /**
     * Check if all required tests are passed.
     */
    public function allTestsPassed(): bool
    {
        $requiredTests = $this->complianceStandard?->required_tests ?? [];
        
        if (empty($requiredTests)) {
            return true;
        }
        
        foreach ($requiredTests as $testType) {
            $test = $this->complianceTests()->where('test_type', $testType)->first();
            if (!$test || $test->status !== 'passed') {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Generate document number if not set.
     */
    public function generateDocumentNumber(): string
    {
        $prefix = $this->complianceStandard?->code ?? 'DOC';
        $year = now()->year;
        $sequence = static::where('compliance_standard_id', $this->compliance_standard_id)
                         ->whereYear('created_at', $year)
                         ->count() + 1;
        
        return sprintf('%s-%04d-%03d', $prefix, $year, $sequence);
    }

    /**
     * Auto-generate document number before saving if not set.
     */
    protected static function booted()
    {
        static::creating(function ($document) {
            if (empty($document->document_number)) {
                $document->document_number = $document->generateDocumentNumber();
            }
        });
    }
}
