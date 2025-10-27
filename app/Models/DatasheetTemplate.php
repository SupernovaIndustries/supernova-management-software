<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatasheetTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'sections',
        'styles',
        'logo_path',
        'include_company_info',
        'include_toc',
        'output_format',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'sections' => 'array',
        'styles' => 'array',
        'include_company_info' => 'boolean',
        'include_toc' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get generated datasheets using this template.
     */
    public function generatedDatasheets(): HasMany
    {
        return $this->hasMany(GeneratedDatasheet::class);
    }

    /**
     * Get available template types.
     */
    public static function getTypes(): array
    {
        return [
            'project' => 'Progetto',
            'component' => 'Componente',
            'system' => 'Sistema',
        ];
    }

    /**
     * Get available output formats.
     */
    public static function getOutputFormats(): array
    {
        return [
            'pdf' => 'PDF',
            'html' => 'HTML',
            'markdown' => 'Markdown',
        ];
    }

    /**
     * Get default sections for template type.
     */
    public static function getDefaultSections(string $type): array
    {
        return match ($type) {
            'project' => [
                ['name' => 'overview', 'title' => 'Panoramica Progetto', 'enabled' => true],
                ['name' => 'specifications', 'title' => 'Specifiche Tecniche', 'enabled' => true],
                ['name' => 'features', 'title' => 'Caratteristiche', 'enabled' => true],
                ['name' => 'bom', 'title' => 'Bill of Materials', 'enabled' => true],
                ['name' => 'schematic', 'title' => 'Schema Elettrico', 'enabled' => false],
                ['name' => 'pcb_layout', 'title' => 'Layout PCB', 'enabled' => false],
                ['name' => 'mechanical', 'title' => 'Disegno Meccanico', 'enabled' => false],
                ['name' => 'performance', 'title' => 'Performance e Test', 'enabled' => false],
                ['name' => 'compliance', 'title' => 'Conformità e Certificazioni', 'enabled' => false],
            ],
            'component' => [
                ['name' => 'overview', 'title' => 'Panoramica Componente', 'enabled' => true],
                ['name' => 'electrical_specs', 'title' => 'Specifiche Elettriche', 'enabled' => true],
                ['name' => 'mechanical_specs', 'title' => 'Specifiche Meccaniche', 'enabled' => true],
                ['name' => 'environmental_specs', 'title' => 'Specifiche Ambientali', 'enabled' => true],
                ['name' => 'pin_configuration', 'title' => 'Configurazione Pin', 'enabled' => false],
                ['name' => 'application_notes', 'title' => 'Note Applicative', 'enabled' => false],
                ['name' => 'pcb_layout_recommendations', 'title' => 'Raccomandazioni Layout PCB', 'enabled' => false],
            ],
            'system' => [
                ['name' => 'overview', 'title' => 'Panoramica Sistema', 'enabled' => true],
                ['name' => 'architecture', 'title' => 'Architettura', 'enabled' => true],
                ['name' => 'interfaces', 'title' => 'Interfacce', 'enabled' => true],
                ['name' => 'performance', 'title' => 'Performance', 'enabled' => true],
                ['name' => 'integration', 'title' => 'Integrazione', 'enabled' => false],
            ],
            default => [],
        };
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
