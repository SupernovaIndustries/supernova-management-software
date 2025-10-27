<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Component;
use App\Models\ComplianceStandard;
use App\Models\ComplianceAiAnalysis;
use App\Services\ClaudeAiService;
use Illuminate\Database\Eloquent\Model;

class ComplianceAiService
{
    protected ClaudeAiService $claudeAi;

    public function __construct(ClaudeAiService $claudeAi)
    {
        $this->claudeAi = $claudeAi;
    }

    /**
     * Analyze a project or component for compliance requirements using AI.
     */
    public function analyzeCompliance(Model $model): ComplianceAiAnalysis
    {
        // Collect data for AI analysis
        $inputData = $this->collectAnalysisData($model);
        
        // Prepare AI prompt
        $prompt = $this->buildCompliancePrompt($model, $inputData);
        
        // Get AI recommendations
        $aiResponse = $this->claudeAi->sendMessage($prompt);
        
        // Parse AI response
        $parsedResponse = $this->parseAiResponse($aiResponse);
        
        // Create analysis record
        return ComplianceAiAnalysis::create([
            'analyzable_type' => get_class($model),
            'analyzable_id' => $model->id,
            'input_data' => $inputData,
            'ai_recommendations' => $parsedResponse['recommendations'],
            'detected_standards' => $parsedResponse['standards'],
            'risk_assessment' => $parsedResponse['risks'],
            'ai_reasoning' => $parsedResponse['reasoning'],
            'confidence_score' => $parsedResponse['confidence'],
            'analyzed_by' => auth()->id(),
            'analyzed_at' => now(),
        ]);
    }

    /**
     * Collect data for AI analysis.
     */
    private function collectAnalysisData(Model $model): array
    {
        $data = [
            'model_type' => class_basename($model),
            'model_name' => $model->name ?? $model->title ?? 'Unknown',
        ];

        if ($model instanceof Project) {
            $data = array_merge($data, $this->collectProjectData($model));
        } elseif ($model instanceof Component) {
            $data = array_merge($data, $this->collectComponentData($model));
        }

        return $data;
    }

    /**
     * Collect project-specific data for analysis.
     */
    private function collectProjectData(Project $project): array
    {
        return [
            'description' => $project->description,
            'customer_country' => $project->customer?->country ?? 'Unknown',
            'target_market' => $project->projectDatasheetData?->target_market ?? 'Unknown',
            'components' => $project->bomComponents?->map(function ($bomItem) {
                return [
                    'name' => $bomItem->component->name,
                    'category' => $bomItem->component->category?->name,
                    'manufacturer' => $bomItem->component->manufacturer,
                    'part_number' => $bomItem->component->part_number,
                    'voltage' => $bomItem->component->specifications['voltage'] ?? null,
                    'current' => $bomItem->component->specifications['current'] ?? null,
                    'frequency' => $bomItem->component->specifications['frequency'] ?? null,
                ];
            })->toArray() ?? [],
            'system_instances' => $project->systemInstances?->map(function ($instance) {
                return [
                    'system_type' => $instance->systemVariant->category->display_name,
                    'variant' => $instance->systemVariant->display_name,
                    'specifications' => $instance->custom_specifications ?? [],
                ];
            })->toArray() ?? [],
            'power_requirements' => $this->extractPowerRequirements($project),
            'wireless_technologies' => $this->extractWirelessTechnologies($project),
            'operating_environment' => $this->extractOperatingEnvironment($project),
        ];
    }

    /**
     * Collect component-specific data for analysis.
     */
    private function collectComponentData(Component $component): array
    {
        return [
            'description' => $component->description,
            'category' => $component->category?->name,
            'manufacturer' => $component->manufacturer,
            'part_number' => $component->part_number,
            'specifications' => $component->specifications ?? [],
            'datasheet_url' => $component->datasheet_url,
            'electrical_specs' => $component->componentDatasheetData?->electrical_specs ?? [],
            'environmental_specs' => $component->componentDatasheetData?->environmental_specs ?? [],
        ];
    }

    /**
     * Build AI prompt for compliance analysis.
     */
    private function buildCompliancePrompt(Model $model, array $inputData): string
    {
        $availableStandards = ComplianceStandard::active()->get()->map(function ($standard) {
            return [
                'code' => $standard->code,
                'name' => $standard->name,
                'scope' => $standard->geographic_scope,
                'categories' => $standard->applicable_categories,
                'description' => $standard->description,
            ];
        });

        $modelType = class_basename($model);
        $modelName = $inputData['model_name'];

        return "Analizza i seguenti dati per un {$modelType} chiamato '{$modelName}' e determina le certificazioni di conformità necessarie:

DATI DEL PRODOTTO:
" . json_encode($inputData, JSON_PRETTY_PRINT) . "

STANDARD DI CONFORMITÀ DISPONIBILI:
" . json_encode($availableStandards, JSON_PRETTY_PRINT) . "

RICHIESTA:
Analizza i dati forniti e fornisci una risposta strutturata in formato JSON con le seguenti sezioni:

1. RECOMMENDATIONS: Array di raccomandazioni prioritizzate
2. STANDARDS: Array degli standard applicabili con motivazioni
3. RISKS: Valutazione dei rischi di non conformità
4. REASONING: Spiegazione dettagliata del ragionamento
5. CONFIDENCE: Punteggio di fiducia da 0 a 100

Per ogni standard raccomandato includi:
- code: Codice dello standard
- priority: high/medium/low
- mandatory: true/false
- reasoning: Perché è necessario
- estimated_cost: Stima costi (se possibile)
- timeframe: Tempi stimati per ottenimento

Considera:
- Paese di destinazione del prodotto
- Categoria del prodotto
- Componenti utilizzati
- Specifiche tecniche
- Presenza di wireless/RF
- Tensioni e correnti
- Ambiente operativo

IMPORTANTE: Rispondi SOLO con JSON valido, senza testo aggiuntivo.";
    }

