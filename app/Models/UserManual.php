<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserManual extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'version',
        'type',
        'format',
        'content',
        'sections',
        'file_path',
        'status',
        'generation_prompt',
        'generation_config',
        'error_message',
        'generated_by',
        'generated_at',
        'last_updated_at',
        'auto_update',
    ];

    protected $casts = [
        'sections' => 'array',
        'generation_config' => 'array',
        'generated_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'auto_update' => 'boolean',
    ];

    /**
     * Get the project this manual belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who generated this manual.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get manual type options.
     */
    public static function getTypeOptions(): array
    {
        return [
            'installation' => 'Installation Manual',
            'operation' => 'Operation Manual',
            'maintenance' => 'Maintenance Manual',
            'troubleshooting' => 'Troubleshooting Guide',
            'complete' => 'Complete Manual',
        ];
    }

    /**
     * Get format options.
     */
    public static function getFormatOptions(): array
    {
        return [
            'pdf' => 'PDF Document',
            'html' => 'HTML Page',
            'markdown' => 'Markdown File',
            'docx' => 'Word Document',
        ];
    }

    /**
     * Get status options.
     */
    public static function getStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'generating' => 'Generating...',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ];
    }

    /**
     * Check if manual is ready for download.
     */
    public function isDownloadable(): bool
    {
        return $this->status === 'completed' && 
               $this->file_path && 
               Storage::exists($this->file_path);
    }

    /**
     * Get the download URL for this manual.
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->isDownloadable()) {
            return null;
        }

        return route('user-manuals.download', $this->id);
    }

    /**
     * Get the file size in human readable format.
     */
    public function getFileSizeAttribute(): ?string
    {
        if (!$this->file_path || !Storage::exists($this->file_path)) {
            return null;
        }

        $bytes = Storage::size($this->file_path);
        
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' B';
    }

    /**
     * Get generation progress percentage.
     */
    public function getProgressPercentage(): int
    {
        return match($this->status) {
            'draft' => 0,
            'generating' => 50,
            'completed' => 100,
            'failed' => 0,
            default => 0,
        };
    }

    /**
     * Mark manual as generating.
     */
    public function markAsGenerating(): void
    {
        $this->update([
            'status' => 'generating',
            'error_message' => null,
        ]);
    }

    /**
     * Mark manual as completed.
     */
    public function markAsCompleted(string $filePath, ?string $content = null): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'content' => $content,
            'generated_at' => now(),
            'last_updated_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark manual as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Update content without changing status.
     */
    public function updateContent(string $content, ?array $sections = null): void
    {
        $updateData = [
            'content' => $content,
            'last_updated_at' => now(),
        ];

        if ($sections !== null) {
            $updateData['sections'] = $sections;
        }

        $this->update($updateData);
    }

    /**
     * Get default generation configuration for a manual type.
     */
    public static function getDefaultGenerationConfig(string $type): array
    {
        return match($type) {
            'installation' => [
                'include_safety_warnings' => true,
                'include_tools_required' => true,
                'include_step_by_step' => true,
                'include_diagrams' => true,
                'detail_level' => 'medium',
            ],
            'operation' => [
                'include_startup_procedure' => true,
                'include_normal_operation' => true,
                'include_shutdown_procedure' => true,
                'include_user_interface' => true,
                'detail_level' => 'high',
            ],
            'maintenance' => [
                'include_preventive_maintenance' => true,
                'include_cleaning_procedures' => true,
                'include_replacement_parts' => true,
                'include_calibration' => true,
                'detail_level' => 'high',
            ],
            'troubleshooting' => [
                'include_common_problems' => true,
                'include_error_codes' => true,
                'include_diagnostic_steps' => true,
                'include_contact_info' => true,
                'detail_level' => 'high',
            ],
            'complete' => [
                'include_all_sections' => true,
                'include_appendices' => true,
                'include_glossary' => true,
                'include_index' => true,
                'detail_level' => 'high',
            ],
            default => [
                'detail_level' => 'medium',
            ],
        };
    }
}
