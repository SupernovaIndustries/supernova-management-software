# Sistema di Generazione Automatica Bozze Contratti con AI

## Panoramica

Sistema completo per la generazione automatica di bozze contrattuali professionali utilizzando Claude AI (Anthropic). Genera contratti conformi alla legislazione italiana in formato HTML pronto per la conversione in PDF.

## Componenti Implementati

### 1. ContractGeneratorService
**File**: `/app/Services/ContractGeneratorService.php`

Servizio principale per la generazione AI di contratti.

#### Metodi Principali

```php
generateContractDraft(CustomerContract $contract, array $options = []): string
```
Genera una bozza completa del contratto basata sul tipo e sui dati forniti.

**Parametri**:
- `$contract`: Oggetto CustomerContract (anche temporaneo per preview)
- `$options`: Array con:
  - `special_clauses` (string): Clausole speciali richieste dall'utente
  - `duration_months` (int): Durata in mesi (se end_date non specificato)

**Ritorna**: String HTML con il contenuto del contratto

**Eccezioni**: Lancia `Exception` se Claude non è configurato o errori API

#### Tipi di Contratto Supportati

1. **NDA (Non-Disclosure Agreement)**
   - Definizione informazioni confidenziali
   - Obblighi di riservatezza
   - Durata dell'obbligo
   - Restituzione informazioni
   - Conseguenze violazione
   - Clausola penale

2. **Service Agreement (Contratto di Servizio)**
   - Descrizione servizi (progettazione, sviluppo, prototipazione)
   - Obblighi fornitore e cliente
   - Corrispettivo e pagamenti
   - Tempistiche e milestone
   - Proprietà intellettuale
   - Garanzie e responsabilità

3. **Supply Contract (Contratto di Fornitura)**
   - Caratteristiche fornitura componenti elettronici
   - Modalità consegna e Incoterms
   - Controllo qualità
   - Certificazioni (CE, RoHS, REACH)
   - Tracciabilità lotti
   - Garanzie

4. **Partnership**
   - Finalità collaborazione
   - Responsabilità delle parti
   - Governance e decisioni
   - Ripartizione costi/profitti
   - Proprietà intellettuale condivisa
   - Non concorrenza

#### Template Prompts

Ogni tipo di contratto ha un prompt dettagliato che:
- Specifica gli articoli obbligatori
- Include riferimenti normativi italiani
- Richiede linguaggio tecnico-giuridico
- Output formattato in HTML

#### Funzionalità Aggiuntive

```php
validateDraft(string $draft): array
```
Valida la bozza generata controllando:
- Lunghezza minima (500 caratteri)
- Presenza struttura HTML
- Termini legali italiani
- Conteggio articoli

**Ritorna**:
```php
[
    'valid' => bool,
    'issues' => array,
    'length' => int,
    'estimated_articles' => int,
]
```

```php
estimateCost(string $prompt, string $response): float
```
Stima il costo della generazione basato su:
- Claude 3.5 Sonnet: $3/MTok input, $15/MTok output
- Calcolo approssimativo: 1 token ≈ 4 caratteri

## Integrazione Filament

### CustomerContractResource

Il resource include un'azione AI per generare bozze direttamente dal form.

**Posizionamento**: Nell'header della sezione "Termini e Note"

**Funzionalità**:
1. Pulsante "Genera Bozza AI" con icona sparkles
2. Modal con opzioni:
   - Clausole speciali (textarea)
   - Durata in mesi (se end_date non impostata)
   - Info su costi stimati
3. Validazione pre-generazione:
   - Cliente selezionato
   - Titolo inserito
   - Tipo contratto scelto
4. Preview risultato prima del salvataggio
5. Notifiche di successo/errore

**File di Integrazione**:
`/app/Filament/Resources/CustomerContractResource_AI_GENERATION_INTEGRATION.php`

Contiene il codice completo da integrare nel resource.

### Integrazione Manuale

Se il file è stato modificato da altri sistemi (ContractAnalysisService, ContractReviewService), segui questi step:

1. Aggiungi import:
```php
use App\Services\ContractGeneratorService;
```

2. Nella sezione "Termini e Note", aggiungi `headerActions`:
```php
Forms\Components\Section::make('Termini e Note')
    ->schema([...])
    ->headerActions([
        // Vedi file CustomerContractResource_AI_GENERATION_INTEGRATION.php
    ]),
```

## Template PDF

### customer-contract.blade.php

**Modifiche**: Il template ora supporta sia contenuto HTML (AI-generated) che testo plain.

```blade
@if(str_contains($contract->terms, '<h3>') || str_contains($contract->terms, '<p>'))
    {{-- AI-generated HTML --}}
    {!! $contract->terms !!}
@else
    {{-- Plain text --}}
    {!! nl2br(e($contract->terms)) !!}
@endif
```

**Styling HTML AI**:
- `<h3>` per titoli articoli → classe `.article-title`
- `<p>` per paragrafi → classe `.article-content`
- `<ul>` e `<li>` per elenchi puntati

## Configurazione

### 1. API Key Claude

