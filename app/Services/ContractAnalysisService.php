<?php

namespace App\Services;

use App\Models\CustomerContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class ContractAnalysisService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.anthropic.com/v1/messages';
    protected string $model = 'claude-3-5-sonnet-20241022';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));
    }

    /**
     * Analizza un contratto PDF usando Claude API
     *
     * @param CustomerContract $contract
     * @param string $pdfPath Path assoluto al file PDF
     * @return array
     * @throws \Exception
     */
    public function analyzeContractPdf(CustomerContract $contract, string $pdfPath): array
    {
        try {
            // Estrai testo dal PDF
            $pdfText = $this->extractPdfText($pdfPath);

            if (empty($pdfText)) {
                throw new \Exception('Impossibile estrarre testo dal PDF');
            }

            // Analizza con Claude API
            $analysisResult = $this->analyzeWithClaude($pdfText, $contract);

            // Prepara i dati strutturati
            return $this->structureAnalysisData($analysisResult, $contract);

        } catch (\Exception $e) {
            Log::error('Errore analisi contratto PDF', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Estrae il testo da un file PDF
     *
     * @param string $pdfPath
     * @return string
     */
    protected function extractPdfText(string $pdfPath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($pdfPath);
            $text = $pdf->getText();

            // Pulisci il testo da caratteri speciali e spazi multipli
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            return $text;

        } catch (\Exception $e) {
            Log::error('Errore estrazione testo PDF', [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Errore durante l\'estrazione del testo dal PDF: ' . $e->getMessage());
        }
    }

    /**
     * Analizza il testo del contratto usando Claude API
     *
     * @param string $contractText
     * @param CustomerContract $contract
     * @return array
     */
    protected function analyzeWithClaude(string $contractText, CustomerContract $contract): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('ANTHROPIC_API_KEY non configurata nel file .env');
        }

        // Tronca il testo se troppo lungo (massimo 100k caratteri per sicurezza)
        if (strlen($contractText) > 100000) {
            $contractText = substr($contractText, 0, 100000) . '... [testo troncato]';
        }

        $prompt = $this->buildAnalysisPrompt($contractText, $contract);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post($this->apiUrl, [
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
                throw new \Exception('Errore API Anthropic: ' . $response->body());
            }

            $data = $response->json();

            // Estrai il testo della risposta
            $analysisText = $data['content'][0]['text'] ?? '';

            // Parse della risposta JSON
            return $this->parseClaudeResponse($analysisText);

        } catch (\Exception $e) {
            Log::error('Errore chiamata API Claude', [
                'error' => $e->getMessage(),
                'contract_id' => $contract->id,
            ]);

            throw new \Exception('Errore durante l\'analisi AI: ' . $e->getMessage());
        }
    }

    /**
     * Costruisce il prompt per l'analisi del contratto
     *
     * @param string $contractText
     * @param CustomerContract $contract
     * @return string
     */
    protected function buildAnalysisPrompt(string $contractText, CustomerContract $contract): string
    {
        return <<<PROMPT
Sei un esperto legale specializzato nell'analisi di contratti commerciali. Analizza il seguente contratto e fornisci un'analisi strutturata in formato JSON.

**INFORMAZIONI CONTRATTO GIA' DISPONIBILI:**
- Numero: {$contract->contract_number}
- Titolo: {$contract->title}
- Tipo: {$contract->type}
- Cliente: {$contract->customer->company_name}

**TESTO DEL CONTRATTO:**
{$contractText}

**ISTRUZIONI:**
Analizza il contratto ed estrai le seguenti informazioni in formato JSON:

```json
{
  "parti_coinvolte": [
    {
      "nome": "Nome completo della parte",
      "ruolo": "committente/fornitore/altro",
      "dettagli": "Eventuali dettagli aggiuntivi (P.IVA, sede legale, etc.)"
    }
  ],
  "date_chiave": [
    {
      "data": "YYYY-MM-DD",
      "tipo": "inizio/scadenza/milestone/altro",
      "descrizione": "Descrizione dell'evento o milestone"
    }
  ],
  "importi": [
    {
      "valore": 0.00,
      "valuta": "EUR",
      "descrizione": "Descrizione dell'importo (compenso totale, acconto, etc.)",
      "tipo": "totale/acconto/rata/penale/altro"
    }
  ],
  "deliverable": [
    {
      "descrizione": "Descrizione del deliverable",
      "scadenza": "YYYY-MM-DD o null se non specificata",
      "note": "Eventuali note aggiuntive"
    }
  ],
  "clausole_rischiose": [
    {
      "tipo": "penale/garanzia/limitazione_responsabilita/esclusiva/altro",
      "gravita": "alta/media/bassa",
      "descrizione": "Descrizione della clausola",
      "testo_originale": "Estratto del testo originale della clausola",
      "raccomandazioni": "Suggerimenti per mitigare il rischio"
    }
  ],
  "obblighi_principali": [
    {
      "parte": "committente/fornitore",
      "descrizione": "Descrizione dell'obbligo",
      "scadenza": "YYYY-MM-DD o null se non applicabile"
    }
  ],
  "condizioni_pagamento": {
    "modalita": "bonifico/assegno/altro",
    "termini": "Descrizione dei termini di pagamento",
    "scadenze": ["YYYY-MM-DD"]
  },
  "rinnovo_automatico": {
    "presente": true/false,
    "condizioni": "Descrizione delle condizioni di rinnovo se presente"
  },
  "foro_competente": "Città e tribunale competente",
  "legge_applicabile": "Legge applicabile al contratto",
  "riassunto_generale": "Breve riassunto del contratto in 2-3 frasi",
  "note_analisi": "Eventuali note aggiuntive sull'analisi"
}
```

**IMPORTANTE:**
- Fornisci SOLO il JSON, senza altro testo prima o dopo
- Usa il formato ISO 8601 (YYYY-MM-DD) per tutte le date
- Se un'informazione non è presente nel contratto, usa null o array vuoto []
- Sii preciso e completo nell'identificare le clausole rischiose
- Estrai tutti gli importi menzionati, anche se non sono l'importo principale del contratto
- Identifica tutte le date rilevanti (inizio, fine, milestone, scadenze pagamenti, etc.)

Fornisci ora l'analisi in formato JSON:
PROMPT;
    }

    /**
     * Parse della risposta di Claude in formato JSON
     *
     * @param string $responseText
     * @return array
     */
    protected function parseClaudeResponse(string $responseText): array
    {
        // Rimuovi eventuali markdown code blocks
        $responseText = preg_replace('/```json\s*/', '', $responseText);
        $responseText = preg_replace('/```\s*$/', '', $responseText);
        $responseText = trim($responseText);

        try {
            $decoded = json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (\JsonException $e) {
            Log::error('Errore parsing risposta Claude', [
                'response' => $responseText,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Errore nel parsing della risposta AI: formato JSON non valido');
        }
    }

    /**
     * Struttura i dati dell'analisi per il salvataggio nel database
     *
     * @param array $analysisResult
     * @param CustomerContract $contract
     * @return array
     */
    protected function structureAnalysisData(array $analysisResult, CustomerContract $contract): array
    {
        return [
            'ai_analysis_data' => $analysisResult,
            'ai_extracted_parties' => $analysisResult['parti_coinvolte'] ?? [],
            'ai_risk_flags' => $analysisResult['clausole_rischiose'] ?? [],
            'ai_key_dates' => $analysisResult['date_chiave'] ?? [],
            'ai_analyzed_at' => now(),
        ];
    }

    /**
     * Verifica se un contratto può essere analizzato
     *
     * @param CustomerContract $contract
     * @return bool
     */
    public function canAnalyze(CustomerContract $contract): bool
    {
        return !empty($contract->nextcloud_path);
    }

    /**
     * Ottiene il path completo del PDF del contratto
     *
     * @param CustomerContract $contract
     * @return string|null
     */
    public function getContractPdfPath(CustomerContract $contract): ?string
    {
        if (empty($contract->nextcloud_path)) {
            return null;
        }

        // Se il path è già assoluto, usalo direttamente
        if (file_exists($contract->nextcloud_path)) {
            return $contract->nextcloud_path;
        }

        // Altrimenti prova a costruire il path usando i disks di storage
        $disks = ['syncthing_clients', 'syncthing_documents', 'local'];

        foreach ($disks as $disk) {
            try {
                if (Storage::disk($disk)->exists($contract->nextcloud_path)) {
                    return Storage::disk($disk)->path($contract->nextcloud_path);
                }
            } catch (\Exception $e) {
                // Continua con il prossimo disk
                continue;
            }
        }

        return null;
    }

    /**
     * Genera un riassunto testuale dell'analisi
     *
     * @param array $analysisData
     * @return string
     */
    public function generateAnalysisSummary(array $analysisData): string
    {
        $summary = [];

        // Parti coinvolte
        if (!empty($analysisData['ai_extracted_parties'])) {
            $partyCount = count($analysisData['ai_extracted_parties']);
            $summary[] = "Parti coinvolte: {$partyCount}";
        }

        // Date chiave
        if (!empty($analysisData['ai_key_dates'])) {
            $dateCount = count($analysisData['ai_key_dates']);
            $summary[] = "Date chiave identificate: {$dateCount}";
        }

        // Importi
        if (!empty($analysisData['ai_analysis_data']['importi'])) {
            $importCount = count($analysisData['ai_analysis_data']['importi']);
            $summary[] = "Importi estratti: {$importCount}";
        }

        // Rischi
        if (!empty($analysisData['ai_risk_flags'])) {
            $riskCount = count($analysisData['ai_risk_flags']);
            $highRiskCount = count(array_filter($analysisData['ai_risk_flags'], fn($r) => ($r['gravita'] ?? '') === 'alta'));

            if ($highRiskCount > 0) {
                $summary[] = "⚠️ ATTENZIONE: {$highRiskCount} clausole ad alto rischio identificate";
            } else {
                $summary[] = "Clausole rischiose identificate: {$riskCount}";
            }
        }

        return implode(' | ', $summary);
    }
}
