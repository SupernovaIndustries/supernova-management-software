# Sistema di Analisi Automatica Contratti PDF con AI

Implementazione completata del sistema di analisi automatica contratti usando Claude 3.5 Sonnet di Anthropic.

## Panoramica

Il sistema analizza automaticamente i PDF dei contratti ed estrae:
- **Parti coinvolte** (committente, fornitore, partner)
- **Date chiave** (inizio, scadenza, milestone, pagamenti)
- **Importi e condizioni di pagamento**
- **Deliverable e scadenze**
- **Clausole rischiose** (penali, garanzie, limitazioni responsabilità)
- **Obblighi principali** delle parti
- **Condizioni di rinnovo automatico**
- **Foro competente e legge applicabile**

## Files Creati/Modificati

### 1. Migration
**File**: `/Users/supernova/supernova-management/database/migrations/2025_10_06_175128_add_ai_analysis_fields_to_customer_contracts_table.php`

Aggiunge i seguenti campi al database `customer_contracts`:
- `ai_analysis_data` (json) - Dati analisi completa
- `ai_extracted_parties` (json) - Parti coinvolte
- `ai_risk_flags` (json) - Clausole rischiose
- `ai_key_dates` (json) - Date milestone
- `ai_analyzed_at` (timestamp) - Data analisi
- Index su `ai_analyzed_at`

**Status**: ✅ Migration eseguita con successo

### 2. Service - ContractAnalysisService
**File**: `/Users/supernova/supernova-management/app/Services/ContractAnalysisService.php`

Servizio principale per l'analisi AI:
- **`analyzeContractPdf()`** - Analizza un PDF e ritorna dati strutturati
- **`extractPdfText()`** - Estrae testo da PDF usando smalot/pdfparser
- **`analyzeWithClaude()`** - Chiama API Anthropic Claude 3.5 Sonnet
- **`buildAnalysisPrompt()`** - Genera prompt in italiano per Claude
- **`canAnalyze()`** - Verifica se un contratto può essere analizzato
- **`getContractPdfPath()`** - Ottiene path del PDF
- **`generateAnalysisSummary()`** - Genera riassunto testuale

### 3. Model - CustomerContract (aggiornato)
**File**: `/Users/supernova/supernova-management/app/Models/CustomerContract.php`

Modifiche:
- Aggiunti campi AI al `$fillable`
- Aggiunti cast per campi JSON e datetime
- Nuovi metodi helper:
  - `isAnalyzed()` - Verifica se analizzato
  - `hasHighRiskFlags()` - Verifica presenza rischi alti
  - `getRiskCountBySeverity()` - Conta rischi per gravità

### 4. Filament Resource (aggiornato)
**File**: `/Users/supernova/supernova-management/app/Filament/Resources/CustomerContractResource.php`

**Nuova Azione nella Table**:
- **"Analizza con AI"** - Pulsante sparkles icon
  - Visibile solo se `nextcloud_path` è presente
  - Mostra modal di conferma
  - Esegue analisi e salva risultati
  - Mostra notifica con riassunto

**Nuovo Tab nel Form**:
- **"Analisi AI"** - Visibile solo se contratto analizzato
  - Stato analisi e timestamp
  - Riepilogo rischi (alta/media/bassa)
  - Conteggio parti coinvolte
  - Conteggio date chiave
  - Liste dettagliate con custom views

### 5. Custom Blade Views
**Files**:
- `/Users/supernova/supernova-management/resources/views/filament/forms/components/contract-parties-list.blade.php`
- `/Users/supernova/supernova-management/resources/views/filament/forms/components/contract-dates-list.blade.php`
- `/Users/supernova/supernova-management/resources/views/filament/forms/components/contract-risks-list.blade.php`

Views personalizzate per visualizzare:
- Parti coinvolte con badge ruolo
- Date chiave con tipo (scadenza/milestone)
- Clausole rischiose con badge gravità (alta/media/bassa)
- Testo originale e raccomandazioni

### 6. Observer - CustomerContractObserver (aggiornato)
**File**: `/Users/supernova/supernova-management/app/Observers/CustomerContractObserver.php`

**Auto-trigger analisi quando**:
1. Campo `nextcloud_path` viene impostato per la prima volta
2. Status cambia da `draft` a `active`

Usa `updateQuietly()` per evitare loop infiniti.

