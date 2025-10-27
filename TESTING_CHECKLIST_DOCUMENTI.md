# Testing Checklist - Sistema Gestione Documenti Progetti

## Checklist Completa per Verifica Funzionalità

---

## Pre-Requisiti

- [ ] Nextcloud configurato e accessibile
- [ ] Variabili ambiente corrette in `.env`:
  - `NEXTCLOUD_BASE_URL`
  - `NEXTCLOUD_USERNAME`
  - `NEXTCLOUD_PASSWORD`
- [ ] Almeno un progetto creato nel sistema
- [ ] Cartella progetto creata su Nextcloud

---

## 1. Test Accesso Interfaccia

### 1.1 Accesso da ProjectResource
- [ ] Aprire menu **Projects**
- [ ] Cliccare su Edit di un progetto
- [ ] Verificare presenza tab **"Documenti Progetto"**
- [ ] Cliccare sulla tab
- [ ] Verificare messaggio empty state: "Nessun documento"
- [ ] Verificare pulsante "Carica Primo Documento"

### 1.2 Accesso Standalone
- [ ] Aprire menu **Gestione Progetti → Documenti Progetto**
- [ ] Verificare lista vuota (se nuovo sistema)
- [ ] Verificare pulsante **"New"** presente

---

## 2. Test Upload Documenti Base

### 2.1 Upload PDF
- [ ] Cliccare "Carica Documento" dal RelationManager
- [ ] Compilare form:
  - Nome: "Test PDF Document"
  - Tipo: "Altro"
  - File: Caricare un PDF di test
  - Descrizione: "Documento di test"
  - Tag: "test, v1"
- [ ] Cliccare "Create"
- [ ] Verificare notifica successo verde
- [ ] Verificare documento nella tabella
- [ ] Verificare icona documento corretta
- [ ] Verificare badge "Altro" grigio

### 2.2 Verifica Storage Locale
- [ ] Verificare file in `storage/app/project-documents/`
- [ ] Verificare nome file preservato

### 2.3 Verifica Upload Nextcloud
- [ ] Accedere a Nextcloud web interface
- [ ] Navigare: `Clienti/{CUSTOMER}/03_Progetti/{PROJECT}/05_Documentazione/`
- [ ] Verificare presenza file PDF
- [ ] Verificare nome file corretto

---

## 3. Test File 3D

### 3.1 Upload File STL
- [ ] Caricare file .stl
- [ ] **NON** selezionare tipo manualmente
- [ ] Verificare auto-selection tipo: "Modello 3D (STL/STEP)"
- [ ] Salvare
- [ ] Verificare badge azzurro
- [ ] Verificare icona cubo (heroicon-o-cube)
- [ ] Cliccare azione "Info"
- [ ] Verificare alert blu "File 3D/CAD Rilevato"
- [ ] Verificare testo info formato STL

### 3.2 Verifica Cartella Nextcloud
- [ ] Navigare: `02_Progettazione/3D_Models/PCB/`
- [ ] Verificare presenza file .stl

### 3.3 Upload File STEP
- [ ] Caricare file .step o .stp
- [ ] Verificare stesso comportamento di STL
- [ ] Verificare cartella corretta

---

## 4. Test File CAD

### 4.1 Upload File DXF
- [ ] Caricare file .dxf
- [ ] Verificare auto-detection tipo: "Disegno CAD"
- [ ] Verificare icona square-3-stack-3d
- [ ] Verificare badge azzurro

### 4.2 Verifica Cartella Nextcloud
- [ ] Navigare: `02_Progettazione/Mechanical/CAD_Drawings/`
- [ ] Verificare presenza file

---

## 5. Test File KiCad

### 5.1 Upload Progetto KiCad
- [ ] Caricare file .kicad_pcb o .kicad_sch
- [ ] Verificare auto-detection tipo: "Progetto KiCad"
- [ ] Verificare badge blu (primary)
- [ ] Verificare icona cpu-chip

### 5.2 Verifica Cartella Nextcloud
- [ ] Navigare: `02_Progettazione/KiCad/`
- [ ] Verificare presenza file

---

## 6. Test File Gerber

### 6.1 Upload File Gerber
- [ ] Caricare file .gbr o .gbl
- [ ] Verificare auto-detection tipo: "File Gerber"
- [ ] Verificare badge blu (primary)

