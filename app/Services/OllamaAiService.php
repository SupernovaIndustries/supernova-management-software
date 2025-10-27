<?php

namespace App\Services;

use App\Contracts\AiServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ollama AI Service - Local AI integration using Ollama
 *
 * This service provides a local AI alternative to Claude API using Ollama.
 * Supports all AI features: project descriptions, milestones, emails, user manuals.
 *
 * SETUP:
 * 1. Install Ollama: https://ollama.ai/download
 * 2. Pull model: ollama pull llama3.1:8b
 * 3. Configure .env:
 *    AI_PROVIDER=ollama
 *    OLLAMA_API_URL=http://localhost:11434
 *    OLLAMA_MODEL=llama3.1:8b
 *
 * @see https://github.com/ollama/ollama/blob/main/docs/api.md
 */
class OllamaAiService implements AiServiceInterface
{
    private string $apiUrl;
    private string $model;

    public function __construct()
    {
        $this->apiUrl = config('services.ollama.api_url', 'http://localhost:11434');
        $this->model = config('services.ollama.model', 'llama3.1:8b');
    }

    /**
     * Check if Ollama is properly configured and running.
     */
    public function isConfigured(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->apiUrl}/api/tags");

            if ($response->successful()) {
                $models = $response->json('models', []);
                foreach ($models as $modelInfo) {
                    // Check if our configured model is available
                    if (str_contains($modelInfo['name'], $this->model) || str_contains($modelInfo['name'], explode(':', $this->model)[0])) {
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('Ollama not available', ['error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Improve project description using Ollama.
     */
    public function improveProjectDescription(string $projectName, string $currentDescription = '', array $context = []): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $prompt = $this->buildProjectDescriptionPrompt($projectName, $currentDescription, $context);

        // Use 800 tokens max for faster response (description improvement)
        return $this->makeRequest($prompt, 800);
    }

    /**
     * Improve milestone description using Ollama.
     */
    public function improveMilestoneDescription(string $milestoneName, string $projectName, string $currentDescription = '', array $context = []): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $prompt = $this->buildMilestoneDescriptionPrompt($milestoneName, $projectName, $currentDescription, $context);

        return $this->makeRequest($prompt);
    }

    /**
     * Generate project milestones from project details.
     */
    public function generateProjectMilestones(string $projectName, string $projectDescription, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $prompt = $this->buildProjectMilestonesPrompt($projectName, $projectDescription, $context);

        try {
            $response = $this->makeRequest($prompt, 2000);

            if (!$response) {
                return [];
            }

            // Parse JSON response from Ollama
            $milestones = $this->parseMilestonesFromResponse($response);

            Log::info('Ollama AI generated milestones', [
                'project' => $projectName,
                'count' => count($milestones)
            ]);

            return $milestones;
        } catch (\Exception $e) {
            Log::error('Failed to generate milestones with Ollama AI', [
                'error' => $e->getMessage(),
                'project' => $projectName
            ]);

            return [];
        }
    }

    /**
     * Generate email content for project notifications.
     */
    public function generateProjectNotificationEmail(string $projectName, string $clientName, \DateTime $deadline, array $context = []): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $prompt = $this->buildEmailNotificationPrompt($projectName, $clientName, $deadline, $context);

        return $this->makeRequest($prompt);
    }

    /**
     * Generate user manual content using Ollama.
     */
    public function generateUserManual(\App\Models\Project $project, string $prompt, array $config): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        // Use higher token limit for manual generation
        $maxTokens = 4000;

        return $this->makeRequest($prompt, $maxTokens);
    }

    /**
     * Test Ollama connection.
     */
    public function testConnection(): array
    {
        try {
            // Check if server is running
            $tagsResponse = Http::timeout(5)->get("{$this->apiUrl}/api/tags");

            if (!$tagsResponse->successful()) {
                return [
                    'success' => false,
                    'message' => 'Ollama server non raggiungibile su ' . $this->apiUrl
                ];
            }

            // Check if model is available
            $models = $tagsResponse->json('models', []);
            $modelFound = false;
            foreach ($models as $modelInfo) {
                if (str_contains($modelInfo['name'], $this->model) || str_contains($modelInfo['name'], explode(':', $this->model)[0])) {
                    $modelFound = true;
                    break;
                }
            }

            if (!$modelFound) {
                return [
                    'success' => false,
                    'message' => "Modello '{$this->model}' non trovato. Esegui: ollama pull {$this->model}"
                ];
            }

            // Test actual generation
            $response = $this->makeRequest("Rispondi semplicemente 'Test connessione Ollama riuscito' in italiano.", 50);

            if ($response) {
                return [
                    'success' => true,
                    'message' => 'Connessione riuscita',
                    'response' => trim($response)
                ];
            }

            return [
                'success' => false,
                'message' => 'Errore durante la generazione di testo'
            ];
        } catch (\Exception $e) {
            Log::error('Ollama test exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build prompt for project description improvement.
     */
    private function buildProjectDescriptionPrompt(string $projectName, string $currentDescription, array $context): string
    {
        $contextInfo = '';
        if (!empty($context['customer_name'])) {
            $contextInfo .= "Cliente: {$context['customer_name']}\n";
        }
        if (!empty($context['category'])) {
            $contextInfo .= "Categoria: {$context['category']}\n";
        }
        if (!empty($context['budget'])) {
            $contextInfo .= "Budget: €" . number_format($context['budget'], 2) . "\n";
        }
        if (!empty($context['quotation'])) {
            $contextInfo .= "{$context['quotation']}\n";
        }
        if (!empty($context['boards_count'])) {
            $contextInfo .= "Numero schede: {$context['boards_count']}\n";
        }
        if (!empty($context['due_date'])) {
            $dueDate = $context['due_date'] instanceof \DateTime ? $context['due_date']->format('d/m/Y') : $context['due_date'];
            $contextInfo .= "Scadenza: {$dueDate}\n";
        }

        // Detect if this is software/firmware focused (default is hardware/PCB)
        $isSoftwareFocused = $this->isSoftwareProject($projectName, $currentDescription);

        $projectType = $isSoftwareFocused
            ? "sviluppo SOFTWARE/FIRMWARE"
            : "progettazione e sviluppo HARDWARE/PCB (scheda elettronica)";

        return "Sei un esperto elettronico per Supernova Industries S.R.L.

Migliora questa descrizione di progetto di {$projectType} rendendola professionale e tecnica.

PROGETTO: {$projectName}

DESCRIZIONE:
{$currentDescription}

{$contextInfo}

IMPORTANTE: Questo è un progetto di {$projectType}. Concentrati su aspetti tecnici hardware (PCB, componenti, circuiti, alimentazione, sensori, connettori) " . ($isSoftwareFocused ? "e sviluppo firmware/software embedded." : "e specifiche elettroniche.") . "

Scrivi una descrizione professionale in italiano (2-3 paragrafi). Mantieni tutti i dettagli tecnici, usa terminologia del settore elettronico, evidenzia specifiche chiave e benefici.

Rispondi SOLO con la descrizione migliorata:";
    }

    /**
     * Build prompt for milestone description improvement.
     */
    private function buildMilestoneDescriptionPrompt(string $milestoneName, string $projectName, string $currentDescription, array $context): string
    {
        $contextInfo = '';
        if (!empty($context['deadline'])) {
            $contextInfo .= "Scadenza: {$context['deadline']}\n";
        }
        if (!empty($context['project_phase'])) {
            $contextInfo .= "Fase progetto: {$context['project_phase']}\n";
        }

        return "Sei un assistente esperto nel settore elettronico che lavora per Supernova Industries S.R.L.

Devi migliorare la descrizione di una milestone di progetto elettronico rendendola più chiara e professionale.

PROGETTO: {$projectName}
MILESTONE: {$milestoneName}

DESCRIZIONE ATTUALE:
{$currentDescription}

CONTESTO:
{$contextInfo}

ISTRUZIONI:
1. Descrivi chiaramente cosa deve essere completato in questa fase
2. Includi criteri di accettazione se appropriati
3. Mantieni un linguaggio tecnico ma comprensibile
4. Limita la risposta a un paragrafo
5. Scrivi in italiano professionale

Fornisci SOLO la descrizione migliorata della milestone, senza commenti aggiuntivi.";
    }

    /**
     * Build prompt for email notification content.
     */
    private function buildEmailNotificationPrompt(string $projectName, string $clientName, \DateTime $deadline, array $context): string
    {
        $daysLeft = now()->diffInDays($deadline, false);
        $urgency = $daysLeft <= 3 ? 'URGENTE' : ($daysLeft <= 7 ? 'ATTENZIONE' : 'PROMEMORIA');

        return "Sei un assistente che scrive email professionali per Supernova Industries S.R.L.

Devi scrivere un'email di notifica per un progetto in scadenza.

DETTAGLI:
- Cliente: {$clientName}
- Progetto: {$projectName}
- Scadenza: {$deadline->format('d/m/Y')}
- Giorni rimanenti: {$daysLeft}
- Urgenza: {$urgency}

ISTRUZIONI:
1. Scrivi un'email professionale in italiano
2. Includi oggetto dell'email
3. Mantieni un tono cortese ma professionale
4. Sottolinea l'importanza del rispetto delle scadenze
5. Offri supporto se necessario
6. Firma come Alessandro Cursoli, Supernova Industries S.R.L.

FORMATO:
OGGETTO: [oggetto email]

CORPO:
[corpo dell'email]

Fornisci SOLO l'email formattata come richiesto.";
    }

    /**
     * Build prompt for project milestones generation.
     */
    private function buildProjectMilestonesPrompt(string $projectName, string $projectDescription, array $context): string
    {
        $contextInfo = '';
        if (!empty($context['customer'])) {
            $contextInfo .= "Cliente: {$context['customer']}\n";
        }
        if (!empty($context['budget'])) {
            $contextInfo .= "Budget: €" . number_format($context['budget'], 2) . "\n";
        }
        if (!empty($context['quotation'])) {
            $contextInfo .= "{$context['quotation']}\n";
        }
        if (!empty($context['boards_count'])) {
            $contextInfo .= "Numero schede da produrre: {$context['boards_count']}\n";
        }
        if (!empty($context['due_date'])) {
            $dueDate = $context['due_date'] instanceof \DateTime ? $context['due_date']->format('d/m/Y') : $context['due_date'];
            $contextInfo .= "Scadenza: {$dueDate}\n";
        }
        if (!empty($context['start_date'])) {
            $startDate = $context['start_date'] instanceof \DateTime ? $context['start_date']->format('d/m/Y') : $context['start_date'];
            $contextInfo .= "Data inizio: {$startDate}\n";
        }

        // Detect if this is software/firmware focused (default is hardware/PCB)
        $isSoftwareFocused = $this->isSoftwareProject($projectName, $projectDescription);

        $focusNote = $isSoftwareFocused
            ? "FOCUS: Questo progetto include sviluppo SOFTWARE/FIRMWARE. Includi milestone per sviluppo firmware, testing software, deployment."
            : "FOCUS: Questo progetto è primariamente HARDWARE/PCB. Concentrati su design schematico, PCB layout, selezione componenti, assemblaggio, test hardware.";

        return "Sei un esperto di gestione progetti nel settore elettronico e automazione industriale per Supernova Industries S.R.L.

Genera milestone realistiche per un progetto elettronico.

PROGETTO: {$projectName}

DESCRIZIONE:
{$projectDescription}

CONTESTO:
{$contextInfo}

{$focusNote}

ISTRUZIONI:
1. Analizza tipo progetto (PCB design, firmware, assemblaggio, prototipazione)
2. Genera milestone seguendo il flusso dello sviluppo elettronico HARDWARE
3. Usa categorie: design, prototyping, testing, production, delivery, documentation
4. Per ogni milestone:
   - Nome chiaro (max 50 caratteri)
   - Descrizione dettagliata (2-3 frasi)
   - Categoria appropriata
   - Offset giorni realistico
   - Sort order progressivo

FASI TIPICHE HARDWARE/PCB:
- Design: Schema elettrico, selezione componenti (5-10gg)
- PCB Layout: Design PCB, DRC, Gerber (7-14gg)
- Prototyping: Ordine PCB, assemblaggio prototipo (10-20gg)
- Testing: Test funzionali, debug, validazione (5-10gg)
- Production: Produzione serie, assemblaggio (variabile)
- Delivery: Consegna, documentazione (3-7gg)

FORMATO RISPOSTA - RESTITUISCI SOLO JSON VALIDO:
```json
[
    {
        \"name\": \"Progettazione Schema Elettrico\",
        \"description\": \"Definizione dell'architettura elettrica, selezione componenti chiave e design dello schema. Verifica compatibilità e creazione netlist.\",
        \"category\": \"design\",
        \"deadline_offset_days\": 7,
        \"sort_order\": 1
    },
    {
        \"name\": \"Design PCB Layout\",
        \"description\": \"Realizzazione del layout PCB con posizionamento ottimizzato dei componenti. Routing tracce, verifica DRC e generazione file Gerber.\",
        \"category\": \"design\",
        \"deadline_offset_days\": 14,
        \"sort_order\": 2
    }
]
```

IMPORTANTE:
- Genera 4-8 milestone appropriate al progetto
- Usa offset giorni realistici per l'elettronica
- Descrizioni in italiano professionale
- Restituisci SOLO l'array JSON, nessun testo aggiuntivo
- JSON deve essere valido e parsabile";
    }

    /**
     * Parse milestones from Ollama JSON response.
     */
    private function parseMilestonesFromResponse(string $response): array
    {
        // Remove markdown code blocks if present
        $cleanResponse = preg_replace('/^```json\\s*/m', '', $response);
        $cleanResponse = preg_replace('/\\s*```$/m', '', $cleanResponse);
        $cleanResponse = trim($cleanResponse);

        // Extract JSON array - find first [ and last ]
        // Ollama often adds explanatory text before the JSON
        $firstBracket = strpos($cleanResponse, '[');
        $lastBracket = strrpos($cleanResponse, ']');

        if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
            $cleanResponse = substr($cleanResponse, $firstBracket, $lastBracket - $firstBracket + 1);
        }

        try {
            $milestones = json_decode($cleanResponse, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($milestones)) {
                throw new \Exception('Response is not an array');
            }

            // Validate and sanitize each milestone
            $validatedMilestones = [];
            foreach ($milestones as $index => $milestone) {
                if (!isset($milestone['name']) || !isset($milestone['description']) || !isset($milestone['category'])) {
                    Log::warning('Invalid milestone data', ['milestone' => $milestone]);
                    continue;
                }

                $validatedMilestones[] = [
                    'name' => substr($milestone['name'], 0, 255),
                    'description' => $milestone['description'],
                    'category' => $this->normalizeMilestoneCategory($milestone['category']),
                    'deadline_offset_days' => (int)($milestone['deadline_offset_days'] ?? (($index + 1) * 7)),
                    'sort_order' => (int)($milestone['sort_order'] ?? ($index + 1)),
                ];
            }

            return $validatedMilestones;
        } catch (\JsonException $e) {
            Log::error('Failed to parse milestones JSON from Ollama', [
                'error' => $e->getMessage(),
                'response' => $cleanResponse
            ]);

            return [];
        }
    }

    /**
     * Normalize milestone category to valid values.
     */
    private function normalizeMilestoneCategory(string $category): string
    {
        $validCategories = ['design', 'prototyping', 'testing', 'production', 'delivery', 'documentation'];

        $normalized = strtolower(trim($category));

        if (in_array($normalized, $validCategories)) {
            return $normalized;
        }

        // Try to match common variations
        $categoryMap = [
            'prototype' => 'prototyping',
            'test' => 'testing',
            'prod' => 'production',
            'manufacturing' => 'production',
            'docs' => 'documentation',
            'documentation' => 'documentation',
            'consegna' => 'delivery',
        ];

        return $categoryMap[$normalized] ?? 'design';
    }

    /**
     * Detect if project is primarily software/firmware focused.
     * Default is hardware/PCB unless explicitly mentioned.
     */
    private function isSoftwareProject(string $projectName, string $description): bool
    {
        $text = strtolower($projectName . ' ' . $description);

        // Software/firmware keywords
        $softwareKeywords = [
            'software',
            'firmware',
            'app ',
            'applicazione',
            'applicativo',
            'web app',
            'mobile app',
            'backend',
            'frontend',
            'api',
            'database',
            'cloud',
            'server',
            'interfaccia grafica',
            'gui',
            'ui',
            'dashboard software',
            'programmazione',
            'codice',
            'sviluppo sw',
            'sviluppo software',
        ];

        foreach ($softwareKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make request to Ollama API.
     */
    private function makeRequest(string $prompt, int $maxTokens = 1000): ?string
    {
        try {
            $response = Http::timeout(60)->post("{$this->apiUrl}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'num_predict' => $maxTokens,
                    'temperature' => 0.7,
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['response'])) {
                    $result = trim($data['response']);

                    Log::info('Ollama AI request successful', [
                        'prompt_length' => strlen($prompt),
                        'response_length' => strlen($result),
                        'model' => $this->model
                    ]);

                    return $result;
                }
            }

            Log::error('Ollama AI request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Ollama AI service error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
