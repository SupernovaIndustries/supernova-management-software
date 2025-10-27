<?php

namespace App\Services;

use App\Models\CustomerContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContractReviewService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.anthropic.com/v1/messages';
    protected string $model = 'claude-3-5-sonnet-20241022';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
    }

    /**
     * Review a contract using AI
     */
    public function reviewContract(CustomerContract $contract): array
    {
        try {
            // Get contract text
            $contractText = $this->getContractText($contract);

            if (empty($contractText)) {
                throw new \Exception('Il contratto non contiene testo da analizzare. Compilare il campo "Termini e Condizioni".');
            }

            // Get checklist based on contract type
            $checklist = $this->getChecklistForType($contract->type);

            // Build prompt for AI
            $prompt = $this->buildReviewPrompt($contract, $contractText, $checklist);

            // Call Anthropic API
            $response = $this->callAnthropicApi($prompt);

            // Parse and structure the response
            $reviewData = $this->parseAiResponse($response, $checklist);

            // Calculate score and issues count
            $score = $this->calculateScore($reviewData);
            $issuesCount = $this->countIssues($reviewData);

            return [
                'success' => true,
                'review_data' => $reviewData,
                'score' => $score,
                'issues_count' => $issuesCount,
                'reviewed_at' => now(),
            ];
        } catch (\Exception $e) {
            Log::error('Contract review error: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get contract text from terms field
     */
    protected function getContractText(CustomerContract $contract): string
    {
        // Strip HTML tags from RichEditor field
        return strip_tags($contract->terms ?? '');
    }

    /**
     * Get checklist items based on contract type
     */
    public function getChecklistForType(string $type): array
    {
        $baseChecklist = [
            'parties_identified' => [
                'label' => 'Parti Identificate',
                'description' => 'Le parti contrattuali devono essere chiaramente identificate con denominazione sociale, sede legale, P.IVA/CF, rappresentante legale',
                'required' => true,
                'severity' => 'critical',
            ],
            'clear_dates' => [
                'label' => 'Date Chiare',
                'description' => 'Data di decorrenza, durata, eventuali termini di scadenza devono essere specificati',
                'required' => true,
                'severity' => 'high',
            ],
            'object_defined' => [
                'label' => 'Oggetto Definito',
                'description' => 'L\'oggetto del contratto deve essere descritto in modo chiaro e completo',
                'required' => true,
                'severity' => 'critical',
            ],
            'signatures' => [
                'label' => 'Firme e Sottoscrizioni',
                'description' => 'Clausola sulla modalità di firma e validità delle sottoscrizioni',
                'required' => true,
                'severity' => 'high',
            ],
            'competent_court' => [
                'label' => 'Foro Competente',
                'description' => 'Indicazione del foro competente per controversie',
                'required' => true,
                'severity' => 'medium',
            ],
            'applicable_law' => [
                'label' => 'Legge Applicabile',
                'description' => 'Indicazione della legge applicabile (es. legge italiana)',
                'required' => true,
                'severity' => 'medium',
            ],
            'gdpr_compliance' => [
                'label' => 'Conformità GDPR',
                'description' => 'Riferimento al trattamento dati personali secondo GDPR (Reg. UE 2016/679)',
                'required' => true,
                'severity' => 'critical',
            ],
        ];

        $typeSpecificChecklists = [
            'nda' => [
                'confidential_info' => [
                    'label' => 'Informazioni Confidenziali Definite',
                    'description' => 'Definizione chiara di cosa costituisce informazione confidenziale',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'exclusions' => [
                    'label' => 'Esclusioni dalla Confidenzialità',
                    'description' => 'Informazioni già pubbliche, già note, sviluppate indipendentemente',
                    'required' => true,
                    'severity' => 'high',
                ],
                'duration' => [
                    'label' => 'Durata dell\'Obbligo',
                    'description' => 'Periodo di validità dell\'obbligo di riservatezza',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'return_obligation' => [
                    'label' => 'Obbligo di Restituzione',
                    'description' => 'Obbligo di restituire o distruggere le informazioni confidenziali',
                    'required' => true,
                    'severity' => 'medium',
                ],
                'authorized_use' => [
                    'label' => 'Uso Autorizzato',
                    'description' => 'Scopo per cui le informazioni possono essere utilizzate',
                    'required' => true,
                    'severity' => 'high',
                ],
                'penalties' => [
                    'label' => 'Penali per Violazione',
                    'description' => 'Conseguenze e penali in caso di violazione della confidenzialità',
                    'required' => false,
                    'severity' => 'medium',
                ],
            ],
            'service_agreement' => [
                'sla_defined' => [
                    'label' => 'SLA Definiti',
                    'description' => 'Service Level Agreement con metriche, tempi di risposta, disponibilità',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'responsibilities' => [
                    'label' => 'Responsabilità delle Parti',
                    'description' => 'Obblighi e responsabilità di fornitore e cliente',
                    'required' => true,
                    'severity' => 'high',
                ],
                'warranty' => [
                    'label' => 'Garanzie',
                    'description' => 'Garanzie sui servizi forniti e limitazioni',
                    'required' => true,
                    'severity' => 'high',
                ],
                'termination' => [
                    'label' => 'Clausole di Risoluzione',
                    'description' => 'Condizioni e modalità di risoluzione del contratto',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'ip_rights' => [
                    'label' => 'Diritti di Proprietà Intellettuale',
                    'description' => 'Proprietà di software, documentazione, deliverable',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'payment_terms' => [
                    'label' => 'Termini di Pagamento',
                    'description' => 'Modalità, tempi, fatturazione, penali ritardo',
                    'required' => true,
                    'severity' => 'high',
                ],
                'liability_limit' => [
                    'label' => 'Limitazione di Responsabilità',
                    'description' => 'Limiti di responsabilità contrattuale ed extracontrattuale',
                    'required' => true,
                    'severity' => 'high',
                ],
                'support_maintenance' => [
                    'label' => 'Supporto e Manutenzione',
                    'description' => 'Modalità di assistenza, aggiornamenti, manutenzione',
                    'required' => false,
                    'severity' => 'medium',
                ],
            ],
            'supply_contract' => [
                'product_specs' => [
                    'label' => 'Specifiche Prodotto',
                    'description' => 'Descrizione dettagliata dei prodotti, quantità, specifiche tecniche',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'delivery_terms' => [
                    'label' => 'Termini di Consegna',
                    'description' => 'Modalità, tempi, luogo di consegna, Incoterms',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'quality_standards' => [
                    'label' => 'Standard di Qualità',
                    'description' => 'Certificazioni, test di accettazione, controlli qualità',
                    'required' => true,
                    'severity' => 'high',
                ],
                'defects_warranty' => [
                    'label' => 'Garanzia Difetti',
                    'description' => 'Garanzia per vizi, difetti, non conformità (art. 1490 c.c.)',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'payment_terms' => [
                    'label' => 'Termini di Pagamento',
                    'description' => 'Prezzo, modalità pagamento, acconti, saldo',
                    'required' => true,
                    'severity' => 'high',
                ],
                'force_majeure' => [
                    'label' => 'Forza Maggiore',
                    'description' => 'Clausola per eventi imprevedibili e inevitabili',
                    'required' => true,
                    'severity' => 'medium',
                ],
                'retention_title' => [
                    'label' => 'Riserva di Proprietà',
                    'description' => 'Clausola di riserva della proprietà fino al pagamento',
                    'required' => false,
                    'severity' => 'medium',
                ],
                'returns_claims' => [
                    'label' => 'Resi e Reclami',
                    'description' => 'Modalità di gestione resi, contestazioni, reclami',
                    'required' => true,
                    'severity' => 'high',
                ],
            ],
            'partnership' => [
                'governance' => [
                    'label' => 'Governance',
                    'description' => 'Struttura decisionale, diritti di voto, maggioranze',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'profit_sharing' => [
                    'label' => 'Ripartizione Utili/Perdite',
                    'description' => 'Modalità di distribuzione utili e ripartizione perdite',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'ip_ownership' => [
                    'label' => 'Proprietà Intellettuale',
                    'description' => 'Proprietà di IP sviluppata congiuntamente, licensing',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'non_compete' => [
                    'label' => 'Non Concorrenza',
                    'description' => 'Clausola di non concorrenza durante e dopo la partnership',
                    'required' => true,
                    'severity' => 'high',
                ],
                'exit_strategy' => [
                    'label' => 'Strategia di Uscita',
                    'description' => 'Modalità di recesso, scioglimento, diritto di prelazione',
                    'required' => true,
                    'severity' => 'critical',
                ],
                'contributions' => [
                    'label' => 'Contributi delle Parti',
                    'description' => 'Contributi economici, risorse, competenze di ciascuna parte',
                    'required' => true,
                    'severity' => 'high',
                ],
                'decision_making' => [
                    'label' => 'Processo Decisionale',
                    'description' => 'Decisioni ordinarie vs straordinarie, unanimità vs maggioranza',
                    'required' => true,
                    'severity' => 'high',
                ],
                'deadlock' => [
                    'label' => 'Gestione Stallo',
                    'description' => 'Meccanismo per risolvere situazioni di stallo decisionale',
                    'required' => false,
                    'severity' => 'medium',
                ],
            ],
        ];

        // Merge base checklist with type-specific
        return array_merge(
            $baseChecklist,
            $typeSpecificChecklists[$type] ?? []
        );
    }

    /**
     * Build the review prompt for AI
     */
    protected function buildReviewPrompt(CustomerContract $contract, string $contractText, array $checklist): string
    {
        $typeLabels = [
            'nda' => 'NDA (Non-Disclosure Agreement)',
            'service_agreement' => 'Contratto di Servizio',
            'supply_contract' => 'Contratto di Fornitura',
            'partnership' => 'Partnership',
        ];

        $checklistText = '';
        foreach ($checklist as $key => $item) {
            $required = $item['required'] ? 'OBBLIGATORIO' : 'OPZIONALE';
            $checklistText .= "\n- {$item['label']} ({$required}, gravità: {$item['severity']}): {$item['description']}";
        }

        return <<<PROMPT
Sei un esperto legale specializzato in diritto contrattuale italiano. Devi analizzare il seguente contratto e fornire una revisione dettagliata.

**INFORMAZIONI CONTRATTO:**
- Tipo: {$typeLabels[$contract->type]}
- Titolo: {$contract->title}
- Cliente: {$contract->customer->company_name}

**CHECKLIST DI CONTROLLO:**
{$checklistText}

**TESTO DEL CONTRATTO:**
{$contractText}

**ISTRUZIONI:**
1. Analizza il contratto verificando tutti i punti della checklist
2. Per ogni punto indica:
   - status: "present" (presente e corretto), "needs_improvement" (presente ma migliorabile), "missing" (mancante)
   - comment: breve commento sulla situazione attuale
   - suggestion: se necessario, suggerimento specifico per migliorare o cosa aggiungere
   - risk_level: "none", "low", "medium", "high", "critical" - livello di rischio se il punto è carente

3. Identifica RISCHI LEGALI specifici (es. clausole vessatorie, violazioni normative, ambiguità pericolose)

4. Verifica COMPLIANCE con normativa italiana:
   - GDPR (Reg. UE 2016/679)
   - Codice Civile (artt. 1321-1469, 1490-1495, 1571-1654)
   - Codice del Consumo (D.Lgs. 206/2005) se applicabile
   - Clausole vessatorie (artt. 1341-1342 c.c.)

5. Fornisci SUGGERIMENTI DI MIGLIORAMENTO con esempi di testo concreti

**FORMATO RISPOSTA (JSON):**
```json
{
  "checklist_results": {
    "nome_punto": {
      "status": "present|needs_improvement|missing",
      "comment": "breve commento",
      "suggestion": "suggerimento specifico",
      "suggested_text": "testo da aggiungere/modificare (se applicabile)",
      "risk_level": "none|low|medium|high|critical"
    }
  },
  "legal_risks": [
    {
      "title": "Titolo rischio",
      "description": "Descrizione dettagliata",
      "severity": "low|medium|high|critical",
      "recommendation": "Come mitigare"
    }
  ],
  "compliance_issues": [
    {
      "regulation": "Nome normativa",
      "issue": "Problema specifico",
      "article": "Articolo violato (se applicabile)",
      "severity": "low|medium|high|critical",
      "remedy": "Come risolvere"
    }
  ],
  "improvements": [
    {
      "area": "Area da migliorare",
      "current": "Situazione attuale",
      "suggested": "Testo suggerito",
      "priority": "low|medium|high"
    }
  ],
  "overall_assessment": {
    "quality": "Valutazione qualitativa generale",
    "strengths": ["punti di forza"],
    "weaknesses": ["punti deboli"],
    "readiness": "draft|needs_revision|ready_for_signature"
  }
}
```

Rispondi SOLO con il JSON, senza altro testo.
PROMPT;
    }

    /**
     * Call Anthropic API
     */
    protected function callAnthropicApi(string $prompt): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post($this->apiUrl, [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Errore chiamata API Anthropic: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['content'][0]['text'])) {
            throw new \Exception('Risposta API non valida');
        }

        // Extract JSON from response
        $text = $data['content'][0]['text'];

        // Try to extract JSON if wrapped in markdown code blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
            $jsonText = $matches[1];
        } else {
            $jsonText = $text;
        }

        $decoded = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Errore parsing JSON risposta AI: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Parse AI response and structure data
     */
    protected function parseAiResponse(array $response, array $checklist): array
    {
        return [
            'checklist_results' => $response['checklist_results'] ?? [],
            'legal_risks' => $response['legal_risks'] ?? [],
            'compliance_issues' => $response['compliance_issues'] ?? [],
            'improvements' => $response['improvements'] ?? [],
            'overall_assessment' => $response['overall_assessment'] ?? [
                'quality' => 'Non disponibile',
                'strengths' => [],
                'weaknesses' => [],
                'readiness' => 'needs_revision',
            ],
        ];
    }

    /**
     * Calculate overall score (0-100)
     */
    protected function calculateScore(array $reviewData): int
    {
        $checklistResults = $reviewData['checklist_results'] ?? [];

        if (empty($checklistResults)) {
            return 0;
        }

        $totalPoints = 0;
        $maxPoints = 0;

        foreach ($checklistResults as $key => $result) {
            $status = $result['status'] ?? 'missing';
            $riskLevel = $result['risk_level'] ?? 'medium';

            // Weight based on risk level
            $weight = match ($riskLevel) {
                'critical' => 10,
                'high' => 7,
                'medium' => 5,
                'low' => 3,
                'none' => 1,
                default => 5,
            };

            $maxPoints += $weight;

            // Points based on status
            if ($status === 'present') {
                $totalPoints += $weight;
            } elseif ($status === 'needs_improvement') {
                $totalPoints += $weight * 0.5;
            }
            // missing = 0 points
        }

        if ($maxPoints === 0) {
            return 0;
        }

        $score = (int) round(($totalPoints / $maxPoints) * 100);

        // Penalize for critical issues
        $legalRisks = $reviewData['legal_risks'] ?? [];
        $complianceIssues = $reviewData['compliance_issues'] ?? [];

        $criticalCount = 0;
        foreach (array_merge($legalRisks, $complianceIssues) as $issue) {
            if (($issue['severity'] ?? 'low') === 'critical') {
                $criticalCount++;
            }
        }

        $score -= ($criticalCount * 10);

        return max(0, min(100, $score));
    }

    /**
     * Count issues
     */
    protected function countIssues(array $reviewData): int
    {
        $count = 0;

        // Count checklist issues
        foreach ($reviewData['checklist_results'] ?? [] as $result) {
            if (in_array($result['status'] ?? '', ['missing', 'needs_improvement'])) {
                $count++;
            }
        }

        // Count legal risks
        $count += count($reviewData['legal_risks'] ?? []);

        // Count compliance issues
        $count += count($reviewData['compliance_issues'] ?? []);

        return $count;
    }

    /**
     * Apply suggestions to contract
     */
    public function applySuggestions(CustomerContract $contract, array $selectedSuggestions = []): string
    {
        $currentTerms = strip_tags($contract->terms ?? '');

        $improvementsText = "\n\n=== SUGGERIMENTI APPLICATI DALLA REVISIONE AI ===\n\n";

        $reviewData = $contract->ai_review_data;

        if (!empty($selectedSuggestions)) {
            // Apply only selected suggestions
            foreach ($selectedSuggestions as $key) {
                if (isset($reviewData['checklist_results'][$key]['suggested_text'])) {
                    $label = $this->getChecklistForType($contract->type)[$key]['label'] ?? $key;
                    $improvementsText .= "\n**{$label}:**\n";
                    $improvementsText .= $reviewData['checklist_results'][$key]['suggested_text'] . "\n";
                }
            }
        } else {
            // Apply all suggestions for missing or needs improvement items
            foreach ($reviewData['checklist_results'] ?? [] as $key => $result) {
                if (in_array($result['status'], ['missing', 'needs_improvement']) && !empty($result['suggested_text'])) {
                    $label = $this->getChecklistForType($contract->type)[$key]['label'] ?? $key;
                    $improvementsText .= "\n**{$label}:**\n";
                    $improvementsText .= $result['suggested_text'] . "\n";
                }
            }
        }

        return $currentTerms . $improvementsText;
    }

    /**
     * Get score color
     */
    public static function getScoreColor(int $score): string
    {
        return match (true) {
            $score >= 80 => 'success',
            $score >= 60 => 'warning',
            $score >= 40 => 'danger',
            default => 'danger',
        };
    }

    /**
     * Get status color
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'present' => 'success',
            'needs_improvement' => 'warning',
            'missing' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get severity color
     */
    public static function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'gray',
            'none' => 'success',
            default => 'gray',
        };
    }
}
