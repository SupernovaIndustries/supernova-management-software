<?php

namespace App\Services;

use App\Contracts\AiServiceInterface;
use App\Models\CompanyProfile;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAiService implements AiServiceInterface
{
    private CompanyProfile $profile;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->profile = CompanyProfile::current();
    }

    /**
     * Check if Claude AI is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->profile->isClaudeEnabled();
    }

    /**
     * Improve project description using Claude AI.
     */
    public function improveProjectDescription(string $projectName, string $currentDescription = '', array $context = []): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $prompt = $this->buildProjectDescriptionPrompt($projectName, $currentDescription, $context);
        
        return $this->makeRequest($prompt);
    }

    /**
     * Improve milestone description using Claude AI.
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

            // Parse JSON response from Claude
            $milestones = $this->parseMilestonesFromResponse($response);

            Log::info('Claude AI generated milestones', [
                'project' => $projectName,
                'count' => count($milestones)
            ]);

            return $milestones;
        } catch (\Exception $e) {
            Log::error('Failed to generate milestones with Claude AI', [
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
     * Generate user manual content using Claude AI.
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
     * Test Claude AI connection.
     */
    public function testConnection(): array
    {
        try {
            // Test diretto senza il wrapper makeRequest per debug
            $response = Http::withHeaders([
                'x-api-key' => $this->profile->claude_api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
                'model' => $this->profile->claude_model,
                'max_tokens' => 100,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Rispondi semplicemente 'Test connessione Claude AI riuscito' in italiano."
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['content'][0]['text'])) {
                    return [
                        'success' => true,
                        'message' => 'Connessione riuscita',
                        'response' => trim($data['content'][0]['text'])
                    ];
                } else {
                    Log::error('Claude AI response structure unexpected', [
                        'response' => $data
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Risposta Claude AI in formato non previsto'
                    ];
                }
            }

            Log::error('Claude AI test failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return [
                'success' => false,
                'message' => 'Errore API Claude: ' . $response->status() . ' - ' . substr($response->body(), 0, 200)
            ];
        } catch (\Exception $e) {
            Log::error('Claude AI test exception', [
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

DESCRIZIONE ATTUALE:
{$currentDescription}

CONTESTO:
{$contextInfo}

IMPORTANTE: Questo è un progetto di {$projectType}. Concentrati su aspetti tecnici hardware (PCB, componenti, circuiti, alimentazione, sensori, connettori) " . ($isSoftwareFocused ? "e sviluppo firmware/software embedded." : "e specifiche elettroniche.") . "

ISTRUZIONI:
1. Mantieni un tono professionale ma accessibile
2. Includi tutti i dettagli tecnici hardware presenti
3. Sottolinea i benefici per il cliente
4. Usa terminologia del settore elettronico/automazione
5. Limita la risposta a 2-3 paragrafi massimo
6. Scrivi in italiano professionale

Fornisci SOLO la descrizione migliorata, senza commenti aggiuntivi.";
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

Devi generare una lista di milestone realistiche e professionali per un progetto elettronico basandoti sulla descrizione fornita.

PROGETTO: {$projectName}

DESCRIZIONE:
{$projectDescription}

CONTESTO:
{$contextInfo}

{$focusNote}

ISTRUZIONI:
1. Analizza il tipo di progetto (PCB design, firmware, assemblaggio, prototipazione, etc.)
2. Genera milestone seguendo il flusso logico dello sviluppo elettronico HARDWARE
3. Usa queste categorie: design, prototyping, testing, production, delivery, documentation
4. Per ogni milestone fornisci:
   - Nome chiaro e professionale (max 50 caratteri)
   - Descrizione dettagliata (2-3 frasi) di cosa viene completato
   - Categoria appropriata
   - Offset giorni dalla data inizio progetto (realistico per elettronica)
   - Sort order (numero progressivo)

FASI TIPICHE PER PROGETTI HARDWARE/PCB:
- Design: Schema elettrico, selezione componenti (5-10 giorni)
- PCB Layout: Design PCB, verifica DRC (7-14 giorni)
- Prototyping: Ordine PCB, assemblaggio prototipo (10-20 giorni)
- Testing: Test funzionali, debug, validazione (5-10 giorni)
- Production: Produzione serie, assemblaggio (variabile)
- Delivery: Consegna, documentazione finale (3-7 giorni)

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
     * Parse milestones from Claude AI JSON response.
     */
    private function parseMilestonesFromResponse(string $response): array
    {
        // Remove markdown code blocks if present
        $cleanResponse = preg_replace('/^```json\s*/m', '', $response);
        $cleanResponse = preg_replace('/\s*```$/m', '', $cleanResponse);
        $cleanResponse = trim($cleanResponse);

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
            Log::error('Failed to parse milestones JSON', [
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
     * Make request to Claude AI API.
     */
    private function makeRequest(string $prompt, int $maxTokens = 1000): ?string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->profile->claude_api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
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
                    $result = trim($data['content'][0]['text']);
                    
                    Log::info('Claude AI request successful', [
                        'prompt_length' => strlen($prompt),
                        'response_length' => strlen($result)
                    ]);
                    
                    return $result;
                }
            }

            Log::error('Claude AI request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Claude AI service error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}