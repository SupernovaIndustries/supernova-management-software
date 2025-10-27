<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BoardAssemblyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'assembly_date',
        'boards_count',
        'batch_number',
        'notes',
        'status',
        'is_prototype',
        // DDT fields
        'ddt_number',
        'ddt_date',
        'ddt_transport_type',
        'ddt_delivery_address',
        'ddt_reason',
        'ddt_payment_condition',
        'ddt_packages_count',
        'ddt_weight_kg',
        'ddt_appearance',
        'ddt_goods_description',
        'ddt_pdf_path',
        'ddt_signed_pdf_path',
        'ddt_conductor_signature',
        'ddt_recipient_signature',
        'ddt_generated_at',
        'ddt_signed_at',
    ];

    protected $casts = [
        'assembly_date' => 'date',
        'boards_count' => 'integer',
        'is_prototype' => 'boolean',
        // DDT casts
        'ddt_date' => 'date',
        'ddt_delivery_address' => 'json',
        'ddt_packages_count' => 'integer',
        'ddt_weight_kg' => 'decimal:2',
        'ddt_generated_at' => 'datetime',
        'ddt_signed_at' => 'datetime',
    ];

    /**
     * Get the project this assembly log belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who performed the assembly.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all QC documents for this assembly log.
     */
    public function qcDocuments(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get all QR codes for this assembly log.
     */
    public function qrCodes(): HasMany
    {
        return $this->hasMany(BoardQrCode::class, 'board_assembly_log_id');
    }

    /**
     * Get assembly checklist for this log.
     */
    public function assemblyChecklist(): HasMany
    {
        return $this->hasMany(AssemblyChecklist::class, 'board_assembly_log_id');
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'assembled' => 'info',
            'tested' => 'success',
            'failed' => 'danger',
            'rework' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Scope to filter by project.
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('assembly_date', [$startDate, $endDate]);
    }

    /**
     * Generate automatic batch number.
     * Format: [number]-[PROJECT-ABBREV]-[TEST/PROD]
     */
    public static function generateBatchNumber(Project $project, bool $isPrototype): string
    {
        // Get last batch number for this project
        $lastLog = static::where('project_id', $project->id)
            ->where('is_prototype', $isPrototype)
            ->latest('id')
            ->first();

        $nextNumber = $lastLog ? (intval(explode('-', $lastLog->batch_number)[0] ?? 0) + 1) : 1;

        // Abbreviate project code (max 10 chars)
        $projectAbbrev = strtoupper(substr($project->code, 0, 10));

        // Type
        $type = $isPrototype ? 'TEST' : 'PROD';

        return sprintf('%03d-%s-%s', $nextNumber, $projectAbbrev, $type);
    }

    /**
     * Check if DDT has been generated.
     */
    public function hasDdt(): bool
    {
        return !empty($this->ddt_number);
    }

    /**
     * Check if DDT has been signed.
     */
    public function isDdtSigned(): bool
    {
        return !empty($this->ddt_conductor_signature) || !empty($this->ddt_recipient_signature);
    }

    /**
     * Get DDT status badge.
     */
    public function getDdtStatusAttribute(): string
    {
        if (!$this->hasDdt()) {
            return 'not_generated';
        }

        if ($this->isDdtSigned()) {
            return 'signed';
        }

        return 'generated';
    }

    /**
     * Get DDT status color.
     */
    public function getDdtStatusColorAttribute(): string
    {
        return match($this->ddt_status) {
            'not_generated' => 'gray',
            'generated' => 'warning',
            'signed' => 'success',
            default => 'gray',
        };
    }

    /**
     * Get DDT status label.
     */
    public function getDdtStatusLabelAttribute(): string
    {
        return match($this->ddt_status) {
            'not_generated' => 'Non Generato',
            'generated' => 'Generato',
            'signed' => 'Firmato',
            default => 'N/A',
        };
    }
}
