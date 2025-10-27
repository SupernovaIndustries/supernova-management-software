# Supernova Management Software - Recap Completo

## 📋 Panoramica del Progetto

Questo documento fornisce un riepilogo completo del sistema Supernova Management Software sviluppato utilizzando Laravel 11 + Filament v3, progettato per essere completamente modulare e portabile tra ambiente di sviluppo Windows (G:\Supernova) e ambiente di produzione OVH VPS.

## 🏗️ Architettura e Setup Iniziale

### Ambiente Docker
- **Docker Compose** configurato con tutti i servizi necessari
- **Laravel 11** come framework backend
- **PostgreSQL** database (porta 5435 per evitare conflitti)
- **Redis** per cache e sessioni (porta 6380)
- **Meilisearch** per funzionalità di ricerca avanzata
- **PHP 8.2** con tutte le estensioni necessarie (inclusa intl)

### Sistema di Path Modulari
Implementato un sistema di gestione path completamente configurabile via variabili d'ambiente:
- `SUPERNOVA_BASE_PATH` per il path base dell'applicazione
- `SYNCTHING_BASE_PATH` per l'integrazione con Syncthing
- Gestione automatica delle differenze tra Windows e Linux
- Path resolver centralizzato in `app/Services/PathService.php`

## 🗄️ Database e Modelli

### Schema Database Completo
Creati tutti i modelli e migrazioni per:

**Entità Principali:**
- `users` - Gestione utenti del sistema
- `customers` - Clienti con SDI code, PEC email, senza credit limit
- `suppliers` - Fornitori per componenti
- `categories` - Categorie per organizzazione componenti
- `components` - Inventario componenti elettronici
- `projects` - Progetti con status avanzati e gestione folder
- `quotations` - Preventivi con gestione pagamenti acconto/saldo
- `activities` - Attività e milestone dei progetti

**Gestione Documenti e BOM:**
- `documents` - Documenti associati a progetti/clienti
- `project_boms` - Distinta base progetti
- `project_bom_items` - Singoli item delle BOM
- `project_pcb_files` - File PCB/ECAD caricati

**Inventario e Movimenti:**
- `inventory_movements` - Movimenti di magazzino
- `aruco_markers` - Markers ArUco per identificazione componenti

**Relazioni Eloquent:**
Tutti i modelli implementano relazioni complete con eager loading e soft deletes dove appropriato.

## 🔧 Servizi Implementati

### 1. API Integration Services

**MouserApiService** (`app/Services/Suppliers/MouserApiService.php`):
- Ricerca componenti via API Mouser
- Recupero dettagli componenti
- Creazione web orders automatici
- Gestione autenticazione API key

**DigiKeyApiService** (`app/Services/Suppliers/DigiKeyApiService.php`):
- Integrazione completa con API DigiKey
- OAuth2 token management
- Ricerca e gestione componenti
- Aggiunta al carrello web

### 2. Import/Export Services

**ComponentImportService** (`app/Services/ComponentImportService.php`):
- Import CSV da Mouser, DigiKey, Farnell
- Mapping automatico colonne per ogni fornitore
- Parsing prezzi e stock con gestione formati diversi
- Validazione e deduplicazione componenti

### 3. File Management Services

**PcbFileService** (`app/Services/PcbFileService.php`):
- Scansione automatica cartelle clienti per file PCB
- Riconoscimento formati: KiCad (.kicad_pro), Altium (.PrjPCB), Eagle (.sch), Gerber (.gbr)
- Estrazione automatica versioni da nomi file
- Organizzazione gerarchica file per progetto

### 4. BOM Management

**BomService** (`app/Services/BomService.php`):
- Parsing automatico file BOM CSV
- Riconoscimento reference designators (C1-C5, R1,R2,R3)
- Parsing valori componenti (10k, 100nF, 1μF, etc.)
- Auto-allocazione componenti dall'inventario
- Gestione quantità e availability

### 5. Document Generation

**DocumentService** (`app/Services/DocumentService.php`):
- Generazione PDF preventivi con template professionale
- Generazione DDT automatici quando progetto raggiunge status "consegna prototipo test"
- Nomenclatura file: `offerta-{customerName}-{date}.pdf`, `ddt-{number}-{date}.pdf`
- Calcolo automatico acconto 40% / saldo 60%
- Salvataggio in cartelle cliente specifiche

## 🎨 Interface Utente (Filament v3)

### Resources Aggiornati

**CustomerResource:**
- Aggiunto campo `sdi_code` per codice SDI
- Aggiunto campo `pec_email` per email PEC
- Rimosso campo `credit_limit`
- Cambiato label da "Syncthing Folder" a "Folder"

**ComponentResource:**
- Azione "Import CSV" con selezione fornitore
- Azioni "Order from Mouser" e "Order from DigiKey"
- Form per quantità e customer order number
- Notifiche success/error per tutte le operazioni

**ProjectResource:**
- Select per status progetti con opzioni complete
- Campo `project_status` separato per stato archivio
- Azione "Generate DDT" visibile solo per status "consegna prototipo test"
- Badge colorati per stati progetti

**QuotationResource:**
- Azione "Generate PDF" per tutti i preventivi
- Integrazione con DocumentService per generazione PDF

### Dashboard Widgets

