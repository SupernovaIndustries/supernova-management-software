<?php

namespace App\Services;

use App\Models\DatasheetTemplate;
use App\Models\GeneratedDatasheet;
use App\Models\Project;
use App\Models\Component;
use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Dompdf\Options;

class DatasheetGeneratorService
{
    /**
     * Generate datasheet for a model (Project, Component, etc.)
     */
    public function generate(Model $model, DatasheetTemplate $template, array $customData = []): GeneratedDatasheet
    {
        // Collect all data needed for generation
        $data = $this->collectData($model, $template, $customData);
        
        // Generate the content based on template type
        $content = $this->generateContent($data, $template);
        
        // Save to file based on output format
        $filePath = $this->saveToFile($content, $template, $data);
        
        // Create database record
        return $this->createGeneratedRecord($model, $template, $data, $filePath);
    }

    /**
     * Collect all data needed for datasheet generation.
     */
    private function collectData(Model $model, DatasheetTemplate $template, array $customData): array
    {
        $data = [
            'template' => $template,
            'model' => $model,
            'model_type' => class_basename($model),
            'company' => CompanyProfile::current(),
            'generated_at' => now(),
            'custom_data' => $customData,
        ];

        // Collect model-specific data
        if ($model instanceof Project) {
            $data = array_merge($data, $this->collectProjectData($model));
        } elseif ($model instanceof Component) {
            $data = array_merge($data, $this->collectComponentData($model));
        }

        return $data;
    }

    /**
     * Collect project-specific data.
     */
    private function collectProjectData(Project $model): array
    {
        return [
            'title' => $model->name,
            'description' => $model->description,
            'project_data' => $model->projectDatasheetData,
            'bom_items' => $model->bomComponents ?? collect(),
            'system_instances' => $model->systemInstances ?? collect(),
            'milestones' => $model->milestones ?? collect(),
            'quotations' => $model->quotations ?? collect(),
            'documents' => $model->projectDocuments ?? collect(),
            'pcb_files' => $model->pcbFiles ?? collect(),
            'specifications' => $this->extractProjectSpecifications($model),
            'features' => $this->extractProjectFeatures($model),
            'performance_data' => $this->extractProjectPerformance($model),
        ];
    }

    /**
     * Collect component-specific data.
     */
    private function collectComponentData(Component $model): array
    {
        return [
            'title' => $model->name,
            'description' => $model->description,
            'component_data' => $model->componentDatasheetData,
            'specifications' => $model->specifications ?? [],
            'category' => $model->category,
            'supplier' => $model->supplier,
            'datasheet_url' => $model->datasheet_url,
            'electrical_specs' => $this->extractElectricalSpecs($model),
            'mechanical_specs' => $this->extractMechanicalSpecs($model),
            'environmental_specs' => $this->extractEnvironmentalSpecs($model),
        ];
    }

    /**
     * Generate content based on template.
     */
    private function generateContent(array $data, DatasheetTemplate $template): string
    {
        // Get enabled sections from template
        $sections = collect($template->sections)->where('enabled', true);
        
        $sectionContents = [];
        
        foreach ($sections as $section) {
            $sectionContent = $this->generateSection($section, $data, $template);
            if (!empty($sectionContent)) {
                $sectionContents[] = $sectionContent;
            }
        }

        // Use main template to combine all sections
        return View::make('datasheets.main', [
            'data' => $data,
            'template' => $template,
            'sections' => $sectionContents,
            'include_toc' => $template->include_toc,
            'include_company_info' => $template->include_company_info,
        ])->render();
    }

    /**
     * Generate individual section content.
     */
    private function generateSection(array $section, array $data, DatasheetTemplate $template): string
    {
        $sectionName = $section['name'];
        $viewName = "datasheets.sections.{$template->type}.{$sectionName}";

        // Check if specific template exists, fallback to generic
        if (!View::exists($viewName)) {
            $viewName = "datasheets.sections.generic.{$sectionName}";
        }

        if (!View::exists($viewName)) {
            // Return basic HTML if no template found
            return "<div class='section {$sectionName}'><h2>{$section['title']}</h2><p>Sezione non implementata: {$sectionName}</p></div>";
        }

        return View::make($viewName, [
            'data' => $data,
            'section' => $section,
            'template' => $template,
        ])->render();
    }

    /**
     * Save content to file based on output format.
     */
    private function saveToFile(string $content, DatasheetTemplate $template, array $data): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $modelName = class_basename($data['model']);
        $modelId = $data['model']->id;
        $fileName = "datasheet_{$modelName}_{$modelId}_{$timestamp}";

