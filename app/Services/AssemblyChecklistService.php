<?php

namespace App\Services;

use App\Models\AssemblyChecklist;
use App\Models\AssemblyChecklistItem;
use App\Models\AssemblyChecklistResponse;
use App\Models\AssemblyChecklistTemplate;
use App\Models\BoardAssemblyLog;
use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssemblyChecklistService
{
    private CompanyProfile $profile;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->profile = CompanyProfile::current();
    }

    /**
     * Generate AI-powered checklist for a board assembly log.
     * Creates a template dynamically and generates a checklist instance.
     */
    public function generateChecklistForAssembly(BoardAssemblyLog $log): ?AssemblyChecklist
    {
        try {
            // Check if checklist already exists for this assembly log
            $existingChecklist = AssemblyChecklist::where('board_assembly_log_id', $log->id)->first();
            if ($existingChecklist) {
                Log::info('Checklist already exists for assembly log', ['assembly_log_id' => $log->id]);
                return $existingChecklist;
            }

            // Load project relationships
            $log->load('project.customer', 'project.boms.components');

            // Try AI generation first, fallback to template if AI fails
            if ($this->isClaudeEnabled()) {
                $checklist = $this->generateWithAI($log);
                if ($checklist) {
                    Log::info('Assembly checklist generated with AI', [
                        'assembly_log_id' => $log->id,
                        'items_count' => $checklist->total_items
                    ]);
                    return $checklist;
                }
            }

            // Fallback to template-based generation
            $checklist = $this->generateFromTemplate($log);

            Log::info('Assembly checklist generated from template', [
                'assembly_log_id' => $log->id,
                'items_count' => $checklist->total_items
            ]);

            return $checklist;

        } catch (\Exception $e) {
            Log::error('Failed to generate assembly checklist', [
                'assembly_log_id' => $log->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Final fallback - create basic checklist
            return $this->createBasicChecklist($log);
        }
    }

    /**
     * Generate checklist using Claude AI.
     */
    private function generateWithAI(BoardAssemblyLog $log): ?AssemblyChecklist
    {
        try {
            $prompt = $this->buildAIPrompt($log);
            $aiResponse = $this->makeClaudeRequest($prompt, 2000);

            if (!$aiResponse) {
                return null;
            }

            // Parse AI response
            $checklistData = $this->parseAIResponse($aiResponse);
            if (empty($checklistData)) {
                return null;
            }

            // Create dynamic template
            $template = $this->createDynamicTemplate($log, $checklistData);

            // Create checklist instance from template
            return $this->createChecklistFromTemplate($template, $log);

        } catch (\Exception $e) {
            Log::error('AI checklist generation failed', [
                'error' => $e->getMessage(),
                'assembly_log_id' => $log->id
            ]);
            return null;
        }
    }

    /**
     * Generate checklist from existing template.
     */
    private function generateFromTemplate(BoardAssemblyLog $log): AssemblyChecklist
    {
        // Find appropriate template based on assembly type
        $boardType = $log->is_prototype ? 'prototype' : 'production';

        $template = AssemblyChecklistTemplate::active()
            ->where('board_type', $boardType)
            ->where('is_default', true)
            ->first();

        // If no template found, use generic template or create one
        if (!$template) {
            $template = AssemblyChecklistTemplate::active()
                ->where('board_type', 'generic')
                ->where('is_default', true)
                ->first();
        }

        if (!$template) {
            // Create basic generic template if none exists
            $template = $this->createBasicTemplate($log);
        }

        return $this->createChecklistFromTemplate($template, $log);
    }

    /**
     * Create checklist instance from template.
     */
    private function createChecklistFromTemplate(AssemblyChecklistTemplate $template, BoardAssemblyLog $log): AssemblyChecklist
    {
        // Create checklist
        $checklist = AssemblyChecklist::create([
            'template_id' => $template->id,
            'project_id' => $log->project_id,
            'board_assembly_log_id' => $log->id,
            'board_serial_number' => null,
            'batch_number' => $log->batch_number,
            'board_quantity' => $log->boards_count,
            'status' => 'not_started',
            'assigned_to' => $log->user_id,
            'total_items' => $template->items()->count(),
            'completed_items' => 0,
            'failed_items' => 0,
            'requires_supervisor_approval' => $template->complexity_level === 'expert',
        ]);

        // Create response records for all template items
        foreach ($template->items as $item) {
            AssemblyChecklistResponse::create([
                'checklist_id' => $checklist->id,
                'item_id' => $item->id,
                'user_id' => $log->user_id,
                'status' => 'pending',
            ]);
        }

        return $checklist;
    }

    /**
     * Build AI prompt for checklist generation.
     */
    private function buildAIPrompt(BoardAssemblyLog $log): string
    {
        $project = $log->project;
        $projectName = $project->name ?? 'Unknown Project';
        $boardsCount = $log->boards_count;
        $assemblyType = $log->is_prototype ? 'Test/Prototipo' : 'Produzione Serie';

        // Gather BOM information
        $bomInfo = $this->extractBOMInfo($log);
        $complexity = $this->assessComplexity($log);

        return "Sei un esperto di assemblaggio PCB e quality control per Supernova Industries S.R.L.

Devi generare una checklist di assemblaggio dettagliata e professionale per PCB seguendo gli standard IPC-A-610.

CARATTERISTICHE ASSEMBLAGGIO:
- Progetto: {$projectName}
- Numero schede: {$boardsCount}
- Tipo assemblaggio: {$assemblyType}
- Complessità: {$complexity}
- Batch: {$log->batch_number}

{$bomInfo}

CATEGORIE OBBLIGATORIE (usa ESATTAMENTE questi nomi):
1. Pre-Assembly: Verifica materiali, tools, ESD protection, workspace setup
2. Component Inspection: Controllo qualità componenti, verifica part numbers
3. Soldering: Processo saldatura (SMD reflow, through-hole, touch-up)
4. Testing: Test funzionali, continuità, alimentazione
5. QC: Quality control finale secondo IPC-A-610
6. Packaging: Imballaggio, etichettatura, documentazione

ISTRUZIONI:
1. Genera 15-25 item specifici e actionable
2. Ogni item deve essere tecnico e professionale
3. Usa terminologia PCB assembly standard (IPC, ESD, reflow, etc)
4. Per assemblaggio PRODUZIONE: focus su ripetibilità e QC stringente
5. Per assemblaggio TEST/PROTOTIPO: focus su debug e validazione funzionale
6. Includi verifiche critiche (critical: true) per safety e quality
7. Specifica tipo di check appropriato (checkbox, measurement, photo, etc)

FORMATO RISPOSTA - RESTITUISCI SOLO JSON VALIDO:
```json
[
    {
        \"title\": \"Verifica ESD Protection\",
        \"description\": \"Verificare che workstation sia dotata di ESD mat e wrist strap connessi\",
        \"instructions\": \"Testare continuità ESD mat e wrist strap con ohm meter (1-10 MOhm)\",
        \"type\": \"checkbox\",
        \"category\": \"Pre-Assembly\",
        \"is_required\": true,
        \"is_critical\": true,
        \"safety_notes\": \"ESD protection obbligatoria per componenti sensibili\",
        \"estimated_minutes\": 2,
        \"sort_order\": 1
    },
    {
        \"title\": \"Misura Tensione Alimentazione\",
        \"description\": \"Verificare tensione rail di alimentazione principali\",
        \"instructions\": \"Misurare con multimetro Vcc, Gnd, verificare tolleranza ±5%\",
        \"type\": \"measurement\",
        \"category\": \"Testing\",
        \"validation_rules\": {\"target\": 5.0, \"tolerance\": 0.25, \"unit\": \"V\"},
        \"is_required\": true,
        \"is_critical\": true,
        \"estimated_minutes\": 5,
        \"sort_order\": 15
    }
]
```

IMPORTANTE:
- Descrizioni chiare e concise (max 100 caratteri per title)
- Instructions dettagliate dove necessario
- type validi: checkbox, text, number, measurement, photo, file
- category DEVONO essere esattamente una delle 6 sopra
- critical items per verifiche di sicurezza e qualità
- Tempi stimati realistici in minuti
- Sort order progressivo per workflow logico

Restituisci SOLO l'array JSON, nessun commento o testo aggiuntivo.";
    }

    /**
     * Extract BOM information from assembly log.
     */
    private function extractBOMInfo(BoardAssemblyLog $log): string
    {
        $project = $log->project;
        $boms = $project->boms ?? collect();

        if ($boms->isEmpty()) {
            return "BOM: Non disponibile";
        }

        $totalComponents = $boms->flatMap(function ($bom) {
            return $bom->components ?? collect();
        })->unique('id')->count();

        $componentTypes = $boms->flatMap(function ($bom) {
            return $bom->components ?? collect();
        })->pluck('package_type')->filter()->unique()->take(5)->implode(', ');

        $bomInfo = "COMPONENTI BOM:\n";
        $bomInfo .= "- Totale componenti unici: {$totalComponents}\n";
        if ($componentTypes) {
            $bomInfo .= "- Package types: {$componentTypes}\n";
        }

        return $bomInfo;
    }

    /**
     * Assess assembly complexity based on project data.
     */
    private function assessComplexity(BoardAssemblyLog $log): string
    {
        $project = $log->project;
        $boms = $project->boms ?? collect();

        // Count total components
        $totalComponents = $boms->flatMap(function ($bom) {
            return $bom->components ?? collect();
        })->unique('id')->count();

        // Assess complexity
        if ($totalComponents > 100) {
            return 'Expert (molti componenti)';
        } elseif ($totalComponents > 50) {
            return 'Complex (componenti moderati)';
        } elseif ($totalComponents > 20) {
            return 'Medium (componenti standard)';
        } else {
            return 'Simple (pochi componenti)';
        }
    }

    /**
     * Parse AI response JSON.
     */
    private function parseAIResponse(string $response): array
    {
        // Remove markdown code blocks if present
        $cleanResponse = preg_replace('/^```json\s*/m', '', $response);
        $cleanResponse = preg_replace('/\s*```$/m', '', $cleanResponse);
        $cleanResponse = trim($cleanResponse);

        try {
            $items = json_decode($cleanResponse, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($items)) {
                throw new \Exception('Response is not an array');
            }

            // Validate each item
            $validatedItems = [];
            foreach ($items as $index => $item) {
                if (!isset($item['title']) || !isset($item['category'])) {
                    Log::warning('Invalid checklist item from AI', ['item' => $item]);
                    continue;
                }

                $validatedItems[] = [
                    'title' => substr($item['title'], 0, 255),
                    'description' => $item['description'] ?? '',
                    'instructions' => $item['instructions'] ?? null,
                    'type' => $this->normalizeItemType($item['type'] ?? 'checkbox'),
                    'category' => $this->normalizeCategory($item['category']),
                    'is_required' => $item['is_required'] ?? true,
                    'is_critical' => $item['is_critical'] ?? false,
                    'validation_rules' => $item['validation_rules'] ?? null,
                    'safety_notes' => $item['safety_notes'] ?? null,
                    'estimated_minutes' => (int)($item['estimated_minutes'] ?? 5),
                    'sort_order' => (int)($item['sort_order'] ?? ($index + 1)),
                ];
            }

            return $validatedItems;

        } catch (\JsonException $e) {
            Log::error('Failed to parse checklist JSON from AI', [
                'error' => $e->getMessage(),
                'response' => substr($cleanResponse, 0, 500)
            ]);
            return [];
        }
    }

    /**
     * Normalize item type to valid values.
     */
    private function normalizeItemType(string $type): string
    {
        $validTypes = ['checkbox', 'text', 'number', 'measurement', 'photo', 'file', 'signature', 'multiselect'];
        $normalized = strtolower(trim($type));

        return in_array($normalized, $validTypes) ? $normalized : 'checkbox';
    }

    /**
     * Normalize category name.
     */
    private function normalizeCategory(string $category): string
    {
        $validCategories = [
            'Pre-Assembly',
            'Component Inspection',
            'Soldering',
            'Testing',
            'QC',
            'Packaging'
        ];

        // Direct match
        foreach ($validCategories as $valid) {
            if (strcasecmp($category, $valid) === 0) {
                return $valid;
            }
        }

        // Fuzzy match
        $categoryLower = strtolower($category);
        if (str_contains($categoryLower, 'pre') || str_contains($categoryLower, 'setup')) {
            return 'Pre-Assembly';
        }
        if (str_contains($categoryLower, 'component') || str_contains($categoryLower, 'inspection')) {
            return 'Component Inspection';
        }
        if (str_contains($categoryLower, 'solder') || str_contains($categoryLower, 'reflow')) {
            return 'Soldering';
        }
        if (str_contains($categoryLower, 'test')) {
            return 'Testing';
        }
        if (str_contains($categoryLower, 'qc') || str_contains($categoryLower, 'quality')) {
            return 'QC';
        }
        if (str_contains($categoryLower, 'pack')) {
            return 'Packaging';
        }

        return 'Pre-Assembly'; // Default
    }

    /**
     * Create dynamic template from AI-generated items.
     */
    private function createDynamicTemplate(BoardAssemblyLog $log, array $items): AssemblyChecklistTemplate
    {
        $boardType = $log->is_prototype ? 'prototype' : 'production';
        $templateName = "AI Generated - {$log->project->code} - " . now()->format('Y-m-d H:i');

        $template = AssemblyChecklistTemplate::create([
            'name' => $templateName,
            'description' => "AI-generated checklist for {$log->project->name}",
            'board_type' => $boardType,
            'complexity_level' => 'medium',
            'is_active' => true,
            'is_default' => false,
            'estimated_time_minutes' => array_sum(array_column($items, 'estimated_minutes')),
            'created_by' => $log->user_id,
            'metadata' => [
                'generated_by' => 'claude_ai',
                'assembly_log_id' => $log->id,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);

        // Create items
        foreach ($items as $itemData) {
            AssemblyChecklistItem::create([
                'template_id' => $template->id,
                'title' => $itemData['title'],
                'description' => $itemData['description'],
                'instructions' => $itemData['instructions'],
                'type' => $itemData['type'],
                'category' => $itemData['category'],
                'is_required' => $itemData['is_required'],
                'is_critical' => $itemData['is_critical'],
                'validation_rules' => $itemData['validation_rules'],
                'safety_notes' => $itemData['safety_notes'],
                'estimated_minutes' => $itemData['estimated_minutes'],
                'sort_order' => $itemData['sort_order'],
            ]);
        }

        return $template->fresh(['items']);
    }

    /**
     * Create basic template as last resort fallback.
     */
    private function createBasicTemplate(BoardAssemblyLog $log): AssemblyChecklistTemplate
    {
        $boardType = $log->is_prototype ? 'prototype' : 'production';

        $template = AssemblyChecklistTemplate::create([
            'name' => "Basic {$boardType} Assembly Checklist",
            'description' => "Standard checklist for PCB assembly",
            'board_type' => $boardType,
            'complexity_level' => 'medium',
            'is_active' => true,
            'is_default' => false,
            'estimated_time_minutes' => 60,
            'created_by' => $log->user_id ?? 1,
        ]);

        // Create basic items
        $basicItems = $this->getBasicChecklistItems();
        foreach ($basicItems as $index => $itemData) {
            AssemblyChecklistItem::create([
                'template_id' => $template->id,
                'title' => $itemData['title'],
                'description' => $itemData['description'],
                'type' => $itemData['type'],
                'category' => $itemData['category'],
                'is_required' => $itemData['is_required'],
                'is_critical' => $itemData['is_critical'],
                'estimated_minutes' => $itemData['estimated_minutes'],
                'sort_order' => $index + 1,
            ]);
        }

        return $template->fresh(['items']);
    }

    /**
     * Create basic checklist without template.
     */
    private function createBasicChecklist(BoardAssemblyLog $log): AssemblyChecklist
    {
        $template = $this->createBasicTemplate($log);
        return $this->createChecklistFromTemplate($template, $log);
    }

    /**
     * Get basic checklist items as fallback.
     */
    private function getBasicChecklistItems(): array
    {
        return [
            [
                'title' => 'ESD Protection Setup',
                'description' => 'Verificare workstation ESD compliant',
                'type' => 'checkbox',
                'category' => 'Pre-Assembly',
                'is_required' => true,
                'is_critical' => true,
                'estimated_minutes' => 2,
            ],
            [
                'title' => 'Verifica Materiali',
                'description' => 'Controllare disponibilità PCB, componenti, solder paste',
                'type' => 'checkbox',
                'category' => 'Pre-Assembly',
                'is_required' => true,
                'is_critical' => false,
                'estimated_minutes' => 5,
            ],
            [
                'title' => 'Ispezione Componenti',
                'description' => 'Verificare part numbers e condizioni componenti',
                'type' => 'checkbox',
                'category' => 'Component Inspection',
                'is_required' => true,
                'is_critical' => true,
                'estimated_minutes' => 10,
            ],
            [
                'title' => 'Saldatura SMD',
                'description' => 'Reflow solder paste per componenti SMD',
                'type' => 'checkbox',
                'category' => 'Soldering',
                'is_required' => true,
                'is_critical' => false,
                'estimated_minutes' => 15,
            ],
            [
                'title' => 'Saldatura Through-Hole',
                'description' => 'Saldatura manuale componenti through-hole',
                'type' => 'checkbox',
                'category' => 'Soldering',
                'is_required' => false,
                'is_critical' => false,
                'estimated_minutes' => 10,
            ],
            [
                'title' => 'Test Continuità',
                'description' => 'Verificare continuità con multimetro',
                'type' => 'checkbox',
                'category' => 'Testing',
                'is_required' => true,
                'is_critical' => true,
                'estimated_minutes' => 5,
            ],
            [
                'title' => 'Test Alimentazione',
                'description' => 'Misurare tensioni rail di alimentazione',
                'type' => 'measurement',
                'category' => 'Testing',
                'is_required' => true,
                'is_critical' => true,
                'estimated_minutes' => 5,
            ],
            [
                'title' => 'Test Funzionale',
                'description' => 'Eseguire test funzionale completo',
                'type' => 'checkbox',
                'category' => 'Testing',
                'is_required' => true,
                'is_critical' => true,
                'estimated_minutes' => 10,
            ],
            [
                'title' => 'Ispezione Visiva QC',
                'description' => 'Ispezione visiva secondo IPC-A-610',
                'type' => 'checkbox',
                'category' => 'QC',
                'is_required' => true,
                'is_critical' => true,
                'estimated_minutes' => 5,
            ],
            [
                'title' => 'Foto Documentazione',
                'description' => 'Scattare foto per documentazione QC',
                'type' => 'photo',
                'category' => 'QC',
                'is_required' => false,
                'is_critical' => false,
                'estimated_minutes' => 3,
            ],
            [
                'title' => 'Etichettatura',
                'description' => 'Applicare etichetta con serial/batch number',
                'type' => 'checkbox',
                'category' => 'Packaging',
                'is_required' => true,
                'is_critical' => false,
                'estimated_minutes' => 2,
            ],
            [
                'title' => 'Imballaggio',
                'description' => 'Imballare scheda con protezione ESD',
                'type' => 'checkbox',
                'category' => 'Packaging',
                'is_required' => true,
                'is_critical' => false,
                'estimated_minutes' => 3,
            ],
        ];
    }

    /**
     * Check if Claude AI is enabled.
     */
    private function isClaudeEnabled(): bool
    {
        return $this->profile->isClaudeEnabled();
    }

    /**
     * Make request to Claude AI API.
     */
    private function makeClaudeRequest(string $prompt, int $maxTokens = 2000): ?string
    {
        if (!$this->isClaudeEnabled()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->profile->claude_api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => $this->profile->claude_model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['content'][0]['text'])) {
                    return trim($data['content'][0]['text']);
                }
            }

            Log::error('Claude AI checklist request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500)
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Claude AI checklist service error', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Regenerate checklist (delete old and create new).
     */
    public function regenerateChecklist(AssemblyChecklist $checklist): ?AssemblyChecklist
    {
        $log = BoardAssemblyLog::find($checklist->board_assembly_log_id);

        if (!$log) {
            return null;
        }

        // Delete old checklist and responses
        $checklist->responses()->delete();
        $checklist->delete();

        // Generate new checklist
        return $this->generateChecklistForAssembly($log);
    }
}
