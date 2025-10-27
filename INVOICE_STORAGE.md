# Sistema di Archiviazione Fatture Fornitori

## Overview

Le fatture d'acquisto caricate durante l'import componenti vengono **automaticamente salvate su Nextcloud** tramite Syncthing, in modo che siano accessibili sia dal sistema gestionale che dalla contabile.

## Dove Vengono Salvate le Fatture

### Percorso Nextcloud

```
Documenti SRL/
└── Fatture Fornitori/
    ├── 2024/
    │   ├── Mouser/
    │   ├── DigiKey/
    │   ├── Farnell/
    │   └── Altri/
    └── 2025/
        ├── Mouser/
        ├── DigiKey/
        ├── Farnell/
        └── Altri/
```

### Percorso Fisico

**Windows (Development):**
```
G:\Supernova\Documenti SRL\Fatture Fornitori\[Anno]\[Fornitore]\
```

**Linux (Production VPS):**
```
/opt/supernova-data/Documenti SRL/Fatture Fornitori/[Anno]/[Fornitore]/
```

## Come Funziona

### 1. Durante l'Import Componenti

Quando importi componenti tramite **Filament Admin → Components → Import**:

1. ✅ Seleziona il fornitore (Mouser, DigiKey, Farnell)
2. ✅ Carica il file CSV/Excel dei componenti
3. ✅ **Carica la fattura PDF** nel tab "Fattura d'Acquisto"
4. ✅ Inserisci numero fattura, data, totale
5. ✅ Click "Import"

### 2. Salvataggio Automatico

Il sistema:
- ✅ Salva la fattura in `Documenti SRL/Fatture Fornitori/[Anno]/[Fornitore]/`
- ✅ Collega la fattura ai componenti importati
- ✅ Salva il numero fattura, data, totale nel database
- ✅ Crea movimenti di inventario con riferimento alla fattura
- ✅ Syncthing sincronizza automaticamente con Nextcloud

### 3. Notifica

Dopo l'import ricevi una notifica con:
```
📊 File: Excel
✅ Imported: 50, Updated: 10, Failed: 0
💾 Fattura: FT-2025-001 collegata alle transazioni
📁 Salvata in Nextcloud: Documenti SRL/Fatture Fornitori/2025/Mouser
```

## Accesso alle Fatture

### Per Te (Amministratore)

**Via Sistema Gestionale:**
- Vai su **Inventory Movements** → Visualizza movimento → Vedi "Invoice Number" e "Invoice Path"
- Download diretto della fattura dal sistema

**Via File System:**
- Apri `G:\Supernova\Documenti SRL\Fatture Fornitori\`
- Oppure accedi via Nextcloud web/app

### Per la Contabile

**Via Nextcloud:**
1. Accesso web: `https://nextcloud.tuodominio.it`
2. Vai a: `Documenti SRL/Fatture Fornitori/`
3. Seleziona anno e fornitore
4. Download PDF delle fatture

**Vantaggi:**
- ✅ Nessun accesso al sistema gestionale necessario
- ✅ Può vedere solo le fatture, non i dati sensibili
- ✅ Sincronizzazione automatica in tempo reale
- ✅ Può aprire/scaricare fatture da qualsiasi dispositivo

## Configurazione Permessi Nextcloud

### Condivisione Cartella con Contabile

1. Vai su Nextcloud
2. Apri `Documenti SRL/Fatture Fornitori/`
3. Click destro → **Share**
4. Aggiungi utente contabile
5. Imposta permessi:
   - ✅ **Read** (lettura)
   - ❌ **Edit** (modifica) - disabilitato
   - ❌ **Delete** (cancellazione) - disabilitato
   - ✅ **Download** (download)

### Permessi Consigliati

| Ruolo | Lettura | Modifica | Cancellazione | Download |
|-------|---------|----------|---------------|----------|
| **Amministratore** | ✅ | ✅ | ✅ | ✅ |
| **Contabile** | ✅ | ❌ | ❌ | ✅ |
| **Solo Lettura** | ✅ | ❌ | ❌ | ❌ |

## Struttura File README

Ogni cartella contiene un file `README.txt` con informazioni:

```
Fatture Fornitori - Mouser 2025
=======================================

Questa cartella contiene le fatture d'acquisto da Mouser per l'anno 2025.

Le fatture vengono caricate automaticamente dal sistema di gestione
durante l'import dei componenti.

Formato file: PDF, JPG, PNG
Sincronizzato con: Nextcloud/Syncthing

Generato automaticamente: 2025-10-07 19:45:00
```

## Nomenclatura File

Le fatture vengono salvate con nome automatico generato da Filament:

**Formato:**
```
[random-hash].[estensione]

Esempio:
01JBCD12EFGH3IJKLMN4.pdf
```

