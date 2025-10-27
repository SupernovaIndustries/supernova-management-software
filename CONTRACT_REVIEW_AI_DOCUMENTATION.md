# Sistema di Revisione Intelligente Contratti con AI

## Panoramica

Il sistema di **Revisione Intelligente Contratti** utilizza Claude 3.5 Sonnet di Anthropic per analizzare automaticamente i contratti clienti, identificare clausole mancanti, rischi legali e problemi di compliance con la normativa italiana.

## File Creati

### 1. Migration
**File**: `/Users/supernova/supernova-management/database/migrations/2025_10_06_175108_add_ai_review_fields_to_customer_contracts_table.php`

Aggiunge 4 nuovi campi alla tabella `customer_contracts`:
- `ai_review_data` (json): Dati completi della revisione AI
- `ai_review_score` (integer): Score 0-100 della qualità del contratto
- `ai_review_issues_count` (integer): Numero totale di problemi identificati
- `ai_reviewed_at` (timestamp): Data e ora dell'ultima revisione

### 2. Service Layer
**File**: `/Users/supernova/supernova-management/app/Services/ContractReviewService.php`

Service principale per la revisione dei contratti con i seguenti metodi chiave:

#### Metodi Principali

- `reviewContract(CustomerContract $contract): array`
  - Analizza il testo del contratto usando Claude AI
  - Verifica tutte le clausole della checklist specifica per tipo contratto
  - Identifica rischi legali e problemi di compliance
  - Restituisce score, problemi e suggerimenti dettagliati

- `getChecklistForType(string $type): array`
  - Restituisce la checklist di controllo specifica per tipo contratto
  - Tipi supportati: nda, service_agreement, supply_contract, partnership

- `applySuggestions(CustomerContract $contract, array $selectedSuggestions): string`
  - Applica i suggerimenti selezionati al testo del contratto

#### Checklist per Tipo Contratto

**Clausole Base (tutti i contratti)**:
- Parti Identificate (denominazione, sede, P.IVA, rappresentante legale)
- Date Chiare (decorrenza, durata, scadenza)
- Oggetto Definito
- Firme e Sottoscrizioni
- Foro Competente
- Legge Applicabile
- Conformità GDPR

**NDA (Non-Disclosure Agreement)**:
- Informazioni Confidenziali Definite
- Esclusioni dalla Confidenzialità
- Durata dell'Obbligo
- Obbligo di Restituzione
- Uso Autorizzato
- Penali per Violazione

**Contratto di Servizio (Service Agreement)**:
- SLA Definiti (tempi di risposta, disponibilità)
- Responsabilità delle Parti
- Garanzie
- Clausole di Risoluzione
- Diritti di Proprietà Intellettuale
- Termini di Pagamento
- Limitazione di Responsabilità
- Supporto e Manutenzione

**Contratto di Fornitura (Supply Contract)**:
- Specifiche Prodotto
- Termini di Consegna (Incoterms)
- Standard di Qualità
- Garanzia Difetti (art. 1490 c.c.)
- Termini di Pagamento
- Forza Maggiore
- Riserva di Proprietà
- Resi e Reclami

**Partnership**:
- Governance (struttura decisionale, diritti di voto)
- Ripartizione Utili/Perdite
- Proprietà Intellettuale
- Non Concorrenza
- Strategia di Uscita
- Contributi delle Parti
- Processo Decisionale
- Gestione Stallo

### 3. Model Updates
**File**: `/Users/supernova/supernova-management/app/Models/CustomerContract.php`

Nuovi campi aggiunti a `$fillable` e `$casts`:
```php
'ai_review_data' => 'array',
'ai_review_score' => 'integer',
'ai_review_issues_count' => 'integer',
'ai_reviewed_at' => 'datetime',
```

Nuovi metodi helper:
- `isReviewed()`: bool - Verifica se il contratto è stato revisionato
- `getReviewScoreColorAttribute()`: string - Restituisce il colore del badge basato sullo score

### 4. Filament Resource
**File**: `/Users/supernova/supernova-management/app/Filament/Resources/CustomerContractResource.php`

#### Nuove Actions nella Tabella

**Action "Revisiona con AI"**
- Icona: shield-check (scudo)
- Colore: warning (arancione)
- Funzionalità:
  - Chiama `ContractReviewService->reviewContract()`
  - Salva risultati nel database
  - Mostra notifica con score e numero problemi

**Action "Vedi Revisione"**
- Icona: document-magnifying-glass
- Colore: info (blu)
- Visibile solo per contratti già revisionati
- Modal slide-over con visualizzazione completa della revisione

#### Nuova Colonna nella Tabella
- **Review AI**: Badge colorato che mostra lo score (es. "85/100")
  - Verde: ≥80 (eccellente)
  - Arancione: 60-79 (migliorabile)
  - Rosso: <60 (attenzione richiesta)
  - Grigio: non revisionato

