<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ComplianceTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_compliance_document_id',
        'test_type',
        'test_standard',
        'description',
        'test_lab',
        'test_report_number',
        'test_results',
        'status',
        'test_date',
        'report_date',
        'test_report_path',
        'notes',
    ];

    protected $casts = [
        'test_results' => 'array',
        'test_date' => 'date',
        'report_date' => 'date',
    ];

    /**
     * Get the compliance document this test belongs to.
     */
    public function projectComplianceDocument(): BelongsTo
    {
        return $this->belongsTo(ProjectComplianceDocument::class);
    }

    /**
     * Scope for passed tests.
     */
    public function scopePassed(Builder $query): Builder
    {
        return $query->where('status', 'passed');
    }

    /**
     * Scope for failed tests.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for pending tests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope by test type.
     */
    public function scopeOfType(Builder $query, string $testType): Builder
    {
        return $query->where('test_type', $testType);
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'passed' => 'success',
            'failed' => 'danger',
            'pending' => 'warning',
            'not_applicable' => 'info',
            default => 'gray',
        };
    }

    /**
     * Get formatted test type.
     */
    public function getTestTypeNameAttribute(): string
    {
        return match($this->test_type) {
            'EMC' => 'Compatibilità Elettromagnetica',
            'Safety' => 'Sicurezza',
            'Environmental' => 'Test Ambientali',
            'RF' => 'Test Radio Frequenza',
            'Mechanical' => 'Test Meccanici',
            'Electrical' => 'Test Elettrici',
            default => ucfirst($this->test_type),
        };
    }

    /**
     * Get formatted status.
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            'passed' => 'Superato',
            'failed' => 'Fallito',
            'pending' => 'In Attesa',
            'not_applicable' => 'Non Applicabile',
            default => ucfirst($this->status),
        };
    }

    /**
     * Check if test report file exists.
     */
    public function reportExists(): bool
    {
        return $this->test_report_path && 
               \Illuminate\Support\Facades\Storage::disk('local')->exists($this->test_report_path);
    }

    /**
     * Get download URL for test report.
     */
    public function getReportDownloadUrlAttribute(): ?string
    {
        if (!$this->reportExists()) return null;
        
        return route('compliance.tests.download-report', $this->id);
    }

    /**
     * Check if test is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && 
               $this->test_date && 
               $this->test_date->isPast();
    }

    /**
     * Get days since test date.
     */
    public function getDaysSinceTestAttribute(): ?int
    {
        if (!$this->test_date) return null;
        
        return $this->test_date->diffInDays(now());
    }

    /**
     * Get summary of test results.
     */
    public function getResultsSummaryAttribute(): string
    {
        if (empty($this->test_results)) {
            return 'Nessun risultato disponibile';
        }
        
        $results = $this->test_results;
        $summary = [];
        
        foreach ($results as $key => $value) {
            if (is_array($value)) {
                $summary[] = $key . ': ' . json_encode($value);
            } else {
                $summary[] = $key . ': ' . $value;
            }
        }
        
        return implode(', ', array_slice($summary, 0, 3)) . 
               (count($summary) > 3 ? '...' : '');
    }

    /**
     * Check if test is critical for compliance.
     */
    public function isCritical(): bool
    {
        $criticalTests = ['EMC', 'Safety', 'RF'];
        return in_array($this->test_type, $criticalTests);
    }

    /**
     * Get test priority level.
     */
    public function getPriorityAttribute(): string
    {
        if ($this->isCritical()) {
            return 'high';
        }
        
        if (in_array($this->test_type, ['Environmental', 'Mechanical'])) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Get priority color.
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->getPriorityAttribute()) {
            'high' => 'danger',
            'medium' => 'warning',
            'low' => 'info',
            default => 'gray',
        };
    }

    /**
     * Get full test identifier.
     */
    public function getFullIdentifierAttribute(): string
    {
        $parts = [];
        
        if ($this->test_report_number) {
            $parts[] = $this->test_report_number;
        }
        
        $parts[] = $this->test_type;
        $parts[] = $this->projectComplianceDocument->project->code ?? 'Unknown';
        
        return implode(' / ', $parts);
    }

    /**
     * Generate test report number if not set.
     */
    public function generateTestReportNumber(): string
    {
        $prefix = strtoupper(substr($this->test_type, 0, 3));
        $year = now()->year;
        $sequence = static::where('test_type', $this->test_type)
                         ->whereYear('created_at', $year)
                         ->count() + 1;
        
        return sprintf('%s-%04d-%03d', $prefix, $year, $sequence);
    }

    /**
     * Auto-generate test report number before saving if not set.
     */
    protected static function booted()
    {
        static::creating(function ($test) {
            if (empty($test->test_report_number)) {
                $test->test_report_number = $test->generateTestReportNumber();
            }
        });
    }
}