Vai a **Profilo Azienda** nel pannello Filament:
- Campo: `claude_api_key`
- Campo: `claude_model` (default: `claude-3-5-sonnet-20241022`)
- Checkbox: `claude_enabled`

**Ottenere API Key**:
1. Vai su [console.anthropic.com](https://console.anthropic.com/)
2. Crea account/accedi
3. Vai in "API Keys"
4. Genera nuova chiave
5. Copia e incolla in Profilo Azienda

### 2. Modelli Disponibili

Configurati in `CompanyProfile::getClaudeModels()`:

| Modello | Descrizione | Uso Consigliato |
|---------|-------------|------------------|
| `claude-3-5-sonnet-20241022` | Latest, bilanciato | **Raccomandato per contratti** |
| `claude-3-sonnet-20240229` | Precedente versione | Fallback |
| `claude-3-haiku-20240307` | Veloce, economico | Bozze rapide |
| `claude-3-opus-20240229` | Più capace | Contratti complessi |

### 3. Costi Stimati

**Claude 3.5 Sonnet**:
- Input: $3 per million tokens
- Output: $15 per million tokens

**Stima per contratto**:
- Prompt: ~2000 caratteri ≈ 500 tokens = $0.0015
- Output: ~8000 caratteri ≈ 2000 tokens = $0.03
- **Totale: ~$0.03-0.05 per contratto**

**Ottimizzazione costi**:
- Usa Haiku per bozze iniziali ($0.25/$1.25 per MTok)
- Usa Sonnet per versioni finali
- Rate limiting: 1 secondo tra chiamate (implementato)

## Utilizzo

### Flusso di Lavoro Completo

1. **Crea Nuovo Contratto**
   - Vai in "Contratti Clienti" → "Nuovo"
   - Compila campi obbligatori:
     - Cliente
     - Titolo (es: "NDA per progetto XYZ")
     - Tipo contratto
     - Date e importi

2. **Genera Bozza AI**
   - Clicca "Genera Bozza AI" nell'header sezione "Termini e Note"
   - Nel modal:
     - (Opzionale) Aggiungi clausole speciali
     - (Opzionale) Specifica durata in mesi
   - Clicca "Genera Bozza"
   - Attendi 5-15 secondi

3. **Rivedi e Modifica**
   - La bozza appare nel campo "Termini e Condizioni"
   - Usa editor rich text per modifiche
   - Notifica mostra: numero articoli, lunghezza, eventuali warning

4. **Salva Contratto**
   - Clicca "Salva"
   - Contratto salvato come bozza

5. **Genera PDF**
   - Nella tabella, clicca "Genera PDF" sul contratto
   - PDF generato automaticamente
   - Caricato su Nextcloud in: `Clienti/{nome_cliente}/01_Anagrafica/Contratti/`

### Esempi di Clausole Speciali

**Per NDA**:
```
- Durata obbligo: 5 anni dalla cessazione
- Clausola penale: 20% del valore del progetto
- Esclusione: dati già pubblicati su riviste scientifiche
```

**Per Service Agreement**:
```
- Milestone 1: Progettazione schematica - 30 giorni - 30%
- Milestone 2: Prototipo funzionale - 60 giorni - 40%
- Milestone 3: Test e documentazione - 20 giorni - 30%
- SLA: risposta entro 48h per supporto tecnico
```

**Per Supply Contract**:
```
- Consegna: DAP stabilimento cliente
- Lead time: 15 giorni lavorativi
- Certificazioni: RoHS, REACH, ISO 9001
- Controllo qualità: AQL 1.0 per campionamento
```

## Conformità Legale

### Riferimenti Normativi Italiani

I contratti generati includono riferimenti a:

**Codice Civile**:
- Artt. 1655 ss. - Contratto d'opera (Service Agreement)
- Artt. 1470 ss. - Vendita (Supply Contract)
- Artt. 1321 ss. - Contratti in generale

**Leggi Speciali**:
- D.Lgs. 231/2002 - Ritardi di pagamento
- D.Lgs. 196/2003 e GDPR - Privacy
- D.Lgs. 206/2005 - Codice del Consumo

**Normative Tecniche**:
- Direttive CE (marcatura CE)
- RoHS (Restriction of Hazardous Substances)
- REACH (Registration, Evaluation, Authorization of Chemicals)

### Disclaimer Importante

⚠️ **ATTENZIONE**: Le bozze generate dall'AI sono un punto di partenza professionale ma:

1. **NON sostituiscono la consulenza legale**
2. **Devono essere riviste da un avvocato** prima della firma
3. Possono contenere errori o imprecisioni
4. Vanno adattate al caso specifico
5. La responsabilità legale è sempre dell'azienda

**Raccomandazioni**:
- Fai sempre rivedere i contratti da un legale
- Adatta le clausole al caso specifico
- Verifica i riferimenti normativi
- Aggiorna periodicamente i template
- Mantieni traccia delle versioni

## Troubleshooting

### Errore: "Claude AI non è configurato"

**Causa**: API Key non impostata o Claude non abilitato

**Soluzione**:
1. Vai in Profilo Azienda
2. Inserisci `claude_api_key`
3. Abilita `claude_enabled`
4. Seleziona `claude_model`
5. Salva

### Errore: "Seleziona prima un cliente"

**Causa**: Form compilato parzialmente

**Soluzione**:
1. Seleziona cliente dal dropdown
2. Inserisci titolo contratto
3. Scegli tipo contratto
4. Riprova generazione

### Errore API: "Invalid API Key"

**Causa**: API Key scaduta o non valida

**Soluzione**:
1. Vai su console.anthropic.com
2. Verifica stato API Key
3. Genera nuova chiave se necessario
4. Aggiorna in Profilo Azienda

### Errore API: "Rate limit exceeded"

**Causa**: Troppe richieste in breve tempo

**Soluzione**:
- Attendi 60 secondi
- Il sistema ha rate limiting automatico (1 sec tra chiamate)
- Controlla piano Anthropic per limiti

### Bozza troppo breve o incompleta

**Causa**: Dati insufficienti o tipo contratto non riconosciuto

**Soluzione**:
1. Verifica tutti i campi compilati
2. Aggiungi valore contratto se rilevante
3. Specifica clausole speciali
4. Riprova generazione
5. Se persiste, usa tipo "generico"

### HTML non formattato correttamente nel PDF

**Causa**: Template blade non aggiornato

**Soluzione**:
- Verifica che `/resources/views/pdf/customer-contract.blade.php` abbia il check `str_contains()`
- CSS già presente per `.article-title`, `.article-content`, ecc.

## Estensioni Future

### Possibili Miglioramenti

1. **Template Personalizzati**
   - Salvare template aziendali riutilizzabili
   - Libreria clausole standard

2. **Multilingua**
   - Generazione in inglese
   - Altri template internazionali

3. **Integrazione Firma Elettronica**
   - Invio automatico per firma digitale
   - Tracking stato firma

4. **Analisi AI del Contratto Generato**
   - Scoring qualità
   - Suggerimenti miglioramento
   - Identificazione rischi

5. **Versioning Contratti**
   - Tracking modifiche
   - Comparazione versioni
   - Approvazioni multiple

6. **Statistiche Utilizzo**
   - Costi mensili AI
   - Tipologie più usate
   - Tempi medi generazione

## File Creati/Modificati

### Nuovi File
1. `/app/Services/ContractGeneratorService.php` - Service principale
2. `/app/Filament/Resources/CustomerContractResource_AI_GENERATION_INTEGRATION.php` - Codice integrazione
3. `/docs/AI_CONTRACT_GENERATION_SYSTEM.md` - Questa documentazione

### File Modificati
1. `/resources/views/pdf/customer-contract.blade.php` - Supporto HTML AI
2. `/app/Models/CompanyProfile.php` - Già aveva campi Claude
3. `/app/Filament/Resources/CustomerContractResource.php` - Da integrare manualmente

### Dipendenze Aggiunte
```json
{
    "symfony/http-client": "^7.3",
    "nyholm/psr7": "^1.8"
}
```

## Testing

### Test Manuale Consigliato

1. **Test NDA**
   ```
   Cliente: ACME Corp
   Titolo: "Accordo di Riservatezza per progetto Robotica"
   Tipo: NDA
   Durata: 3 anni
   Clausole speciali: "Esclusione dati già pubblicati"
   ```

2. **Test Service Agreement**
   ```
   Cliente: TechStart SRL
   Titolo: "Sviluppo prototipo IoT"
   Tipo: Service Agreement
   Valore: €15,000
   Durata: 6 mesi
   Clausole: "Milestone mensili, test finale con cliente"
   ```

3. **Test Supply Contract**
   ```
   Cliente: Manufacturing Inc
   Titolo: "Fornitura componenti elettronici"
   Tipo: Supply Contract
   Valore: €50,000
   Clausole: "Certificazioni CE/RoHS, consegna DAP, AQL 1.0"
   ```

### Checklist Validazione

- [ ] API Key configurata e funzionante
- [ ] Cliente selezionabile dal dropdown
- [ ] Tutti i tipi contratto generano output
- [ ] Output contiene almeno 3-4 articoli
- [ ] HTML ben formattato (h3, p, ul/li)
- [ ] Dati azienda e cliente inseriti correttamente
- [ ] Date e importi presenti quando specificati
- [ ] Clausole speciali incluse nel testo
- [ ] PDF generabile dal contratto salvato
- [ ] PDF include contenuto HTML formattato
- [ ] Notifiche funzionanti (successo/errore)
- [ ] Costo stimato ragionevole (<€0.10)

## Supporto

Per problemi o domande:
1. Consulta questa documentazione
2. Verifica log Laravel: `storage/logs/laravel.log`
3. Controlla console browser per errori JavaScript
4. Verifica API Key su console.anthropic.com

## Changelog

### v1.0.0 (2025-10-06)
- Implementazione iniziale ContractGeneratorService
- Supporto 4 tipi contratto (NDA, Service, Supply, Partnership)
- Integrazione Filament con modal interattivo
- Template PDF con supporto HTML
- Validazione bozze generate
- Documentazione completa
- Stima costi API
- Rate limiting integrato
