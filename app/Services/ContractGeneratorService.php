<?php

namespace App\Services;

use App\Models\CustomerContract;
use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ContractGeneratorService
{
    protected const API_URL = 'https://api.anthropic.com/v1/messages';
    protected const MAX_TOKENS = 4096;
    protected const RATE_LIMIT_DELAY = 1; // seconds

    /**
     * Generate contract draft using Claude AI
     *
     * @param CustomerContract $contract
     * @param array $options Additional options: special_clauses, duration_months, etc.
     * @return string HTML formatted contract text
     * @throws Exception
     */
    public function generateContractDraft(CustomerContract $contract, array $options = []): string
    {
        // Load relationships
        $contract->load('customer');

        // Get company profile
        $company = CompanyProfile::current();

        // Validate Claude is enabled
        if (!$company->isClaudeEnabled()) {
            throw new Exception('Claude AI non è configurato. Configurare API Key in Profilo Azienda.');
        }

        // Get the appropriate prompt based on contract type
        $prompt = $this->buildPrompt($contract, $company, $options);

        // Call Claude API
        $response = $this->callClaudeApi($prompt, $company);

        // Extract and format the response
        return $this->formatResponse($response);
    }

    /**
     * Build the prompt based on contract type
     *
     * @param CustomerContract $contract
     * @param CompanyProfile $company
     * @param array $options
     * @return string
     */
    protected function buildPrompt(CustomerContract $contract, CompanyProfile $company, array $options): string
    {
        $customer = $contract->customer;
        $specialClauses = $options['special_clauses'] ?? '';
        $durationMonths = $options['duration_months'] ?? null;

        // Common context for all contracts
        $context = "Sei un esperto legale italiano specializzato in contratti commerciali. Genera un contratto professionale in italiano, conforme alla legislazione italiana.\n\n";

        $context .= "INFORMAZIONI AZIENDA FORNITORE:\n";
        $context .= "- Ragione Sociale: {$company->company_name}\n";
        $context .= "- Sede Legale: {$company->legal_address}, {$company->legal_postal_code} {$company->legal_city} ({$company->legal_province})\n";
        $context .= "- Partita IVA: {$company->vat_number}\n";
        if ($company->tax_code) $context .= "- Codice Fiscale: {$company->tax_code}\n";
        if ($company->pec) $context .= "- PEC: {$company->pec}\n";
        if ($company->email) $context .= "- Email: {$company->email}\n";
        if ($company->phone) $context .= "- Telefono: {$company->phone}\n";

        $context .= "\nINFORMAZIONI CLIENTE:\n";
        $context .= "- Ragione Sociale: {$customer->company_name}\n";
        if ($customer->address) $context .= "- Sede: {$customer->address}, {$customer->postal_code} {$customer->city}\n";
        if ($customer->vat_number) $context .= "- Partita IVA: {$customer->vat_number}\n";
        if ($customer->tax_code) $context .= "- Codice Fiscale: {$customer->tax_code}\n";
        if ($customer->pec_email) $context .= "- PEC: {$customer->pec_email}\n";

        $context .= "\nTITOLO CONTRATTO: {$contract->title}\n";
        $context .= "DATA INIZIO: {$contract->start_date->format('d/m/Y')}\n";

        if ($contract->end_date) {
            $context .= "DATA FINE: {$contract->end_date->format('d/m/Y')}\n";
        } elseif ($durationMonths) {
            $context .= "DURATA: {$durationMonths} mesi\n";
        }

        if ($contract->contract_value) {
            $context .= "VALORE ECONOMICO: € " . number_format($contract->contract_value, 2, ',', '.') . "\n";
        }

        if ($specialClauses) {
            $context .= "\nCLAUSOLE SPECIALI RICHIESTE:\n{$specialClauses}\n";
        }

        // Type-specific prompts
        $typePrompts = [
            'nda' => $this->getNdaPrompt(),
            'service_agreement' => $this->getServiceAgreementPrompt(),
            'supply_contract' => $this->getSupplyContractPrompt(),
            'partnership' => $this->getPartnershipPrompt(),
        ];

        $specificPrompt = $typePrompts[$contract->type] ?? $this->getGenericPrompt();

        return $context . "\n" . $specificPrompt;
    }

    /**
     * NDA (Non-Disclosure Agreement) prompt
     */
    protected function getNdaPrompt(): string
    {
        return <<<PROMPT
Genera un ACCORDO DI RISERVATEZZA (NDA) professionale con i seguenti articoli obbligatori:

Art. 1 - PREMESSE E OGGETTO
- Descrivere il contesto della collaborazione
- Definire l'oggetto dell'accordo di riservatezza

Art. 2 - DEFINIZIONI
- Definire cosa si intende per "Informazioni Confidenziali"
- Specificare cosa include (know-how, documenti, progetti, processi, strategie commerciali, dati tecnici)
- Specificare le esclusioni (informazioni pubbliche, già note, sviluppate indipendentemente)

Art. 3 - OBBLIGHI DI RISERVATEZZA
- Impegno a mantenere riservate le informazioni
- Divieto di divulgazione a terzi senza consenso scritto
- Obbligo di proteggere con misure di sicurezza adeguate
- Limitazione dell'uso alle sole finalità dell'accordo
- Possibilità di condivisione solo con dipendenti/collaboratori vincolati a riservatezza

Art. 4 - DURATA DELL'OBBLIGO
- Durata dell'accordo (tipicamente 3-5 anni)
- Persistenza dell'obbligo anche dopo la scadenza per informazioni particolarmente sensibili

Art. 5 - RESTITUZIONE DELLE INFORMAZIONI
- Obbligo di restituire o distruggere le informazioni alla cessazione
- Modalità di restituzione/distruzione

Art. 6 - CONSEGUENZE DELLA VIOLAZIONE
- Risarcimento danni
- Clausola penale (se valore contratto specificato, proporre penale del 20-30%)
- Diritto a tutele cautelari e inibitorie

Art. 7 - LEGGE APPLICABILE E FORO COMPETENTE
- Legge italiana
- Foro competente: sede legale del fornitore

Art. 8 - DISPOSIZIONI FINALI
- Modifiche solo per iscritto
- Comunicazioni valide se inviate via PEC o raccomandata
- Clausola di salvaguardia

IMPORTANTE:
- Usa linguaggio formale e tecnico-giuridico
- Includi riferimenti alla normativa italiana sulla riservatezza
- Menzione GDPR se applicabile
- Formato: titolo articolo in grassetto, testo giustificato
- Output solo il contenuto degli articoli in formato HTML con tag <h3> per titoli articoli e <p> per paragrafi
PROMPT;
    }

    /**
     * Service Agreement prompt
     */
    protected function getServiceAgreementPrompt(): string
    {
        return <<<PROMPT
Genera un CONTRATTO DI SERVIZIO professionale con i seguenti articoli obbligatori:

Art. 1 - PREMESSE E OGGETTO
- Descrivere i servizi che il fornitore si impegna a prestare
- Ambito di applicazione del contratto

Art. 2 - DESCRIZIONE DEI SERVIZI
- Dettagliare i servizi di progettazione elettronica, sviluppo hardware/software, prototipazione
- Specifiche tecniche e standard qualitativi
- Metodologie di lavoro
- Deliverables attesi

Art. 3 - OBBLIGHI DEL FORNITORE
- Svolgere servizi con professionalità e competenza
- Rispettare tempistiche concordate
- Fornire documentazione tecnica completa
- Garantire qualità secondo standard di settore
- Assistenza post-consegna

Art. 4 - OBBLIGHI DEL CLIENTE
- Fornire informazioni e specifiche necessarie
- Collaborare attivamente
- Designare referente tecnico
- Pagamenti nei termini concordati
- Accettare/rifiutare deliverables entro termini stabiliti

Art. 5 - CORRISPETTIVO E MODALITÀ DI PAGAMENTO
- Importo totale del contratto
- Modalità di pagamento (esempio: 40% anticipo, 60% a completamento)
- Termini di pagamento (30/60 giorni data fattura)
- Fatturazione secondo normativa fiscale italiana
- Interessi di mora in caso di ritardo (D.Lgs. 231/2002)

Art. 6 - TEMPISTICHE E MILESTONE
- Durata del contratto
- Eventuali milestone intermedie
- Procedure di accettazione deliverables
- Gestione ritardi e proroghe

Art. 7 - PROPRIETÀ INTELLETTUALE
- Proprietà dei progetti, schemi, software, documentazione sviluppati
- Licenze d'uso concesse al cliente
- Diritti di modifica e utilizzo
- Protezione brevetti/know-how

Art. 8 - GARANZIE E RESPONSABILITÀ
- Garanzia sulla qualità dei servizi
- Periodo di garanzia (es. 12 mesi)
- Limitazioni di responsabilità
- Esclusioni garanzia per uso improprio

Art. 9 - RISERVATEZZA
- Obbligo di riservatezza reciproco
- Protezione informazioni confidenziali
- Riferimento a eventuale NDA separato

Art. 10 - RECESSO E RISOLUZIONE
- Condizioni di recesso
- Risoluzione per inadempimento
- Conseguenze della risoluzione
- Restituzione documentazione

Art. 11 - FORZA MAGGIORE
- Cause di forza maggiore
- Sospensione obblighi in caso di eventi imprevedibili

Art. 12 - MODIFICHE E VARIANTI
- Procedure per richieste di modifica
- Valutazione economica delle varianti
- Formalizzazione per iscritto

Art. 13 - LEGGE APPLICABILE E FORO COMPETENTE
- Legge italiana
- Foro competente esclusivo

Art. 14 - DISPOSIZIONI FINALI
- Comunicazioni valide
- Integrazioni e modifiche solo per iscritto
- Clausola di salvaguardia

IMPORTANTE:
- Usa linguaggio formale e tecnico-giuridico italiano
- Conformità al Codice Civile italiano (artt. 1655 ss. - contratto d'opera)
- Riferimenti normativi: D.Lgs. 231/2002 (ritardi pagamento), D.Lgs. 196/2003 e GDPR
- Output HTML con <h3> per articoli, <p> per paragrafi, <ul><li> per elenchi
PROMPT;
    }

    /**
     * Supply Contract prompt
     */
    protected function getSupplyContractPrompt(): string
    {
        return <<<PROMPT
Genera un CONTRATTO DI FORNITURA professionale con i seguenti articoli obbligatori:

Art. 1 - PREMESSE E OGGETTO
- Descrivere la fornitura di componenti elettronici, materiali, assemblati
- Ambito della fornitura

Art. 2 - CARATTERISTICHE DELLA FORNITURA
- Specifiche tecniche dei prodotti forniti
- Quantitativi (se applicabile)
- Standard di qualità
- Certificazioni richieste (CE, RoHS, REACH, ISO)
- Documentazione tecnica (datasheet, certificati)

Art. 3 - MODALITÀ DI FORNITURA E CONSEGNA
- Termini di consegna
- Luogo di consegna (Incoterms se applicabili: EXW, FCA, DAP)
- Modalità di trasporto
- Imballaggio e etichettatura
- Trasferimento rischio e proprietà

Art. 4 - CONTROLLO QUALITÀ E COLLAUDO
- Procedure di controllo qualità del fornitore
- Ispezioni in ingresso dal cliente
- Criteri di accettazione/rifiuto
- Gestione non conformità
- Campionamenti

Art. 5 - CORRISPETTIVO E CONDIZIONI DI PAGAMENTO
- Prezzo unitario o totale
- Valuta (EUR)
- Modalità pagamento
- Termini di pagamento
- Eventuali sconti per quantità
- Variazioni prezzi (se contratto lungo termine)

Art. 6 - GARANZIE
- Garanzia conformità alle specifiche
- Durata garanzia (es. 24 mesi)
- Garanzia contro difetti di fabbricazione
- Gestione prodotti difettosi
- Sostituzione/rimborso

Art. 7 - RESPONSABILITÀ
- Responsabilità fornitore per vizi e difetti
- Limitazioni responsabilità
- Coperture assicurative
- Esclusioni per uso improprio

Art. 8 - OBBLIGHI DEL FORNITORE
- Conformità normativa (CE, RoHS, REACH)
- Tracciabilità lotti
- Comunicazioni obsolescenza componenti
- Supporto tecnico
- Stoccaggio componenti

Art. 9 - OBBLIGHI DEL CLIENTE
- Ordini secondo procedure concordate
- Pagamenti puntuali
- Comunicazioni tempestive previsioni
- Gestione corretta dei prodotti

Art. 10 - DURATA E RINNOVO
- Durata contratto
- Rinnovo tacito o esplicito
- Quantitativi minimi (se applicabile)

Art. 11 - RISERVATEZZA
- Protezione informazioni tecniche e commerciali
- Obbligo riservatezza su specifiche, prezzi, condizioni

Art. 12 - RISOLUZIONE E RECESSO
- Condizioni di recesso
- Preavviso
- Risoluzione per inadempimento grave
- Conseguenze risoluzione

Art. 13 - FORZA MAGGIORE
- Eventi di forza maggiore
- Effetti su consegne e obblighi
- Comunicazioni in caso di impedimento

Art. 14 - MODIFICHE
- Modifiche tecniche
- Variazioni quantità
- Change orders

Art. 15 - LEGGE APPLICABILE E FORO COMPETENTE
- Legge italiana
- Foro esclusivo

Art. 16 - DISPOSIZIONI FINALI
- Comunicazioni
- Modifiche per iscritto
- Clausola salvaguardia

IMPORTANTE:
- Linguaggio tecnico-giuridico italiano
- Conformità Codice Civile (artt. 1470 ss. - vendita)
- Riferimenti normativi: D.Lgs. 206/2005 (Codice del Consumo se applicabile), normative CE, RoHS, REACH
- Output HTML con <h3>, <p>, <ul><li>
PROMPT;
    }

    /**
     * Partnership prompt
     */
    protected function getPartnershipPrompt(): string
    {
        return <<<PROMPT
Genera un ACCORDO DI PARTNERSHIP professionale con i seguenti articoli obbligatori:

Art. 1 - PREMESSE E FINALITÀ
- Descrivere obiettivi comuni della partnership
- Vision condivisa
- Settori di collaborazione

Art. 2 - OGGETTO DELLA PARTNERSHIP
- Ambiti di collaborazione (R&D, commerciale, produttivo)
- Progetti comuni
- Condivisione know-how e risorse

Art. 3 - RESPONSABILITÀ DELLE PARTI
- Ruolo e responsabilità del fornitore
- Ruolo e responsabilità del partner
- Risorse messe a disposizione da ciascuna parte
- Competenze e contributi

Art. 4 - GOVERNANCE E DECISIONI
- Modalità decisionali
- Riunioni periodiche
- Referenti per ciascuna parte
- Procedure di escalation

Art. 5 - ASPETTI ECONOMICI
- Ripartizione costi e investimenti
- Ripartizione ricavi e profitti
- Modalità di rendicontazione
- Fatturazione reciproca

Art. 6 - PROPRIETÀ INTELLETTUALE
- Proprietà sviluppi congiunti
- Licenze reciproche
- Diritti d'uso su IP preesistenti
- Protezione brevetti, marchi, know-how
- Utilizzo marchi e loghi

Art. 7 - RISERVATEZZA E NON CONCORRENZA
- Obblighi di riservatezza
- Patto di non concorrenza durante partnership
- Limitazioni post-cessazione
- Esclusività (se applicabile)

Art. 8 - COMUNICAZIONE E MARKETING
- Comunicati stampa congiunti
- Uso denominazioni e loghi
- Materiali marketing
- Eventi e fiere

Art. 9 - DURATA E RINNOVO
- Durata iniziale
- Rinnovo automatico o negoziato
- Obiettivi e milestone

Art. 10 - RECESSO E RISOLUZIONE
- Recesso con preavviso
- Risoluzione per inadempimento
- Conseguenze della cessazione
- Transizione e chiusura progetti in corso

Art. 11 - GARANZIE E RESPONSABILITÀ
- Garanzie reciproche
- Limitazioni responsabilità
- Indennizzi
- Assicurazioni

Art. 12 - FORZA MAGGIORE
- Eventi di forza maggiore
- Sospensione obblighi

Art. 13 - LEGGE APPLICABILE E CONTROVERSIE
- Legge italiana
- Risoluzione controversie (mediazione, arbitrato, foro)

Art. 14 - DISPOSIZIONI FINALI
- Modifiche per iscritto
- Comunicazioni
- Autonomia clausole
- Allegati parte integrante

IMPORTANTE:
- Linguaggio formale italiano
- Equilibrio diritti/doveri tra parti
- Conformità Codice Civile italiano
- Output HTML con <h3>, <p>, <ul><li>
PROMPT;
    }

    /**
     * Generic contract prompt fallback
     */
    protected function getGenericPrompt(): string
    {
        return <<<PROMPT
Genera un CONTRATTO professionale generico con i seguenti elementi:

1. PREMESSE E OGGETTO - descrivere finalità e oggetto contrattuale
2. OBBLIGHI DELLE PARTI - dettagliare reciproci impegni
3. ASPETTI ECONOMICI - corrispettivo, pagamenti, fatturazione
4. DURATA - periodo validità contratto
5. RISERVATEZZA - protezione informazioni riservate
6. RESPONSABILITÀ E GARANZIE - limitazioni e tutele
7. RISOLUZIONE - condizioni cessazione anticipata
8. LEGGE APPLICABILE E FORO - giurisdizione italiana

Usa linguaggio tecnico-giuridico italiano, conforme al Codice Civile.
Output HTML con <h3> per articoli, <p> per paragrafi.
PROMPT;
    }

    /**
     * Call Claude API
     *
     * @param string $prompt
     * @param CompanyProfile $company
     * @return string
     * @throws Exception
     */
    protected function callClaudeApi(string $prompt, CompanyProfile $company): string
    {
        try {
            // Rate limiting
            sleep(self::RATE_LIMIT_DELAY);

            $model = $company->claude_model ?? 'claude-3-5-sonnet-20241022';

            $response = Http::withHeaders([
                'x-api-key' => $company->claude_api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model' => $model,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            if ($response->failed()) {
                $error = $response->json('error.message', 'Unknown API error');
                Log::error('Claude API error', [
                    'status' => $response->status(),
                    'error' => $error,
                    'response' => $response->body(),
                ]);
                throw new Exception("Errore API Claude: {$error}");
            }

            $result = $response->json();

            if (!isset($result['content'][0]['text'])) {
                throw new Exception('Risposta API non valida: contenuto mancante');
            }

            return $result['content'][0]['text'];

        } catch (Exception $e) {
            Log::error('Error calling Claude API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception("Errore generazione contratto: {$e->getMessage()}");
        }
    }

    /**
     * Format the API response
     *
     * @param string $response
     * @return string
     */
    protected function formatResponse(string $response): string
    {
        // Claude returns HTML formatted text, ensure it's clean
        $formatted = trim($response);

        // Remove any markdown code blocks if present
        $formatted = preg_replace('/```html\s*/', '', $formatted);
        $formatted = preg_replace('/```\s*/', '', $formatted);

        // Ensure proper HTML structure
        if (!str_contains($formatted, '<h3>') && !str_contains($formatted, '<p>')) {
            // If response is plain text, wrap it in paragraphs
            $paragraphs = explode("\n\n", $formatted);
            $formatted = implode('', array_map(function($p) {
                return '<p>' . trim($p) . '</p>';
            }, $paragraphs));
        }

        return $formatted;
    }

    /**
     * Get estimated generation cost
     * Claude 3.5 Sonnet: $3/MTok input, $15/MTok output
     *
     * @param string $prompt
     * @param string $response
     * @return float
     */
    public function estimateCost(string $prompt, string $response): float
    {
        // Rough estimation: 1 token ≈ 4 characters
        $inputTokens = strlen($prompt) / 4;
        $outputTokens = strlen($response) / 4;

        $inputCost = ($inputTokens / 1000000) * 3;   // $3 per million tokens
        $outputCost = ($outputTokens / 1000000) * 15; // $15 per million tokens

        return round($inputCost + $outputCost, 4);
    }

    /**
     * Validate contract draft before saving
     *
     * @param string $draft
     * @return array Validation results
     */
    public function validateDraft(string $draft): array
    {
        $issues = [];

        // Check minimum length
        if (strlen($draft) < 500) {
            $issues[] = 'Il contratto generato sembra troppo breve';
        }

        // Check for HTML structure
        if (!str_contains($draft, '<h3>') && !str_contains($draft, '<p>')) {
            $issues[] = 'Il contratto non contiene la formattazione HTML attesa';
        }

        // Check for common Italian legal terms
        $legalTerms = ['fornitore', 'cliente', 'contratto', 'obblig', 'articolo', 'parte'];
        $foundTerms = 0;
        foreach ($legalTerms as $term) {
            if (stripos($draft, $term) !== false) {
                $foundTerms++;
            }
        }

        if ($foundTerms < 3) {
            $issues[] = 'Il contratto non contiene sufficienti termini legali italiani';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'length' => strlen($draft),
            'estimated_articles' => substr_count(strtolower($draft), 'art.'),
        ];
    }
}