        switch ($template->output_format) {
            case 'pdf':
                return $this->savePdf($content, $fileName);
            case 'html':
                return $this->saveHtml($content, $fileName);
            case 'markdown':
                return $this->saveMarkdown($content, $fileName);
            default:
                throw new \InvalidArgumentException("Unsupported output format: {$template->output_format}");
        }
    }

    /**
     * Save as PDF using DomPDF.
     */
    private function savePdf(string $content, string $fileName): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        $filePath = "datasheets/pdf/{$fileName}.pdf";
        
        Storage::disk('local')->put($filePath, $pdfContent);
        
        return $filePath;
    }

    /**
     * Save as HTML.
     */
    private function saveHtml(string $content, string $fileName): string
    {
        $filePath = "datasheets/html/{$fileName}.html";
        Storage::disk('local')->put($filePath, $content);
        return $filePath;
    }

    /**
     * Save as Markdown.
     */
    private function saveMarkdown(string $content, string $fileName): string
    {
        // Convert HTML to Markdown (basic conversion)
        $markdown = $this->htmlToMarkdown($content);
        $filePath = "datasheets/markdown/{$fileName}.md";
        Storage::disk('local')->put($filePath, $markdown);
        return $filePath;
    }

    /**
     * Create generated datasheet database record.
     */
    private function createGeneratedRecord(Model $model, DatasheetTemplate $template, array $data, string $filePath): GeneratedDatasheet
    {
        $fileSize = Storage::disk('local')->size($filePath);
        $fileHash = hash('sha256', Storage::disk('local')->get($filePath));

        return GeneratedDatasheet::create([
            'datasheet_template_id' => $template->id,
            'generatable_type' => get_class($model),
            'generatable_id' => $model->id,
            'title' => $data['title'],
            'version' => '1.0',
            'description' => $data['description'] ?? null,
            'generated_data' => $data,
            'file_path' => $filePath,
            'file_format' => $template->output_format,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);
    }

    /**
     * Extract project specifications from various sources.
     */
    private function extractProjectSpecifications(Project $project): array
    {
        $specs = [];

        // From custom datasheet data
        if ($project->projectDatasheetData?->custom_specifications) {
            $specs = array_merge($specs, $project->projectDatasheetData->custom_specifications);
        }

        // From system instances
        foreach ($project->systemInstances as $instance) {
            if ($instance->custom_specifications) {
                $specs[$instance->instance_name] = $instance->custom_specifications;
            }
        }

        return $specs;
    }

    /**
     * Extract project features.
     */
    private function extractProjectFeatures(Project $project): array
    {
        $features = [];

        if ($project->projectDatasheetData?->features_list) {
            $features = explode("\n", $project->projectDatasheetData->features_list);
        }

        return array_filter($features);
    }

    /**
     * Extract project performance data.
     */
    private function extractProjectPerformance(Project $project): array
    {
        return $project->projectDatasheetData?->performance_data ?? [];
    }

    /**
     * Extract electrical specifications from component.
     */
    private function extractElectricalSpecs(Component $component): array
    {
        return $component->componentDatasheetData?->electrical_specs ?? [];
    }

    /**
     * Extract mechanical specifications from component.
     */
    private function extractMechanicalSpecs(Component $component): array
    {
        return $component->componentDatasheetData?->mechanical_specs ?? [];
    }

    /**
     * Extract environmental specifications from component.
     */
    private function extractEnvironmentalSpecs(Component $component): array
    {
        return $component->componentDatasheetData?->environmental_specs ?? [];
    }

    /**
     * Basic HTML to Markdown conversion.
     */
    private function htmlToMarkdown(string $html): string
    {
        // Basic HTML to Markdown conversion
        $markdown = $html;
        
        // Headers
        $markdown = preg_replace('/<h1[^>]*>(.*?)<\/h1>/i', '# $1', $markdown);
        $markdown = preg_replace('/<h2[^>]*>(.*?)<\/h2>/i', '## $1', $markdown);
        $markdown = preg_replace('/<h3[^>]*>(.*?)<\/h3>/i', '### $1', $markdown);
        
        // Bold and italic
        $markdown = preg_replace('/<strong[^>]*>(.*?)<\/strong>/i', '**$1**', $markdown);
        $markdown = preg_replace('/<em[^>]*>(.*?)<\/em>/i', '*$1*', $markdown);
        
        // Remove HTML tags
        $markdown = strip_tags($markdown);
        
        return $markdown;
    }
}