**Consiglio:** Nel form di import, usa un numero fattura descrittivo per facilitare la ricerca:
- ✅ `MOUSER-2025-001234`
- ✅ `DK-FT-2025-05-15-0001`
- ❌ `fattura1.pdf`

## Metadati Fattura nel Database

Per ogni fattura salvata, il database contiene:

```sql
inventory_movements:
  - invoice_number: "MOUSER-2025-001234"
  - invoice_path: "G:\Supernova\Documenti SRL\Fatture Fornitori\2025\Mouser\01JBCD.pdf"
  - invoice_date: "2025-10-07"
  - invoice_total: 1250.50
  - supplier: "mouser"
  - notes: "Ordine componenti progetto X"
```

## Ricerca Fatture

### Nel Sistema Gestionale

**Via Inventory Movements:**
```
1. Vai su Inventory Movements
2. Filtra per:
   - Invoice Number
   - Supplier
   - Date Range
3. Visualizza fattura collegata
```

**Via Components:**
```
1. Vai su Components
2. Seleziona componente
3. Tab "Inventory History"
4. Vedi fattura di acquisto
```

### In Nextcloud

**Ricerca per nome file:**
- Usa la barra di ricerca Nextcloud
- Cerca per estensione: `.pdf`
- Cerca per anno: `2025`

**Organizzazione manuale:**
- Le cartelle sono già organizzate per anno/fornitore
- Naviga direttamente alla cartella corretta

## Backup e Sincronizzazione

### Syncthing

Le fatture vengono sincronizzate automaticamente:

**Device Sincronizzati:**
1. Server VPS Linux (principale)
2. PC Amministrazione Windows
3. Nextcloud Server (via mount)

**Vantaggi:**
- ✅ Backup automatico su più dispositivi
- ✅ Disponibilità offline
- ✅ Sincronizzazione bidirezionale
- ✅ Versioning (se abilitato)

### Backup Aggiuntivi

**Consigliato:**
1. Backup settimanale di `Documenti SRL/Fatture Fornitori/` su storage esterno
2. Backup cloud (Google Drive, Dropbox) come ridondanza
3. Backup annuale su disco esterno offline

## Troubleshooting

### Fattura Non Appare in Nextcloud

**Problema:** Ho caricato la fattura ma non la vedo in Nextcloud.

**Soluzioni:**

1. **Verifica Syncthing Status**
   ```bash
   # Controlla se Syncthing è in esecuzione
   systemctl status syncthing@username

   # Oppure via web UI
   http://localhost:8384
   ```

2. **Forza Sincronizzazione**
   - Apri Syncthing Web UI
   - Vai su "Documenti SRL" folder
   - Click "Rescan"

3. **Verifica Path Locale**
   ```bash
   # Controlla se il file esiste localmente
   ls -la "G:\Supernova/Documenti SRL/Fatture Fornitori/2025/Mouser/"
   ```

4. **Controlla Permessi**
   ```bash
   # Assicurati che Syncthing abbia permessi di lettura
   chmod -R 755 "G:\Supernova/Documenti SRL/Fatture Fornitori/"
   ```

### Errore "Disk not configured"

**Problema:** Errore durante upload fattura: "syncthing_documents disk not configured"

**Soluzione:**

1. Verifica `.env`:
   ```env
   SYNCTHING_DOCUMENTS_PATH="G:\Supernova\Documenti SRL"
   # Oppure Linux:
   SYNCTHING_DOCUMENTS_PATH="/opt/supernova-data/Documenti SRL"
   ```

2. Clear cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

3. Verifica `config/filesystems.php`:
   ```php
   'syncthing_documents' => [
       'driver' => 'local',
       'root' => env('SYNCTHING_DOCUMENTS_PATH', ...),
   ],
   ```

### Cartelle Mancanti

**Problema:** Le cartelle per anno/fornitore non esistono.

**Soluzione:**

Esegui lo script di setup:
```bash
php setup_invoice_folders.php
```

Output:
```
✅ Created: Fatture Fornitori/2025/Mouser
✅ Created: Fatture Fornitori/2025/DigiKey
✅ Created: Fatture Fornitori/2025/Farnell
```

### Fattura Non Collegata ai Componenti

**Problema:** La fattura è stata caricata ma non vedo il collegamento nei movimenti inventario.

**Verifica:**
1. Vai su **Inventory Movements**
2. Filtra per data import
3. Controlla campo "Invoice Number"

**Se mancante:**
- La fattura è stata salvata ma non collegata
- Questo può succedere se l'import è fallito dopo il caricamento fattura
- Puoi rifare l'import con lo stesso file CSV - i componenti esistenti vengono aggiornati

## Gestione Spazio

### Dimensioni Tipiche

**Per fattura:**
- PDF medio: 200-500 KB
- PDF scannerizzato: 1-3 MB
- Immagine JPG: 500 KB - 2 MB

