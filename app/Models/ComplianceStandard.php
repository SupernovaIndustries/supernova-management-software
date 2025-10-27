<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ComplianceStandard extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'issuing_authority',
        'geographic_scope',
        'applicable_categories',
        'required_tests',
        'required_documentation',
        'validity_period',
        'renewal_requirements',
        'requires_testing',
        'requires_declaration',
        'template_path',
        'is_active',
    ];

    protected $casts = [
        'applicable_categories' => 'array',
        'required_tests' => 'array',
        'required_documentation' => 'array',
        'requires_testing' => 'boolean',
        'requires_declaration' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all compliance templates for this standard.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(ComplianceTemplate::class);
    }

    /**
     * Get all project compliance documents using this standard.
     */
    public function projectComplianceDocuments(): HasMany
    {
        return $this->hasMany(ProjectComplianceDocument::class);
    }

    /**
     * Scope to get only active standards.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by geographic scope.
     */
    public function scopeForRegion(Builder $query, string $region): Builder
    {
        return $query->where('geographic_scope', $region)
                    ->orWhere('geographic_scope', 'Global');
    }

    /**
     * Scope to filter by applicable categories.
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->whereJsonContains('applicable_categories', $category);
    }

    /**
     * Check if this standard applies to a given category.
     */
    public function appliesTo(string $category): bool
    {
        $categories = $this->applicable_categories ?? [];
        return in_array($category, $categories) || in_array('all', $categories);
    }

    /**
     * Check if this standard is valid for a given region.
     */
    public function isValidFor(string $region): bool
    {
        return $this->geographic_scope === 'Global' || 
               $this->geographic_scope === $region;
    }

    /**
     * Get default template for this standard.
     */
    public function getDefaultTemplate(): ?ComplianceTemplate
    {
        return $this->templates()
                   ->where('is_default', true)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Get templates by type.
     */
    public function getTemplatesByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->templates()
                   ->where('type', $type)
                   ->where('is_active', true)
                   ->orderBy('is_default', 'desc')
                   ->get();
    }

    /**
     * Get required tests as formatted list.
     */
    public function getRequiredTestsListAttribute(): string
    {
        if (empty($this->required_tests)) {
            return 'Nessun test specifico richiesto';
        }
        
        return implode(', ', $this->required_tests);
    }

    /**
     * Get required documentation as formatted list.
     */
    public function getRequiredDocumentationListAttribute(): string
    {
        if (empty($this->required_documentation)) {
            return 'Nessuna documentazione specifica richiesta';
        }
        
        return implode(', ', $this->required_documentation);
    }

    /**
     * Get applicable categories as formatted list.
     */
    public function getApplicableCategoriesListAttribute(): string
    {
        if (empty($this->applicable_categories)) {
            return 'Tutte le categorie';
        }
        
        return implode(', ', $this->applicable_categories);
    }

    /**
     * Check if standard requires renewal.
     */
    public function requiresRenewal(): bool
    {
        return !empty($this->validity_period) && 
               $this->validity_period !== 'Permanent';
    }

    /**
     * Get display badge color based on geographic scope.
     */
    public function getBadgeColorAttribute(): string
    {
        return match($this->geographic_scope) {
            'EU' => 'info',
            'USA' => 'warning',
            'Global' => 'success',
            default => 'gray',
        };
    }

    /**
     * Get full display name with code.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }
}
