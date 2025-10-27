<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssemblyChecklistItem extends Model
{
    protected $fillable = [
        'template_id',
        'title',
        'description',
        'instructions',
        'type',
        'options',
        'is_required',
        'is_critical',
        'sort_order',
        'category',
        'validation_rules',
        'reference_image',
        'safety_notes',
        'estimated_minutes',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_critical' => 'boolean',
        'validation_rules' => 'array',
    ];

    /**
     * Get the template this item belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AssemblyChecklistTemplate::class, 'template_id');
    }

    /**
     * Get all responses for this item.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(AssemblyChecklistResponse::class, 'item_id');
    }

    /**
     * Get item type options.
     */
    public static function getTypeOptions(): array
    {
        return [
            'checkbox' => 'Checkbox (Yes/No)',
            'text' => 'Text Input',
            'number' => 'Number Input',
            'measurement' => 'Measurement with Tolerance',
            'photo' => 'Photo Upload',
            'file' => 'File Upload',
            'signature' => 'Digital Signature',
            'multiselect' => 'Multiple Choice',
        ];
    }

    /**
     * Get default categories.
     */
    public static function getCategoryOptions(): array
    {
        return [
            'Pre-assembly' => 'Pre-assembly Checks',
            'SMD Placement' => 'SMD Component Placement',
            'Through-Hole' => 'Through-Hole Components',
            'Soldering' => 'Soldering Operations',
            'Testing' => 'Testing & Verification',
            'Final Inspection' => 'Final Quality Inspection',
            'Packaging' => 'Packaging & Labeling',
        ];
    }

    /**
     * Validate response data against validation rules.
     */
    public function validateResponse($responseData): array
    {
        $errors = [];
        $rules = $this->validation_rules ?? [];

        switch ($this->type) {
            case 'number':
                if (isset($rules['min']) && $responseData < $rules['min']) {
                    $errors[] = "Value must be at least {$rules['min']}";
                }
                if (isset($rules['max']) && $responseData > $rules['max']) {
                    $errors[] = "Value must be at most {$rules['max']}";
                }
                break;

            case 'measurement':
                if (isset($rules['tolerance'])) {
                    $target = $rules['target'] ?? 0;
                    $tolerance = $rules['tolerance'];
                    $min = $target - $tolerance;
                    $max = $target + $tolerance;
                    
                    if ($responseData < $min || $responseData > $max) {
                        $errors[] = "Measurement outside tolerance: {$min} - {$max}";
                    }
                }
                break;

            case 'text':
                if (isset($rules['min_length']) && strlen($responseData) < $rules['min_length']) {
                    $errors[] = "Text must be at least {$rules['min_length']} characters";
                }
                if (isset($rules['pattern']) && !preg_match($rules['pattern'], $responseData)) {
                    $errors[] = "Text format is invalid";
                }
                break;
        }

        return $errors;
    }

    /**
     * Get formatted validation rules for display.
     */
    public function getFormattedValidationRules(): ?string
    {
        if (!$this->validation_rules) {
            return null;
        }

        $rules = $this->validation_rules;
        $formatted = [];

        switch ($this->type) {
            case 'number':
                if (isset($rules['min'])) $formatted[] = "Min: {$rules['min']}";
                if (isset($rules['max'])) $formatted[] = "Max: {$rules['max']}";
                break;

            case 'measurement':
                if (isset($rules['target'], $rules['tolerance'])) {
                    $unit = $rules['unit'] ?? '';
                    $formatted[] = "Target: {$rules['target']}{$unit} ±{$rules['tolerance']}{$unit}";
                }
                break;

            case 'text':
                if (isset($rules['min_length'])) $formatted[] = "Min length: {$rules['min_length']}";
                if (isset($rules['max_length'])) $formatted[] = "Max length: {$rules['max_length']}";
                break;
        }

        return implode(', ', $formatted);
    }

    /**
     * Check if item requires special handling.
     */
    public function requiresSpecialHandling(): bool
    {
        return $this->is_critical || 
               in_array($this->type, ['photo', 'file', 'signature', 'measurement']) ||
               !empty($this->safety_notes);
    }

    /**
     * Get icon for item type.
     */
    public function getTypeIcon(): string
    {
        return match($this->type) {
            'checkbox' => '☑️',
            'text' => '📝',
            'number' => '🔢',
            'measurement' => '📏',
            'photo' => '📷',
            'file' => '📎',
            'signature' => '✍️',
            'multiselect' => '📋',
            default => '▫️',
        };
    }

    /**
     * Get default options for multiselect type.
     */
    public function getSelectOptions(): array
    {
        if ($this->type !== 'multiselect' || !$this->options) {
            return [];
        }

        return $this->options['choices'] ?? [];
    }

    /**
     * Create default validation rules for type.
     */
    public static function getDefaultValidationRules(string $type): array
    {
        return match($type) {
            'number' => ['min' => 0, 'max' => 1000],
            'measurement' => ['target' => 0, 'tolerance' => 0.1, 'unit' => 'mm'],
            'text' => ['min_length' => 1, 'max_length' => 500],
            'multiselect' => ['min_selections' => 1, 'max_selections' => null],
            default => [],
        };
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope critical items.
     */
    public function scopeCritical($query)
    {
        return $query->where('is_critical', true);
    }

    /**
     * Scope required items.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }
}