**Stima annuale:**
- 100 fatture/anno × 500 KB = ~50 MB/anno
- 500 fatture/anno × 1 MB = ~500 MB/anno

### Pulizia

**Fatture Vecchie:**
- Considera di archiviare fatture >5 anni su storage offline
- Mantieni su Nextcloud solo ultimi 3-5 anni
- Backup completo prima di cancellare

**Script Pulizia (esempio):**
```bash
# NON eseguire senza backup!
# Archivia fatture più vecchie di 5 anni
find "Fatture Fornitori/" -name "*.pdf" -mtime +1825 -exec mv {} "Archive/" \;
```

## Integrazione con Sistema Contabilità

### Export Dati Fatture

**Via SQL:**
```sql
SELECT
    invoice_number,
    invoice_date,
    invoice_total,
    supplier,
    COUNT(*) as num_components
FROM inventory_movements
WHERE invoice_number IS NOT NULL
GROUP BY invoice_number, invoice_date, invoice_total, supplier
ORDER BY invoice_date DESC;
```

**Via Filament:**
- Vai su **Inventory Movements**
- Filtra per "Has Invoice"
- Export CSV con tutti i dati

### CSV Export per Contabile

Il CSV esportato contiene:
- Numero fattura
- Data
- Totale (€)
- Fornitore
- Path file PDF (per reference)
- Numero componenti

## Best Practices

### 1. Naming Convention Numero Fattura

✅ **Buono:**
```
MOUSER-2025-001234
DK-FT-2025-05-15-001
FARNELL-20250507-ABC123
```

❌ **Evitare:**
```
fattura1
inv
ft-001 (ambiguo senza anno/fornitore)
```

### 2. Organizzazione

- ✅ Una fattura per import
- ✅ Numero fattura univoco
- ✅ Totale fattura accurato (per riconciliazione)
- ✅ Note descrittive (es. "Ordine progetto X")

### 3. Verifica Post-Import

Dopo ogni import:
1. ✅ Controlla notifica per conferma salvataggio
2. ✅ Vai in Nextcloud e verifica presenza fattura
3. ✅ Apri un movimento inventario e verifica collegamento
4. ✅ Informa contabile se necessario

### 4. Condivisione con Contabile

**Setup Iniziale:**
1. Crea utente Nextcloud per contabile
2. Condividi `Documenti SRL/Fatture Fornitori/` (solo lettura)
3. Invia email con istruzioni accesso
4. Verifica che possa vedere le fatture

**Workflow Mensile:**
1. Import componenti durante il mese
2. Fine mese: notifica contabile che fatture sono disponibili
3. Contabile accede Nextcloud e scarica fatture necessarie
4. Riconciliazione con export CSV dei movimenti

## Setup Iniziale

### 1. Configurazione .env

```env
SYNCTHING_ROOT_PATH=/opt/supernova-data
SYNCTHING_DOCUMENTS_PATH="${SYNCTHING_ROOT_PATH}/Documenti SRL"
```

### 2. Creazione Cartelle

```bash
php setup_invoice_folders.php
```

### 3. Test Upload

1. Vai su Filament Admin
2. Components → Import
3. Carica CSV test + fattura PDF test
4. Verifica in Nextcloud

### 4. Condivisione Nextcloud

1. Login Nextcloud come admin
2. Vai a `Documenti SRL/Fatture Fornitori/`
3. Share → Add user → Imposta permessi
4. Send email con link

## FAQ

### Q: Posso cambiare la struttura delle cartelle?
**A:** Sì, modifica `ListComponents.php` → `directory()` callback. Poi ricrea cartelle con `setup_invoice_folders.php`.

### Q: Cosa succede se carico la stessa fattura due volte?
**A:** Filament genera un nuovo file con hash diverso. Non sovrascrive. Entrambe le versioni sono salvate.

### Q: Posso salvare le fatture altrove (es. solo locale)?
**A:** Sì, cambia il disk da `syncthing_documents` a `local` o `public`. Ma perdi sincronizzazione Nextcloud.

### Q: La contabile può modificare le fatture?
**A:** Solo se le dai permessi di scrittura su Nextcloud. **Sconsigliato** - mantieni solo lettura per integrità dati.

### Q: Posso collegare una fattura a componenti già importati?
**A:** No, attualmente la fattura si collega solo durante import. Puoi reimportare lo stesso CSV con la fattura - i componenti vengono aggiornati.

### Q: Come faccio backup delle fatture?
**A:** Syncthing fa già backup automatico. Per backup aggiuntivo, copia `Documenti SRL/Fatture Fornitori/` su storage esterno mensile.

---

**Implementato:** 2025-10-07
**Versione:** 1.0
**Path:** `Documenti SRL/Fatture Fornitori/[Anno]/[Fornitore]/`
**Disk:** `syncthing_documents`
**Sync:** Syncthing → Nextcloud
