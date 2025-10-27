# Quick Start: Generazione Contratti AI

## Setup Rapido (5 minuti)

### 1. Ottieni API Key Anthropic

1. Vai su https://console.anthropic.com/
2. Registrati o accedi
3. Vai in "API Keys"
4. Clicca "Create Key"
5. Copia la chiave (inizia con `sk-ant-...`)

### 2. Configura in Filament

1. Apri Supernova Management
2. Menu → **Profilo Azienda**
3. Sezione "Configurazione AI Claude":
   - **Claude API Key**: Incolla la chiave
   - **Claude Model**: Seleziona `claude-3-5-sonnet-20241022` (default)
   - **Claude Abilitato**: Attiva il checkbox
4. Clicca **Salva**

### 3. Genera il Tuo Primo Contratto

1. Menu → **Contratti Clienti** → **Nuovo**
2. Compila i campi base:
   - **Cliente**: Seleziona un cliente esistente
   - **Titolo**: "Accordo di Riservatezza progetto XYZ"
   - **Tipo**: NDA
   - **Data Inizio**: Oggi
   - **Valore**: (opzionale) €5.000
3. Nella sezione **Termini e Note**:
   - Clicca il pulsante **"Genera Bozza AI"** ✨
4. Nel modal che si apre:
   - (Opzionale) Clausole Speciali: "Durata 5 anni, clausola penale 20%"
   - (Opzionale) Durata: 36 mesi
   - Clicca **"Genera Bozza"**
5. Attendi 5-10 secondi ⏱️
6. La bozza appare nel campo "Termini e Condizioni"
7. Rivedi e modifica se necessario
8. Clicca **"Salva"**

### 4. Genera PDF

1. Nella tabella "Contratti Clienti"
2. Trova il contratto appena creato
3. Menu azioni → **"Genera PDF"**
4. PDF salvato automaticamente su Nextcloud

## Esempi Rapidi

### NDA Standard
```
Cliente: ACME Corp
Titolo: "Accordo di Riservatezza"
Tipo: NDA
Clausole Speciali: (lascia vuoto per clausole standard)
```

### Contratto di Servizio
```
Cliente: TechStart SRL
Titolo: "Sviluppo prototipo elettronico"
Tipo: Service Agreement
Valore: €15.000
Durata: 3 mesi
Clausole: "3 milestone mensili da 5k ciascuna"
```

### Contratto Fornitura
```
Cliente: Manufacturing Inc
Titolo: "Fornitura componenti SMD"
Tipo: Supply Contract
Valore: €50.000
Clausole: "Certificazioni CE/RoHS, consegna entro 15gg, garanzia 24 mesi"
```

## Output Atteso

### Struttura Tipica NDA
- Art. 1 - Premesse e Oggetto
- Art. 2 - Definizioni (Informazioni Confidenziali)
- Art. 3 - Obblighi di Riservatezza
- Art. 4 - Durata dell'Obbligo
- Art. 5 - Restituzione delle Informazioni
- Art. 6 - Conseguenze della Violazione
- Art. 7 - Legge Applicabile e Foro Competente
- Art. 8 - Disposizioni Finali

### Lunghezza Media
- **NDA**: 3.000-5.000 caratteri, 8 articoli
- **Service Agreement**: 5.000-8.000 caratteri, 14 articoli
- **Supply Contract**: 6.000-9.000 caratteri, 16 articoli
- **Partnership**: 5.000-7.000 caratteri, 14 articoli

## Costi

| Operazione | Costo Stimato |
|------------|---------------|
| NDA | €0.02-0.03 |
| Service Agreement | €0.03-0.05 |
| Supply Contract | €0.04-0.06 |
| Partnership | €0.03-0.05 |

**Totale mensile stimato** (10 contratti): ~€0.40

## Troubleshooting Rapido

| Problema | Soluzione |
|----------|-----------|
| "Claude AI non configurato" | Vai in Profilo Azienda e inserisci API Key |
| "Seleziona prima un cliente" | Compila Cliente, Titolo e Tipo prima di generare |
| "Invalid API Key" | API Key errata, rigenerane una nuova |
| "Rate limit exceeded" | Attendi 60 secondi e riprova |
| Bozza troppo corta | Aggiungi più dettagli: valore, durata, clausole speciali |

## Best Practices

### ✅ DO
- Rivedi SEMPRE la bozza generata
- Fai validare da un legale prima della firma
- Personalizza con clausole specifiche del caso
- Salva clausole ricorrenti per riutilizzo
- Genera PDF solo dopo revisione completa

### ❌ DON'T
- Non firmare senza revisione legale
- Non usare per contratti critici senza avvocato
- Non copiare clausole senza capirle
- Non modificare riferimenti normativi senza verificare
- Non condividere API Key con terzi

## Prossimi Passi

Dopo aver generato il tuo primo contratto:

1. **Personalizza i Template**: Salva clausole ricorrenti
2. **Integra con Workflow**: Collega a progetti/quotazioni
3. **Monitora Costi**: Traccia utilizzo API mensile
4. **Forma il Team**: Condividi questa guida con colleghi
5. **Feedback**: Migliora i prompt in base ai risultati

## Supporto

- 📖 Documentazione completa: `/docs/AI_CONTRACT_GENERATION_SYSTEM.md`
- 🔧 Integrazione codice: `/app/Filament/Resources/CustomerContractResource_AI_GENERATION_INTEGRATION.php`
- 💻 Service: `/app/Services/ContractGeneratorService.php`
- 📝 Log errori: `storage/logs/laravel.log`

## Checklist Setup Completo

- [ ] API Key Anthropic ottenuta
- [ ] API Key inserita in Profilo Azienda
- [ ] Claude abilitato
- [ ] Modello selezionato (Sonnet 3.5)
- [ ] Cliente test creato
- [ ] Primo contratto test generato
- [ ] Bozza revisionata e salvata
- [ ] PDF generato con successo
- [ ] Team formato sull'utilizzo
- [ ] Processo di revisione legale definito

**Tempo totale setup**: ~10 minuti
**Tempo generazione contratto**: ~1-2 minuti
**Tempo risparmiato vs. manuale**: ~85% (da 30min a 4min)