#### Visualizzazione Revisione (Modal)

Il modal di revisione mostra:

1. **Score Generale**: Badge colorato con score 0-100
2. **Valutazione Generale**: Qualità, punti di forza, punti deboli
3. **Checklist Clausole**: Ogni clausola con:
   - Badge verde (✓): Presente e corretta
   - Badge arancione (⚠): Da migliorare
   - Badge rosso (✗): Mancante
   - Commento sulla situazione attuale
   - Suggerimento specifico
   - Testo suggerito (se applicabile)

4. **Rischi Legali** (se presenti):
   - Titolo del rischio
   - Descrizione dettagliata
   - Livello di gravità (critical/high/medium/low)
   - Raccomandazione per mitigare

5. **Problemi di Compliance** (se presenti):
   - Normativa violata (GDPR, Codice Civile, etc.)
   - Articolo specifico
   - Descrizione del problema
   - Soluzione proposta

6. **Miglioramenti Suggeriti**:
   - Area da migliorare
   - Situazione attuale
   - Testo suggerito
   - Priorità (alta/media/bassa)

### 5. Dashboard Widget
**File**: `/Users/supernova/supernova-management/app/Filament/Widgets/ContractReviewStatsWidget.php`

Widget statistiche che mostra:

1. **Contratti Revisionati**: X / Totale (con percentuale)
2. **Score Medio**: Media degli score con descrizione qualitativa
3. **Contratti Alto Rischio**: Numero contratti con score < 60
4. **Richiedono Revisione**: Non revisionati o score < 70
5. **Problemi Totali**: Somma di tutti i problemi identificati
6. **Revisioni Recenti**: Numero revisioni negli ultimi 7 giorni

Ogni statistica include:
- Icona descrittiva
- Colore semantico
- Chart di tendenza (dove applicabile)

## Configurazione

### 1. Variabili d'Ambiente

Aggiungi al file `.env`:

```env
# Anthropic Claude AI
ANTHROPIC_API_KEY=sk-ant-api03-xxxxxxxxxxxx
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
ANTHROPIC_MAX_TOKENS=4096
```

### 2. Configurazione Services