**ProjectGanttWidget:**
- Diagramma Gantt completo per visualizzazione timeline progetti
- Indicatori progresso, today line, progetti in ritardo
- Vista responsive con scroll orizzontale
- Color coding per stati progetti

**ProjectCalendarWidget:**
- Calendario mensile con navigazione
- Eventi: start progetti, due date, attività
- Indicatori visivi per progetti in ritardo
- Legenda colori per tipologie eventi

**Widgets Esistenti:**
- StatsOverview - Statistiche generali
- LowStockComponents - Componenti sotto scorta minima
- RecentProjects - Progetti recenti

## 📄 Template PDF

### Quotation Template (`resources/views/pdf/quotation.blade.php`):
- Header professionale con logo placeholder
- Sezione informazioni cliente (business name, VAT, SDI, address)
- Dettagli preventivo (numero, data, validità)
- Tabella items con descrizioni, quantità, prezzi unitari, totali
- Calcolo automatico IVA 22%
- Sezione termini pagamento con visualizzazione acconto/saldo
- Footer con contatti azienda

### DDT Template (`resources/views/pdf/ddt.blade.php`):
- Header aziendale con schema colori rosso distintivo
- Sezioni destinatario e indirizzo spedizione
- Numero DDT, data, riferimento progetto
- Tabella items con codice, descrizione, quantità, note
- Sezione dettagli trasporto (vettore, data, ora, pesi)
- Informazioni aspetto merci
- Righe firma vettore e destinatario
- Conformità normative nel footer

## 🔄 Funzionalità Avanzate Implementate

### 1. **Integrazione API Fornitori**
- Ricerca componenti in tempo reale
- Creazione ordini web automatici
- Sincronizzazione prezzi e disponibilità

### 2. **Import CSV Intelligente**
- Riconoscimento automatico formato fornitore
- Mapping colonne flessibile
- Gestione errori e report import

### 3. **Gestione File PCB Automatica**
- Scan cartelle Syncthing per nuovi file
- Riconoscimento automatico tipo progetto
- Versioning automatico

### 4. **BOM Auto-Allocation**
- Parsing BOM con AI per riconoscimento componenti
- Allocazione automatica dall'inventario
- Gestione shortage e alternative

### 5. **Workflow Documenti**
- Generazione automatica PDF preventivi
- DDT automatici al cambio status progetto
- Archiviazione organizzata per cliente

### 6. **Visualizzazioni Progetti**
- Gantt chart interattivo
- Calendario eventi progetti
- Dashboard KPI in tempo reale

## 🔧 Configurazione Tecnica

### Environment Variables Chiave:
```bash
# Path Configuration
SUPERNOVA_BASE_PATH=/mnt/g/Supernova
SYNCTHING_BASE_PATH=/mnt/g/Supernova/supernova-management/storage/syncthing

# API Keys
MOUSER_API_KEY=your_mouser_api_key
DIGIKEY_CLIENT_ID=your_digikey_client_id
DIGIKEY_CLIENT_SECRET=your_digikey_client_secret

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=supernova_management

# Cache & Search
REDIS_HOST=redis
REDIS_PORT=6379
MEILISEARCH_HOST=meilisearch
MEILISEARCH_PORT=7700
```

### Comandi Artisan Personalizzati:
- `php artisan supernova:import-components` - Import batch componenti
- `php artisan supernova:scan-pcb-files` - Scan file PCB
- `php artisan supernova:process-boms` - Elaborazione BOM
- `php artisan supernova:update-admin-email` - Aggiornamento email admin

## 📊 Metrics e KPI Implementati

### Dashboard Statistics:
- Numero totale progetti attivi
- Componenti sotto scorta minima
- Preventivi in pending
- Progetti in ritardo

### Reports Disponibili:
- Export inventory CSV
- Report utilizzo componenti
- Timeline progetti Gantt
- Calendario milestone

## 🚀 Status Implementazione

### ✅ Completamente Implementato:
1. **Integrazione API Mouser & DigiKey** - Ricerca, dettagli, ordini web
2. **Import CSV Componenti** - Supporto Mouser, DigiKey, Farnell
3. **Gestione File PCB/ECAD** - Scan automatico da cartelle Syncthing
4. **BOM Management Automatico** - Parsing e allocazione componenti
5. **Aggiornamenti Customer Model** - SDI code, PEC email, rimozione credit limit
6. **Gestione Pagamenti** - Split acconto/saldo su progetti
7. **UI Labels Update** - Rimosso "Syncthing", sostituito con "Folder"
8. **Generazione PDF Preventivi** - Template professionale, path personalizzati
9. **Diagramma Gantt Progetti** - Widget interattivo dashboard
10. **DDT Automatici** - Generazione al cambio status "consegna prototipo test"

### 📋 Pronto per Deploy:
- Tutti i servizi testati e funzionanti
- Database migrations complete
- Filament resources aggiornati
- PDF templates implementati
- Widgets dashboard attivi

### 📧 Admin Email Aggiornato:
Email admin sistema cambiato a: `alessandro.cursoli@supernovaindustries.it`

---

## 🎯 Risultato Finale

Il sistema Supernova Management Software è ora completamente funzionale con tutte le 12 funzionalità richieste implementate. Fornisce una soluzione completa per la gestione di progetti elettronici, inventario componenti, preventivazione, e workflow documentali, il tutto in un ambiente moderno e user-friendly basato su Laravel + Filament v3.