### 6.2 Verifica Cartella Nextcloud
- [ ] Navigare: `02_Progettazione/Gerber/`
- [ ] Verificare presenza file

---

## 7. Test Firmware

### 7.1 Upload File Firmware
- [ ] Caricare file .hex o .bin
- [ ] Verificare auto-detection tipo: "Firmware"
- [ ] Verificare badge indaco

### 7.2 Verifica Cartella Nextcloud
- [ ] Navigare: `02_Progettazione/Firmware/`
- [ ] Verificare presenza file

---

## 8. Test Fatture

### 8.1 Upload Fattura Ricevuta
- [ ] Selezionare tipo: "Fattura Ricevuta"
- [ ] Compilare campi:
  - Nome: "Fattura Fornitore XYZ"
  - Importo: 1500.50
  - Valuta: EUR
  - Data: data odierna
- [ ] Salvare
- [ ] Verificare badge verde
- [ ] Verificare colonna importo formattata: "€1.500,50"

### 8.2 Verifica Cartella Nextcloud
- [ ] Navigare: `04_Certificazioni_Conformita/`
- [ ] Verificare presenza file

---

## 9. Test Filtri

### 9.1 Filtro per Tipo
- [ ] Caricare almeno 3 documenti di tipi diversi
- [ ] Aprire filtro "Tipo Documento"
- [ ] Selezionare un tipo specifico
- [ ] Verificare solo documenti di quel tipo visibili
- [ ] Selezionare multi-tipo
- [ ] Verificare OR logic corretta

### 9.2 Filtro "Solo File 3D/CAD"
- [ ] Caricare almeno 1 file 3D e 1 PDF
- [ ] Attivare filtro toggle "Solo File 3D/CAD"
- [ ] Verificare solo file 3D/CAD visibili

### 9.3 Filtro "Solo File Progettazione"
- [ ] Caricare KiCad, Gerber, BOM
- [ ] Attivare filtro "Solo File Progettazione"
- [ ] Verificare solo file progettazione visibili

### 9.4 Filtro "Solo Attivi"
- [ ] Disattivare un documento (Edit → is_active = false)
- [ ] Verificare filtro "Solo Attivi" attivo di default
- [ ] Verificare documento inattivo NON visibile
- [ ] Disattivare filtro
- [ ] Verificare documento inattivo visibile

---

## 10. Test Azioni

### 10.1 Azione Download
- [ ] Cliccare icona download su un documento
- [ ] Verificare apertura in nuova tab
- [ ] Verificare file corretto scaricato

### 10.2 Azione Info
- [ ] Cliccare azione "Info"
- [ ] Verificare modale apertura
- [ ] Verificare tutti i campi presenti:
  - Nome file originale
  - Tipo MIME
  - Dimensione formattata
  - Data documento
  - Data caricamento
  - Descrizione
  - Tag (con badge)
  - Path storage
- [ ] Per file 3D: verificare alert blu speciale

### 10.3 Azione Edit
- [ ] Cliccare "Edit"
- [ ] Modificare descrizione
- [ ] Aggiungere tag
- [ ] Salvare
- [ ] Verificare modifiche salvate
- [ ] Verificare tag visualizzati in Info

### 10.4 Azione Delete
- [ ] Cliccare "Delete"
- [ ] Verificare modale conferma
- [ ] Confermare
- [ ] Verificare documento rimosso da tabella
- [ ] Verificare file rimosso da storage locale
- [ ] **Nota**: File Nextcloud rimane (by design)

---

## 11. Test Bulk Actions

### 11.1 Bulk Delete
- [ ] Selezionare 2+ documenti con checkbox
- [ ] Cliccare "Delete" in bulk actions
- [ ] Confermare
- [ ] Verificare tutti documenti rimossi

---

## 12. Test Ricerca

### 12.1 Ricerca per Nome Documento
- [ ] Creare documenti con nomi distinti
- [ ] Usare search box
- [ ] Digitare parte del nome
- [ ] Verificare filtro real-time

### 12.2 Ricerca per Nome Progetto
- [ ] In vista standalone
- [ ] Digitare nome progetto
- [ ] Verificare filtro su colonna progetto

---

## 13. Test Ordinamento

