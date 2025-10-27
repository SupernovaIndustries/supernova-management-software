# Categorie Documenti - Quick Reference

## Riferimento Rapido per Upload Documenti Progetto

---

## Fatturazione e Amministrazione

| Categoria | Nome Italiano | Cartella Nextcloud |
|-----------|---------------|-------------------|
| `invoice_received` | Fattura Ricevuta | `04_Certificazioni_Conformita` |
| `invoice_issued` | Fattura Emessa | `04_Certificazioni_Conformita` |
| `customs` | Documenti Dogana | `03_Produzione/Ordini_Componenti` |

**Campi Speciali**: Importo, Valuta

---

## Progettazione Elettronica

### KiCad

| Categoria | Nome Italiano | Cartella Nextcloud | Estensioni Comuni |
|-----------|---------------|-------------------|-------------------|
| `kicad_project` | Progetto KiCad | `02_Progettazione/KiCad` | .kicad_pcb, .kicad_sch, .kicad_pro |
| `kicad_library` | Librerie KiCad | `02_Progettazione/KiCad/libraries` | .kicad_mod, .lib |

### File di Produzione

| Categoria | Nome Italiano | Cartella Nextcloud | Estensioni Comuni |
|-----------|---------------|-------------------|-------------------|
| `gerber` | File Gerber | `02_Progettazione/Gerber` | .gbr, .gbl, .gtl, .gbs, .gts |
| `bom` | Bill of Materials | `02_Progettazione/BOM` | .csv, .xlsx, .pdf |
| `bom_interactive` | BOM Interattiva | `02_Progettazione/BOM` | .html, .json |

### Componenti

| Categoria | Nome Italiano | Cartella Nextcloud |
|-----------|---------------|-------------------|
| `datasheet` | Datasheet | `02_Progettazione/Datasheet/Component_Datasheets` |

---

## Modellazione 3D e CAD

| Categoria | Nome Italiano | Cartella Nextcloud | Estensioni Comuni |
|-----------|---------------|-------------------|-------------------|
| `3d_model` | Modello 3D (STL/STEP) | `02_Progettazione/3D_Models/PCB` | .stl, .step, .stp, .iges |
| `3d_case` | Case/Enclosure 3D | `02_Progettazione/3D_Models/Enclosure` | .stl, .step, .obj |
| `3d_mechanical` | Progetto Meccanico 3D | `02_Progettazione/3D_Models/Assembly` | .step, .iges, .3mf |
| `cad_drawing` | Disegno CAD | `02_Progettazione/Mechanical/CAD_Drawings` | .dxf, .dwg, .f3d |

**Auto-Detection**: File 3D e CAD vengono riconosciuti automaticamente dall'estensione

---

## Firmware e Software

| Categoria | Nome Italiano | Cartella Nextcloud | Estensioni Comuni |
|-----------|---------------|-------------------|-------------------|
| `firmware` | Firmware | `02_Progettazione/Firmware` | .bin, .hex, .elf |

---

## Produzione e Assemblaggio

| Categoria | Nome Italiano | Cartella Nextcloud |
|-----------|---------------|-------------------|
| `assembly_instructions` | Istruzioni Assemblaggio | `03_Produzione/Assembly_Instructions` |
| `test_report` | Report Test | `03_Produzione/Test_Reports` |

---

## Certificazioni e Conformità

| Categoria | Nome Italiano | Cartella Nextcloud |
|-----------|---------------|-------------------|
| `certification` | Certificazioni | `04_Certificazioni_Conformita` |

---

## Assistenza e Quality Control

| Categoria | Nome Italiano | Cartella Nextcloud |
|-----------|---------------|-------------------|
| `complaint` | Reclamo Cliente | `07_Assistenza/Reclami` |
| `error_report` | Report Errori | `07_Assistenza/Error_Reports` |

---

## Altro

| Categoria | Nome Italiano | Cartella Nextcloud |
|-----------|---------------|-------------------|
| `other` | Altro | `05_Documentazione` |

---

## Badge Colori nell'Interfaccia

- **Verde** (success): Fatture, Certificazioni, Datasheet
- **Blu** (primary): KiCad, Gerber, BOM
- **Azzurro** (info): File 3D e CAD
- **Rosso** (danger): Reclami, Report Errori
- **Giallo** (warning): Documenti Dogana
- **Grigio** (secondary): Istruzioni, Test Report
- **Indaco** (indigo): Firmware
- **Grigio chiaro** (gray): Altro

---

## Tips per l'Utilizzo

### Auto-Detection
Il sistema riconosce automaticamente questi file:
- **.stl, .step, .iges** → `3d_model`
- **.dxf, .dwg, .f3d** → `cad_drawing`
- **.kicad_**** → `kicad_project`
- **.gbr, .gbl, .gtl** → `gerber`
- **.bin, .hex, .elf** → `firmware`

### Tag Suggeriti
- `v1`, `v2`, `v3` - Versioni
- `bozza` - Documenti non finali
- `finale` - Versione definitiva
- `approvato` - Approvato per produzione
- `revisione` - In revisione
- `urgente` - Priorità alta

### Best Practices
1. Usa nomi descrittivi per i documenti
2. Aggiungi sempre la data documento
3. Usa i tag per versioning e stato
4. Compila la descrizione per documenti complessi
5. Per fatture, inserisci sempre l'importo

---

**Ultima modifica:** 2025-10-06