Il file `config/services.php` include già la configurazione:

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
    'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),
],
```

## Utilizzo

### 1. Revisionare un Contratto

1. Navigare su **Clienti → Contratti Clienti**
2. Nella tabella, cliccare l'icona **scudo** (Revisiona con AI) sul contratto desiderato
3. Confermare l'azione nel modal
4. Attendere l'elaborazione (20-40 secondi)
5. La notifica mostrerà score e numero problemi

### 2. Visualizzare la Revisione

1. Cliccare l'icona **lente di ingrandimento** (Vedi Revisione)
2. Il modal slide-over mostrerà tutti i dettagli:
   - Score generale
   - Checklist completa con badge colorati
   - Rischi legali identificati
   - Problemi di compliance
   - Suggerimenti di miglioramento

### 3. Monitorare le Statistiche

Il widget `ContractReviewStatsWidget` può essere aggiunto a qualsiasi dashboard Filament:

```php
protected function getHeaderWidgets(): array
{
    return [
        ContractReviewStatsWidget::class,
    ];
}
```

## Esempi di Output

### Esempio 1: NDA - Score 75/100

**Clausole Presenti** (verde):
- ✓ Parti Identificate
- ✓ Informazioni Confidenziali Definite
- ✓ Durata dell'Obbligo
- ✓ GDPR Compliance

**Da Migliorare** (arancione):
- ⚠ Esclusioni dalla Confidenzialità
  - *Commento*: Le esclusioni sono menzionate ma non completamente definite
  - *Suggerimento*: Specificare chiaramente: info pubbliche, già note, sviluppate indipendentemente, richieste da legge

**Mancante** (rosso):
- ✗ Penali per Violazione
  - *Testo suggerito*: "In caso di violazione degli obblighi di riservatezza, la Parte inadempiente sarà tenuta al risarcimento di tutti i danni, diretti e indiretti, subiti dalla Parte lesa, ivi incluso il lucro cessante. A titolo di penale, è previsto il pagamento di € [IMPORTO] per ogni violazione accertata."

**Rischi Legali Identificati**:
- **Durata Eccessiva**: L'obbligo di riservatezza di 10 anni potrebbe essere considerato eccessivo per alcune tipologie di informazioni. Raccomandazione: Differenziare la durata per tipo di informazione (es. 5 anni per info commerciali, 10 per segreti industriali).

### Esempio 2: Service Agreement - Score 92/100

**Punti di Forza**:
- SLA chiaramente definiti con metriche specifiche
- Responsabilità ben distribuite tra le parti
- Clausole di IP ownership dettagliate
- Termini di pagamento conformi alle best practices

**Punti Deboli**:
- La clausola di limitazione responsabilità potrebbe essere più specifica
- Mancano dettagli sulla gestione dei dati personali post-termine

**Compliance**:
- ✓ GDPR: Conforme (Reg. UE 2016/679)
- ✓ Codice Civile: Rispetta artt. 1321-1469
- ⚠ Clausole vessatorie: Una clausola richiede approvazione specifica (art. 1341 c.c.)

### Esempio 3: Supply Contract - Score 58/100 (Alto Rischio)

**Problemi Critici**:
- ✗ Garanzia Difetti: Mancante riferimento all'art. 1490 c.c.
- ✗ Specifiche Prodotto: Descrizione generica, mancano specifiche tecniche
- ✗ Termini di Consegna: Assenti gli Incoterms

**Rischi Legali**:
1. **Assenza Garanzia Legale** (Gravità: CRITICAL)
   - Il contratto non prevede la garanzia per vizi di cui all'art. 1490 c.c.
   - Raccomandazione: Inserire clausola di garanzia biennale per vizi, con obbligo di denuncia entro 8 giorni dalla scoperta

2. **Specifiche Vaghe** (Gravità: HIGH)
   - Le specifiche del prodotto sono insufficientemente dettagliate
   - Raccomandazione: Allegare schede tecniche dettagliate con tolleranze, certificazioni richieste, test di accettazione

**Score Basso**: Contratto necessita revisione urgente prima della firma

## Best Practices

### 1. Quando Revisionare

- **Sempre** prima della firma di un nuovo contratto
- Dopo modifiche sostanziali al testo
- Periodicamente per contratti a lungo termine (annualmente)
- Prima di rinnovi contrattuali

### 2. Interpretare gli Score

- **80-100**: Contratto ben strutturato, sicuro da firmare
- **60-79**: Migliorabile, considerare i suggerimenti prima della firma
- **40-59**: Alto rischio, revisione legale necessaria
- **0-39**: Critico, non firmare senza revisione legale completa

### 3. Gestione dei Suggerimenti

- I suggerimenti AI sono **indicativi**, non sostituiscono una consulenza legale professionale
- Per contratti di valore elevato (>€50.000), consultare sempre un avvocato
- I testi suggeriti vanno personalizzati al caso specifico
- Verificare sempre la compliance con normative aggiornate

### 4. Privacy e Sicurezza

- I dati vengono inviati all'API Anthropic (cloud USA)
- Non includere informazioni ultra-sensibili nei contratti da revisionare
- Per contratti riservati, considerare soluzioni on-premise
- I dati di revisione sono salvati nel database locale

## Troubleshooting

### Errore: "Il contratto non contiene testo da analizzare"
**Causa**: Campo `terms` vuoto
**Soluzione**: Compilare il campo "Termini e Condizioni" prima di revisionare

### Errore: "Errore chiamata API Anthropic"
**Causa**: API key non configurata o non valida
**Soluzione**: Verificare `ANTHROPIC_API_KEY` in `.env`

### Score sempre 0
**Causa**: Contratto molto carente o formato non riconosciuto
**Soluzione**: Verificare che il testo sia in italiano e contenga almeno le parti contrattuali base

### Timeout durante la revisione
**Causa**: Contratto molto lungo (>10.000 parole)
**Soluzione**: Aumentare `ANTHROPIC_MAX_TOKENS` o dividere il contratto in sezioni

## Normativa di Riferimento

Il sistema verifica la compliance con:

- **GDPR** (Reg. UE 2016/679): Trattamento dati personali
- **Codice Civile Italiano**:
  - Artt. 1321-1469: Contratti in generale
  - Artt. 1490-1495: Garanzia per vizi
  - Artt. 1341-1342: Clausole vessatorie
  - Artt. 1571-1654: Appalto e somministrazione
- **Codice del Consumo** (D.Lgs. 206/2005): Contratti B2C
- **Best Practices Internazionali**: Incoterms, SLA, IP licensing

## Sviluppi Futuri

### Roadmap Prevista

1. **Applica Suggerimenti Automaticamente**: Pulsante per applicare tutti i suggerimenti al testo
2. **Confronto Versioni**: Diff tra versione originale e suggerita
3. **Template Intelligenti**: Generazione automatica contratti da template
4. **Multi-lingua**: Supporto per contratti in inglese e altre lingue
5. **Integrazione Firma Elettronica**: Workflow completo firma digitale
6. **Export Report PDF**: Report di revisione stampabile
7. **Alert Automatici**: Notifiche per contratti che necessitano revisione
8. **Analisi Comparativa**: Confronto con contratti simili già revisionati

## Supporto

Per problemi o domande:
- Consultare questa documentazione
- Verificare i log in `storage/logs/laravel.log`
- Contattare il team di sviluppo

---

**Versione**: 1.0
**Data**: 6 Ottobre 2025
**Autore**: Sistema AI Claude Code
**Licenza**: Proprietaria - Supernova Management