### 7. Configurazione
**Files modificati**:
- `/Users/supernova/supernova-management/config/services.php` - Aggiunta sezione `anthropic`
- `/Users/supernova/supernova-management/.env.example` - Aggiunte variabili ANTHROPIC_*

## Configurazione Necessaria

### 1. Variabili Ambiente (.env)

Aggiungi al tuo `.env`:

```env
# Anthropic Claude API (per analisi contratti)
ANTHROPIC_API_KEY=sk-ant-api03-xxxxxxxxxxxxx
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
ANTHROPIC_MAX_TOKENS=4096
```

**IMPORTANTE**: Senza `ANTHROPIC_API_KEY` l'analisi non funzionerà!

Per ottenere la chiave API:
1. Vai su https://console.anthropic.com/
2. Crea un account o fai login
3. Vai su "API Keys"
4. Genera una nuova chiave
5. Copia e incolla nel `.env`

### 2. Dipendenze Installate

```bash
composer require smalot/pdfparser
```

**Status**: ✅ Già installato automaticamente

## Come Usare

### Metodo 1: Azione Manuale dalla Table

1. Vai su **Contratti Clienti** nel panel Filament
2. Trova un contratto che ha un PDF caricato (campo `nextcloud_path` presente)
3. Clicca sull'icona sparkles **"Analizza con AI"**
4. Conferma nel modal
5. Attendi l'analisi (può richiedere 10-30 secondi)
6. Riceverai notifica con riassunto

### Metodo 2: Auto-trigger Automatico

L'analisi viene eseguita automaticamente quando:

**Scenario A**: Upload nuovo PDF
```php
$contract->update(['nextcloud_path' => '/path/to/contract.pdf']);
// → Analisi automatica triggata
```

**Scenario B**: Attivazione contratto
```php
$contract->update(['status' => 'active']); // da 'draft'
// → Analisi automatica triggata
```

### Metodo 3: Programmazione Manuale

```php
use App\Services\ContractAnalysisService;

$service = app(ContractAnalysisService::class);
$contract = CustomerContract::find(1);

// Verifica se può essere analizzato
if ($service->canAnalyze($contract)) {
    $pdfPath = $service->getContractPdfPath($contract);

    // Esegui analisi
    $analysisData = $service->analyzeContractPdf($contract, $pdfPath);

    // Salva risultati
    $contract->update($analysisData);

    // Genera riassunto
    $summary = $service->generateAnalysisSummary($analysisData);
}
```

## Visualizzazione Risultati

### Nel Form di Editing

Dopo l'analisi, apparirà il tab **"Analisi AI"** con:

1. **Header Info**:
   - Data/ora analisi
   - Riepilogo rischi (conta per gravità)
   - Numero parti coinvolte
   - Numero date chiave

2. **Parti Coinvolte**:
   - Nome completo
   - Ruolo (committente/fornitore/altro)
   - Dettagli (P.IVA, sede, etc.)

3. **Date Chiave**:
   - Data formattata
   - Tipo (inizio/scadenza/milestone)
   - Descrizione evento

4. **Clausole Rischiose**:
   - Badge gravità (ALTA/MEDIA/BASSA)
   - Tipo clausola
   - Descrizione rischio
   - Testo originale estratto
   - Raccomandazioni AI

5. **Riassunto Generale**:
   - Sintesi contratto in 2-3 frasi

## Struttura Dati AI

### ai_analysis_data (JSON completo)
```json
{
  "parti_coinvolte": [...],
  "date_chiave": [...],
  "importi": [
    {
      "valore": 10000.00,
      "valuta": "EUR",
      "descrizione": "Compenso totale",
      "tipo": "totale"
    }
  ],
  "deliverable": [...],
  "clausole_rischiose": [...],
  "obblighi_principali": [...],
  "condizioni_pagamento": {
    "modalita": "bonifico",
    "termini": "30 giorni fine mese",
    "scadenze": ["2024-12-31"]
  },
  "rinnovo_automatico": {
    "presente": true,
    "condizioni": "Rinnovo tacito annuale"
  },
  "foro_competente": "Tribunale di Milano",
  "legge_applicabile": "Legge italiana",
  "riassunto_generale": "Contratto di servizio...",
  "note_analisi": "..."
}
```

