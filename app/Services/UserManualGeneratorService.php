<?php

namespace App\Services;

use App\Models\Project;
use App\Models\UserManual;
use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class UserManualGeneratorService
{
    protected ClaudeAiService $claudeService;

    public function __construct(ClaudeAiService $claudeService)
    {
        $this->claudeService = $claudeService;
    }

    /**
     * Generate a user manual for a project.
     */
    public function generateManual(Project $project, array $config): UserManual
    {
        // Create manual record
        $manual = UserManual::create([
            'project_id' => $project->id,
            'title' => $this->generateTitle($project, $config['type']),
            'version' => $config['version'] ?? '1.0',
            'type' => $config['type'],
            'format' => $config['format'],
            'status' => 'draft',
            'generation_config' => array_merge(
                UserManual::getDefaultGenerationConfig($config['type']),
                $config['custom_config'] ?? []
            ),
            'generated_by' => auth()->id(),
        ]);

        // Generate content asynchronously (in real implementation, this would be a job)
        $this->generateContent($manual);

        return $manual;
    }

    /**
     * Generate content for a manual.
     */
    protected function generateContent(UserManual $manual): void
    {
        try {
            $manual->markAsGenerating();

            $project = $manual->project;
            $config = $manual->generation_config;

            // Build generation prompt
            $prompt = $this->buildGenerationPrompt($project, $manual->type, $config);
            
            // Generate content using Claude AI
            $generatedContent = $this->claudeService->generateUserManual($project, $prompt, $config);

            if (!$generatedContent) {
                throw new \Exception('AI service did not return content');
            }

            // Process and structure the content
            $structuredContent = $this->structureContent($generatedContent, $manual->type);

            // Generate file based on format
            $filePath = $this->generateFile($manual, $structuredContent);

            // Mark as completed
            $manual->markAsCompleted($filePath, $generatedContent);

        } catch (\Exception $e) {
            $manual->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Build AI generation prompt based on project and manual type.
     */
    protected function buildGenerationPrompt(Project $project, string $type, array $config): string
    {
        $basePrompt = "Generate a comprehensive {$type} manual for the electronic project: '{$project->name}'\n\n";
        
        $basePrompt .= "Project Context:\n";
        $basePrompt .= "- Customer: {$project->customer->company_name}\n";
        $basePrompt .= "- Description: {$project->description}\n";
        $basePrompt .= "- Status: {$project->status}\n";
        
        if ($project->folder) {
            $basePrompt .= "- Project Folder: {$project->folder}\n";
        }

        // Add BOM information if available
        $bomItems = $project->bomItems()->with('component')->get();
        if ($bomItems->isNotEmpty()) {
            $basePrompt .= "\nBill of Materials (Key Components):\n";
            foreach ($bomItems->take(10) as $item) {
                $basePrompt .= "- {$item->component->part_number}: {$item->component->description} (Qty: {$item->quantity})\n";
            }
        }

        // Add project documents context
        $documents = $project->documents()->where('type', '!=', 'invoice')->get();
        if ($documents->isNotEmpty()) {
            $basePrompt .= "\nAvailable Project Documents:\n";
            foreach ($documents as $doc) {
                $basePrompt .= "- {$doc->type}: {$doc->filename}\n";
            }
        }

        // Add type-specific instructions
        $basePrompt .= "\n" . $this->getTypeSpecificPrompt($type, $config);

        $basePrompt .= "\nFormat Requirements:\n";
        $basePrompt .= "- Use clear, professional language\n";
        $basePrompt .= "- Include numbered sections and subsections\n";
        $basePrompt .= "- Add safety warnings where appropriate\n";
        $basePrompt .= "- Use bullet points for lists\n";
        $basePrompt .= "- Include placeholders for diagrams: [DIAGRAM: description]\n";
        $basePrompt .= "- Detail level: {$config['detail_level']}\n";

        return $basePrompt;
    }

    /**
     * Get type-specific prompt instructions.
     */
    protected function getTypeSpecificPrompt(string $type, array $config): string
    {
        return match($type) {
            'installation' => $this->getInstallationPrompt($config),
            'operation' => $this->getOperationPrompt($config),
            'maintenance' => $this->getMaintenancePrompt($config),
            'troubleshooting' => $this->getTroubleshootingPrompt($config),
            'complete' => $this->getCompleteManualPrompt($config),
            default => "Create a general user manual covering basic operation and safety.",
        };
    }

    /**
     * Get installation manual specific prompt.
     */
    protected function getInstallationPrompt(array $config): string
    {
        $prompt = "Create an Installation Manual with the following sections:\n";
        $prompt .= "1. Safety Warnings and Precautions\n";
        $prompt .= "2. Required Tools and Materials\n";
        $prompt .= "3. Pre-Installation Checklist\n";
        $prompt .= "4. Step-by-Step Installation Procedure\n";
        $prompt .= "5. Wiring and Connections\n";
        $prompt .= "6. Initial System Configuration\n";
        $prompt .= "7. Testing and Verification\n";
        $prompt .= "8. Troubleshooting Common Installation Issues\n";

        if ($config['include_diagrams']) {
            $prompt .= "\nInclude diagram placeholders for:\n";
            $prompt .= "- Connection diagrams\n";
            $prompt .= "- Mounting layouts\n";
            $prompt .= "- Wiring schematics\n";
        }

        return $prompt;
    }

    /**
     * Get operation manual specific prompt.
     */
    protected function getOperationPrompt(array $config): string
    {
        $prompt = "Create an Operation Manual with the following sections:\n";
        $prompt .= "1. System Overview\n";
        $prompt .= "2. User Interface Description\n";
        $prompt .= "3. Startup Procedure\n";
        $prompt .= "4. Normal Operation Modes\n";
        $prompt .= "5. Control Functions and Settings\n";
        $prompt .= "6. Monitoring and Status Indicators\n";
        $prompt .= "7. Shutdown Procedure\n";
        $prompt .= "8. Operating Limits and Specifications\n";

        return $prompt;
    }

    /**
     * Get maintenance manual specific prompt.
     */
    protected function getMaintenancePrompt(array $config): string
    {
        $prompt = "Create a Maintenance Manual with the following sections:\n";
        $prompt .= "1. Preventive Maintenance Schedule\n";
        $prompt .= "2. Cleaning Procedures\n";
        $prompt .= "3. Component Inspection Guidelines\n";
        $prompt .= "4. Replacement Parts List\n";
        $prompt .= "5. Calibration Procedures\n";
        $prompt .= "6. Performance Testing\n";
        $prompt .= "7. Maintenance Records\n";
        $prompt .= "8. Safety During Maintenance\n";

        return $prompt;
    }

    /**
     * Get troubleshooting guide specific prompt.
     */
    protected function getTroubleshootingPrompt(array $config): string
    {
        $prompt = "Create a Troubleshooting Guide with the following sections:\n";
        $prompt .= "1. Common Problems and Solutions\n";
        $prompt .= "2. Error Codes and Meanings\n";
        $prompt .= "3. Diagnostic Procedures\n";
        $prompt .= "4. Component Testing Methods\n";
        $prompt .= "5. System Reset Procedures\n";
        $prompt .= "6. When to Contact Support\n";
        $prompt .= "7. Emergency Procedures\n";
        $prompt .= "8. Contact Information\n";

        return $prompt;
    }

    /**
     * Get complete manual specific prompt.
     */
    protected function getCompleteManualPrompt(array $config): string
    {
        $prompt = "Create a Complete User Manual combining all aspects:\n";
        $prompt .= "1. Introduction and Overview\n";
        $prompt .= "2. Safety Information\n";
        $prompt .= "3. Installation Guide\n";
        $prompt .= "4. Operation Instructions\n";
        $prompt .= "5. Maintenance Procedures\n";
        $prompt .= "6. Troubleshooting Guide\n";
        $prompt .= "7. Technical Specifications\n";
        $prompt .= "8. Appendices and References\n";

        if ($config['include_glossary']) {
            $prompt .= "9. Glossary of Terms\n";
        }

        return $prompt;
    }

    /**
     * Structure the generated content into sections.
     */
    protected function structureContent(string $content, string $type): array
    {
        // Split content into sections based on numbered headings
        $sections = [];
        $lines = explode("\n", $content);
        $currentSection = null;
        $currentContent = [];

        foreach ($lines as $line) {
            // Check if line is a section header (starts with number)
            if (preg_match('/^\d+\.\s+(.+)/', $line, $matches)) {
                // Save previous section if exists
                if ($currentSection) {
                    $sections[] = [
                        'title' => $currentSection,
                        'content' => implode("\n", $currentContent),
                    ];
                }
                
                // Start new section
                $currentSection = $matches[1];
                $currentContent = [];
            } else {
                $currentContent[] = $line;
            }
        }

        // Add last section
        if ($currentSection) {
            $sections[] = [
                'title' => $currentSection,
                'content' => implode("\n", $currentContent),
            ];
        }

        return $sections;
    }

    /**
     * Generate file in the specified format.
     */
    protected function generateFile(UserManual $manual, array $structuredContent): string
    {
        $filename = $this->generateFilename($manual);
        
        return match($manual->format) {
            'pdf' => $this->generatePdfFile($manual, $structuredContent, $filename),
            'html' => $this->generateHtmlFile($manual, $structuredContent, $filename),
            'markdown' => $this->generateMarkdownFile($manual, $structuredContent, $filename),
            'docx' => $this->generateDocxFile($manual, $structuredContent, $filename),
            default => throw new \Exception("Unsupported format: {$manual->format}"),
        };
    }

    /**
     * Generate filename for the manual.
     */
    protected function generateFilename(UserManual $manual): string
    {
        $projectCode = $manual->project->code ?? 'PROJECT';
        $type = Str::upper($manual->type);
        $version = str_replace('.', '-', $manual->version);
        $timestamp = now()->format('Ymd');
        
        return "{$projectCode}_{$type}_MANUAL_v{$version}_{$timestamp}";
    }

    /**
     * Generate PDF file.
     */
    protected function generatePdfFile(UserManual $manual, array $sections, string $filename): string
    {
        $html = $this->buildHtmlContent($manual, $sections);
        
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        
        $filePath = "user-manuals/{$filename}.pdf";
        Storage::put($filePath, $pdf->output());
        
        return $filePath;
    }

    /**
     * Generate HTML file.
     */
    protected function generateHtmlFile(UserManual $manual, array $sections, string $filename): string
    {
        $html = $this->buildHtmlContent($manual, $sections);
        
        $filePath = "user-manuals/{$filename}.html";
        Storage::put($filePath, $html);
        
        return $filePath;
    }

    /**
     * Generate Markdown file.
     */
    protected function generateMarkdownFile(UserManual $manual, array $sections, string $filename): string
    {
        $markdown = $this->buildMarkdownContent($manual, $sections);
        
        $filePath = "user-manuals/{$filename}.md";
        Storage::put($filePath, $markdown);
        
        return $filePath;
    }

    /**
     * Generate DOCX file (placeholder - would need additional package).
     */
    protected function generateDocxFile(UserManual $manual, array $sections, string $filename): string
    {
        // For now, generate as HTML with .docx extension
        // In production, would use phpoffice/phpword
        $html = $this->buildHtmlContent($manual, $sections);
        
        $filePath = "user-manuals/{$filename}.docx";
        Storage::put($filePath, $html);
        
        return $filePath;
    }

    /**
     * Build HTML content for the manual.
     */
    protected function buildHtmlContent(UserManual $manual, array $sections): string
    {
        $company = CompanyProfile::current();
        
        $html = "<!DOCTYPE html>\n<html>\n<head>\n";
        $html .= "<meta charset='utf-8'>\n";
        $html .= "<title>{$manual->title}</title>\n";
        $html .= "<style>\n";
        $html .= "body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }\n";
        $html .= "h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }\n";
        $html .= "h2 { color: #34495e; border-left: 4px solid #3498db; padding-left: 15px; }\n";
        $html .= "h3 { color: #7f8c8d; }\n";
        $html .= ".header { text-align: center; margin-bottom: 40px; }\n";
        $html .= ".warning { background-color: #fff3cd; border: 1px solid #ffeeba; padding: 15px; margin: 20px 0; border-radius: 5px; }\n";
        $html .= ".section { margin-bottom: 30px; }\n";
        $html .= "table { width: 100%; border-collapse: collapse; margin: 20px 0; }\n";
        $html .= "th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }\n";
        $html .= "th { background-color: #f8f9fa; }\n";
        $html .= "</style>\n";
        $html .= "</head>\n<body>\n";
        
        // Header
        $html .= "<div class='header'>\n";
        $html .= "<h1>{$manual->title}</h1>\n";
        $html .= "<p><strong>Project:</strong> {$manual->project->name}</p>\n";
        $html .= "<p><strong>Customer:</strong> {$manual->project->customer->company_name}</p>\n";
        $html .= "<p><strong>Version:</strong> {$manual->version}</p>\n";
        $html .= "<p><strong>Generated:</strong> " . now()->format('F j, Y') . "</p>\n";
        if ($company) {
            $html .= "<p><strong>Generated by:</strong> {$company->company_name}</p>\n";
        }
        $html .= "</div>\n\n";
        
        // Table of Contents
        $html .= "<h2>Table of Contents</h2>\n<ul>\n";
        foreach ($sections as $index => $section) {
            $html .= "<li><a href='#section-" . ($index + 1) . "'>" . ($index + 1) . ". {$section['title']}</a></li>\n";
        }
        $html .= "</ul>\n\n";
        
        // Content sections
        foreach ($sections as $index => $section) {
            $html .= "<div class='section'>\n";
            $html .= "<h2 id='section-" . ($index + 1) . "'>" . ($index + 1) . ". {$section['title']}</h2>\n";
            $html .= "<div>" . nl2br(htmlspecialchars($section['content'])) . "</div>\n";
            $html .= "</div>\n\n";
        }
        
        $html .= "</body>\n</html>";
        
        return $html;
    }

    /**
     * Build Markdown content for the manual.
     */
    protected function buildMarkdownContent(UserManual $manual, array $sections): string
    {
        $markdown = "# {$manual->title}\n\n";
        $markdown .= "**Project:** {$manual->project->name}\n";
        $markdown .= "**Customer:** {$manual->project->customer->company_name}\n";
        $markdown .= "**Version:** {$manual->version}\n";
        $markdown .= "**Generated:** " . now()->format('F j, Y') . "\n\n";
        
        // Table of Contents
        $markdown .= "## Table of Contents\n\n";
        foreach ($sections as $index => $section) {
            $markdown .= ($index + 1) . ". [{$section['title']}](#" . Str::slug($section['title']) . ")\n";
        }
        $markdown .= "\n";
        
        // Content sections
        foreach ($sections as $index => $section) {
            $markdown .= "## " . ($index + 1) . ". {$section['title']}\n\n";
            $markdown .= $section['content'] . "\n\n";
        }
        
        return $markdown;
    }

    /**
     * Generate title for the manual.
     */
    protected function generateTitle(Project $project, string $type): string
    {
        $typeNames = UserManual::getTypeOptions();
        $typeName = $typeNames[$type] ?? ucfirst($type);
        
        return "{$project->name} - {$typeName}";
    }
}