### 13.1 Ordinamento Default
- [ ] Verificare documenti ordinati per created_at DESC (più recenti in alto)

### 13.2 Ordinamento per Nome
- [ ] Cliccare header colonna "Nome Documento"
- [ ] Verificare ordinamento alfabetico ASC
- [ ] Cliccare nuovamente
- [ ] Verificare ordinamento alfabetico DESC

### 13.3 Ordinamento per Data
- [ ] Cliccare header "Data Doc."
- [ ] Verificare ordinamento cronologico

---

## 14. Test Edge Cases

### 14.1 File Grandi
- [ ] Tentare upload file > 100 MB
- [ ] Verificare errore validazione
- [ ] Verificare messaggio chiaro

### 14.2 Formato Non Supportato
- [ ] Tentare upload .exe o .dmg
- [ ] Verificare errore validazione
- [ ] Verificare lista formati accettati in helper text

### 14.3 Campi Obbligatori
- [ ] Tentare salvare senza "Nome Documento"
- [ ] Verificare errore validazione
- [ ] Tentare salvare senza "Tipo"
- [ ] Verificare errore validazione
- [ ] Tentare salvare senza "File"
- [ ] Verificare errore validazione

### 14.4 Caratteri Speciali
- [ ] Caricare file con nome: "Test_ÀÈÌ-123.pdf"
- [ ] Verificare preservazione nome
- [ ] Verificare upload Nextcloud corretto

---

## 15. Test Gestione Errori Nextcloud

### 15.1 Nextcloud Non Disponibile
- [ ] Disabilitare temporaneamente Nextcloud (se possibile)
- [ ] Caricare documento
- [ ] Verificare notifica warning:
  - "Documento salvato localmente"
  - "Upload Nextcloud fallito" o errore specifico
- [ ] Verificare documento comunque presente in tabella
- [ ] Verificare file in storage locale

### 15.2 Cartella Nextcloud Non Esistente
- [ ] Usare progetto senza cartella Nextcloud
- [ ] Caricare documento
- [ ] Verificare creazione automatica cartella (se supportata)
- [ ] O verificare errore chiaro

---

## 16. Test Performance

### 16.1 Upload Multipli
- [ ] Caricare 10 documenti in rapida successione
- [ ] Verificare tutti upload completati
- [ ] Verificare notifiche corrette

### 16.2 Tabella con Molti Record
- [ ] Caricare 50+ documenti
- [ ] Verificare paginazione funzionante
- [ ] Verificare filtri responsive
- [ ] Verificare ricerca veloce

---

## 17. Test Responsive Design

### 17.1 Tablet View
- [ ] Ridurre finestra browser a 768px
- [ ] Verificare tabella scrollabile
- [ ] Verificare form leggibile

### 17.2 Mobile View
- [ ] Ridurre a 375px
- [ ] Verificare azioni accessibili
- [ ] Verificare form usabile

---

## 18. Test Dark Mode

### 18.1 Switch Dark Mode
- [ ] Attivare dark mode in Filament
- [ ] Verificare colori corretti in tabella
- [ ] Verificare badge leggibili
- [ ] Verificare modale Info corretta
- [ ] Verificare form leggibile

---

## 19. Test Permessi (se implementati)

### 19.1 Utente Standard
- [ ] Login come utente non-admin
- [ ] Verificare accesso documenti
- [ ] Verificare permessi upload/delete

---

## 20. Test Logging

### 20.1 Verifica Log Errori
- [ ] Provocare errore Nextcloud
- [ ] Controllare `storage/logs/laravel.log`
- [ ] Verificare log entry con:
  - Timestamp
  - Tipo errore
  - Document ID
  - Stack trace

---

## Report Bug Template

Se trovi un bug, annota:

```
**Titolo**: [Breve descrizione]
**Passi per riprodurre**:
1. ...
2. ...
3. ...

**Risultato atteso**: ...
**Risultato ottenuto**: ...
**Browser**: Chrome/Firefox/Safari + versione
**Screenshot**: (se disponibile)
**Log**: (se disponibile)
```

---

## Test Superati

Data: _______________
Tester: _______________

**Riepilogo**:
- Test completati: ___ / 20
- Bug trovati: ___
- Bug critici: ___

**Note aggiuntive**:
...

---

**Versione Checklist**: 1.0
**Data Creazione**: 2025-10-06