    /**
     * Parse AI response into structured data.
     */
    private function parseAiResponse(string $response): array
    {
        try {
            // Clean response (remove potential markdown formatting)
            $cleanResponse = preg_replace('/```json\n?/', '', $response);
            $cleanResponse = preg_replace('/```\n?$/', '', $cleanResponse);
            $cleanResponse = trim($cleanResponse);

            $parsed = json_decode($cleanResponse, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from AI');
            }

            return [
                'recommendations' => $parsed['RECOMMENDATIONS'] ?? [],
                'standards' => $parsed['STANDARDS'] ?? [],
                'risks' => $parsed['RISKS'] ?? [],
                'reasoning' => $parsed['REASONING'] ?? 'No reasoning provided',
                'confidence' => $parsed['CONFIDENCE'] ?? 50,
            ];

        } catch (\Exception $e) {
            // Fallback response if AI parsing fails
            return [
                'recommendations' => [
                    [
                        'type' => 'manual_review',
                        'priority' => 'high',
                        'description' => 'AI analysis failed. Manual review required.',
                        'reason' => 'Error parsing AI response: ' . $e->getMessage()
                    ]
                ],
                'standards' => [],
                'risks' => ['AI analysis error - manual compliance review recommended'],
                'reasoning' => 'AI response could not be parsed: ' . $e->getMessage(),
                'confidence' => 0,
            ];
        }
    }

    /**
     * Extract power requirements from project components.
     */
    private function extractPowerRequirements(Project $project): array
    {
        $powerData = [];
        
        foreach ($project->bomComponents ?? [] as $bomItem) {
            $component = $bomItem->component;
            $specs = $component->specifications ?? [];
            
            if (isset($specs['voltage'])) {
                $powerData['voltages'][] = $specs['voltage'];
            }
            if (isset($specs['current'])) {
                $powerData['currents'][] = $specs['current'];
            }
            if (isset($specs['power'])) {
                $powerData['power_ratings'][] = $specs['power'];
            }
        }

        return [
            'max_voltage' => !empty($powerData['voltages']) ? max($powerData['voltages']) : null,
            'total_current' => !empty($powerData['currents']) ? array_sum($powerData['currents']) : null,
            'total_power' => !empty($powerData['power_ratings']) ? array_sum($powerData['power_ratings']) : null,
        ];
    }

    /**
     * Extract wireless technologies from project components.
     */
    private function extractWirelessTechnologies(Project $project): array
    {
        $wireless = [];
        
        foreach ($project->bomComponents ?? [] as $bomItem) {
            $component = $bomItem->component;
            $name = strtolower($component->name);
            $specs = $component->specifications ?? [];
            
            // Detect wireless technologies
            if (str_contains($name, 'wifi') || str_contains($name, '802.11')) {
                $wireless[] = 'WiFi';
            }
            if (str_contains($name, 'bluetooth') || str_contains($name, 'ble')) {
                $wireless[] = 'Bluetooth';
            }
            if (str_contains($name, 'gsm') || str_contains($name, 'lte') || str_contains($name, '4g') || str_contains($name, '5g')) {
                $wireless[] = 'Cellular';
            }
            if (str_contains($name, 'zigbee')) {
                $wireless[] = 'ZigBee';
            }
            if (isset($specs['frequency'])) {
                $wireless[] = 'RF (' . $specs['frequency'] . ')';
            }
        }

        return array_unique($wireless);
    }

    /**
     * Extract operating environment info.
     */
    private function extractOperatingEnvironment(Project $project): array
    {
        $environment = [
            'indoor' => true, // Default assumption
            'outdoor' => false,
            'industrial' => false,
            'medical' => false,
            'automotive' => false,
        ];

        // Analyze project description and customer info
        $description = strtolower($project->description ?? '');
        $customerName = strtolower($project->customer?->name ?? '');

        if (str_contains($description, 'outdoor') || str_contains($description, 'external')) {
            $environment['outdoor'] = true;
        }
        if (str_contains($description, 'industrial') || str_contains($description, 'factory')) {
            $environment['industrial'] = true;
        }
        if (str_contains($description, 'medical') || str_contains($customerName, 'medical')) {
            $environment['medical'] = true;
        }
        if (str_contains($description, 'automotive') || str_contains($description, 'vehicle')) {
            $environment['automotive'] = true;
        }

        return $environment;
    }

    /**
     * Generate compliance document content using AI.
     */
    public function generateComplianceDocument(string $templateContent, array $projectData, array $complianceData): string
    {
        $prompt = "Genera il contenuto per un documento di conformità basato sui seguenti dati:

TEMPLATE:
{$templateContent}

DATI PROGETTO:
" . json_encode($projectData, JSON_PRETTY_PRINT) . "

DATI CONFORMITÀ:
" . json_encode($complianceData, JSON_PRETTY_PRINT) . "

ISTRUZIONI:
1. Sostituisci tutti i placeholder nel template con i dati appropriati
2. Genera contenuto tecnico accurato e professionale
3. Includi tutti i dettagli richiesti per la conformità
4. Mantieni il formato e la struttura del template
5. Assicurati che il documento sia completo e conforme agli standard

Restituisci il documento compilato pronto per la firma.";

        return $this->claudeAi->sendMessage($prompt);
    }
}