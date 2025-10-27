<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ComplianceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'compliance_standard_id',
        'name',
        'type',
        'description',
        'required_fields',
        'template_content',
        'ai_prompts',
        'output_format',
        'requires_ai_assistance',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'required_fields' => 'array',
        'ai_prompts' => 'array',
        'requires_ai_assistance' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the compliance standard this template belongs to.
     */
    public function complianceStandard(): BelongsTo
    {
        return $this->belongsTo(ComplianceStandard::class);
    }

    /**
     * Get all project compliance documents using this template.
     */
    public function projectComplianceDocuments(): HasMany
    {
        return $this->hasMany(ProjectComplianceDocument::class);
    }

    /**
     * Scope to get only active templates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only default templates.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to filter by template type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by output format.
     */
    public function scopeByFormat(Builder $query, string $format): Builder
    {
        return $query->where('output_format', $format);
    }

    /**
     * Get required fields as formatted list.
     */
    public function getRequiredFieldsListAttribute(): string
    {
        if (empty($this->required_fields)) {
            return 'Nessun campo specifico richiesto';
        }
        
        return implode(', ', $this->required_fields);
    }

    /**
     * Get template content preview.
     */
    public function getContentPreviewAttribute(): string
    {
        if (empty($this->template_content)) {
            return 'Nessun contenuto';
        }
        
        return \Illuminate\Support\Str::limit(strip_tags($this->template_content), 100);
    }

    /**
     * Check if template can be used with AI assistance.
     */
    public function canUseAi(): bool
    {
        return $this->requires_ai_assistance && !empty($this->ai_prompts);
    }

    /**
     * Get AI prompt for specific context.
     */
    public function getAiPrompt(string $context = 'default'): ?string
    {
        if (empty($this->ai_prompts)) {
            return null;
        }
        
        return $this->ai_prompts[$context] ?? $this->ai_prompts['default'] ?? null;
    }

    /**
     * Get display badge color based on type.
     */
    public function getBadgeColorAttribute(): string
    {
        return match($this->type) {
            'declaration' => 'success',
            'certificate' => 'warning',
            'technical_file' => 'info',
            default => 'gray',
        };
    }

    /**
     * Get formatted type name.
     */
    public function getTypeNameAttribute(): string
    {
        return match($this->type) {
            'declaration' => 'Dichiarazione di Conformità',
            'certificate' => 'Certificato',
            'technical_file' => 'File Tecnico',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get full name with standard code.
     */
    public function getFullNameAttribute(): string
    {
        $standardCode = $this->complianceStandard?->code ?? 'Unknown';
        return "[{$standardCode}] {$this->name}";
    }

    /**
     * Check if this is the default template for its type and standard.
     */
    public function isDefaultForType(): bool
    {
        return $this->is_default && $this->is_active;
    }

    /**
     * Get file extension based on output format.
     */
    public function getFileExtensionAttribute(): string
    {
        return match($this->output_format) {
            'pdf' => 'pdf',
            'docx' => 'docx',
            'html' => 'html',
            'markdown' => 'md',
            default => 'txt',
        };
    }

    /**
     * Get MIME type based on output format.
     */
    public function getMimeTypeAttribute(): string
    {
        return match($this->output_format) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'html' => 'text/html',
            'markdown' => 'text/markdown',
            default => 'text/plain',
        };
    }

    /**
     * Validate required fields against provided data.
     */
    public function validateRequiredFields(array $data): array
    {
        $missing = [];
        
        foreach ($this->required_fields ?? [] as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }

    /**
     * Process template content with provided data.
     */
    public function processContent(array $data): string
    {
        $content = $this->template_content;
        
        // Replace placeholders with actual data
        foreach ($data as $key => $value) {
            $placeholder = "{{" . $key . "}}";
            $content = str_replace($placeholder, $value, $content);
        }
        
        // Handle nested data
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $placeholder = "{{" . $key . "." . $subKey . "}}";
                    $content = str_replace($placeholder, $subValue, $content);
                }
            }
        }
        
        return $content;
    }
}