### ai_extracted_parties (JSON)
```json
[
  {
    "nome": "Supernova Industries SRL",
    "ruolo": "fornitore",
    "dettagli": "P.IVA 12345678901, Sede legale: Milano"
  }
]
```

### ai_risk_flags (JSON)
```json
[
  {
    "tipo": "penale",
    "gravita": "alta",
    "descrizione": "Penale del 20% per ritardi oltre 15 giorni",
    "testo_originale": "In caso di ritardo...",
    "raccomandazioni": "Negoziare riduzione penale..."
  }
]
```

### ai_key_dates (JSON)
```json
[
  {
    "data": "2024-12-31",
    "tipo": "scadenza",
    "descrizione": "Termine consegna progetto finale"
  }
]
```

## Gestione Errori

Il sistema gestisce gracefully i seguenti errori:

1. **PDF non trovato**: Warning notification
2. **API key mancante**: Exception con messaggio chiaro
3. **Errore parsing PDF**: Log error, exception
4. **Errore API Claude**: Log error, notification
5. **JSON malformato**: Log error, exception

Tutti gli errori vengono loggati in `storage/logs/laravel.log` con dettagli completi.

## Performance e Costi

### Tempi di Esecuzione
- Estrazione PDF: 1-3 secondi
- Chiamata Claude API: 5-20 secondi
- Totale: **10-30 secondi** tipicamente

### Costi API Anthropic
- Claude 3.5 Sonnet: ~$3 per milione di token input, ~$15 per milione token output
- Contratto medio (10 pagine): ~10k token input, ~2k token output
- **Costo per analisi**: ~$0.03-0.05 USD

## Testing

### Test Manuale

1. Crea un contratto di test:
```php
$contract = CustomerContract::create([
    'customer_id' => 1,
    'title' => 'Test Contract',
    'type' => 'service_agreement',
    'status' => 'draft',
    'start_date' => now(),
    'nextcloud_path' => '/path/to/test.pdf',
]);
```

2. Trigger analisi manuale dal panel Filament

3. Verifica risultati nel tab "Analisi AI"

### Verifica Database

```sql
SELECT
    id,
    contract_number,
    ai_analyzed_at,
    ai_extracted_parties,
    ai_risk_flags,
    ai_key_dates
FROM customer_contracts
WHERE ai_analyzed_at IS NOT NULL;
```

## Troubleshooting

### Problema: "ANTHROPIC_API_KEY non configurata"
**Soluzione**: Aggiungi la chiave nel `.env` e riavvia server

### Problema: "PDF file not found"
**Soluzione**:
- Verifica che `nextcloud_path` sia corretto
- Controlla permessi file system
- Verifica configurazione storage disks in `config/filesystems.php`

### Problema: "Timeout durante analisi"
**Soluzione**:
- Aumenta timeout in `ContractAnalysisService` (attualmente 120s)
- Contratti molto lunghi potrebbero richiedere più tempo

### Problema: "Formato JSON non valido"
**Soluzione**:
- Claude potrebbe restituire JSON malformato
- Controlla log per vedere risposta raw
- Potrebbe essere necessario migliorare il prompt

## Limitazioni Attuali

1. **Solo PDF**: Supporta solo file PDF (no DOCX, ODT)
2. **Lingua**: Prompt ottimizzato per contratti in italiano
3. **Dimensione**: PDF molto grandi (>100 pagine) potrebbero essere troncati
4. **Precisione**: AI può sbagliare, verificare sempre manualmente dati critici
5. **Re-analisi**: Non prevista re-analisi automatica se contratto modificato

## Estensioni Future Possibili

- [ ] Supporto altri formati (DOCX, ODT)
- [ ] Traduzione automatica contratti
- [ ] Confronto versioni contratti
- [ ] Generazione bozze clausole mancanti
- [ ] Export analisi in PDF
- [ ] Dashboard analytics rischi contratti
- [ ] Notifiche scadenze automatiche da date estratte
- [ ] Integrazione firma elettronica
- [ ] Template contratti AI-generated

## Supporto

Per problemi o domande:
1. Controlla i log: `storage/logs/laravel.log`
2. Verifica configurazione `.env`
3. Testa con contratto semplice prima

---

**Implementato da**: Claude Code Assistant
**Data**: 2025-10-06
**Versione**: 1.0.0
