# Collegamento Fatture a Progetti

## Problema Risolto

**Due problemi risolti:**

1. ✅ **Errore icona dashboard:** `Svg by name "o-circuit-board" from set "heroicons" not found`
2. ✅ **Collegamento fatture a progetti:** Come collegare un acquisto/fattura a un progetto specifico

---

## 1. Errore Icona Dashboard - RISOLTO ✅

### Problema
```
Svg by name "o-circuit-board" from set "heroicons" not found
```

L'icona `heroicon-o-circuit-board` non esiste in heroicons.

### Soluzione
Sostituita con `heroicon-o-cpu-chip` (icona chip del computer) che è semanticamente corretta per "schede prodotte".

**File Modificato:** `app/Filament/Widgets/ActiveProjectsStatsWidget.php`

```php
// Prima
->descriptionIcon('heroicon-o-circuit-board')  // ❌ Non esiste

// Ora
->descriptionIcon('heroicon-o-cpu-chip')        // ✅ Funziona
```

**Risultato:** Nessun errore nella dashboard!

---

## 2. Collegamento Fatture a Progetti - IMPLEMENTATO ✅

### Come Funziona Ora

Quando importi componenti, **puoi specificare un progetto destinazione**:

### Workflow

1. **Vai su Components → Import**
2. Compila tab "File Componenti":
   - Carica CSV/Excel
   - Seleziona fornitore (Mouser/DigiKey/Farnell)

3. Compila tab "Fattura d'Acquisto":
   - Carica fattura PDF
   - Inserisci numero fattura, data, totale
   - **🎯 NUOVO:** Seleziona "Progetto Destinazione" (opzionale)
   - Aggiungi note

4. Click **Import**

### Cosa Succede

Il sistema:
- ✅ Importa i componenti
- ✅ Salva la fattura su Nextcloud
- ✅ **Collega i movimenti di inventario al progetto specificato**
- ✅ Ti notifica del collegamento

**Notifica esempio:**
```
📊 File: Excel
✅ Imported: 50, Updated: 10, Failed: 0
💾 Fattura: MOUSER-2025-001 collegata alle transazioni
📁 Salvata in Nextcloud: Documenti SRL/Fatture Fornitori/2025/Mouser
🎯 Progetto: Progetto Apollo  ← NUOVO!
```

### Dove Vedi il Collegamento

**1. Inventory Movements**
- Vai su **Inventory Movements**
- Vedi colonna "Destination Project"
- Clicca sul progetto per visualizzarlo

**2. Project Detail**
- Vai sul progetto specifico
- Vedi tutti i componenti acquistati per quel progetto
- Vedi le fatture collegate

**3. Reportistica**
- Filtra movimenti per progetto
- Export CSV con informazioni fattura + progetto
- Analisi costi per progetto

### Campo Opzionale

**Importante:** Il campo "Progetto Destinazione" è **opzionale**!

- ✅ **Con progetto:** I componenti sono destinati a un progetto specifico
- ✅ **Senza progetto:** I componenti vanno in magazzino generico (uso normale)

**Quando usare il campo progetto:**
- Acquisti componenti per un progetto specifico
- Vuoi tracciare i costi per progetto
- Vuoi sapere quali fatture appartengono a quale progetto

**Quando NON usarlo:**
- Acquisti per magazzino generico
- Componenti di uso comune
- Non sai ancora a quale progetto saranno destinati

---

## Modifiche Tecniche

### File Modificati

#### 1. `app/Filament/Widgets/ActiveProjectsStatsWidget.php`
**Linea 51:** Cambiata icona da `o-circuit-board` a `o-cpu-chip`

#### 2. `app/Filament/Resources/ComponentResource/Pages/ListComponents.php`
**Linee 85-90:** Aggiunto campo Select per progetto:
```php
Forms\Components\Select::make('project_id')
    ->label('Progetto Destinazione (opzionale)')
    ->options(\App\Models\Project::pluck('name', 'id'))
    ->searchable()
    ->placeholder('Nessun progetto specifico')
    ->helperText('Se i componenti sono destinati a un progetto specifico, selezionalo qui.')
```

**Linea 122:** Aggiunto project_id a invoiceData:
```php
'project_id' => $data['project_id'] ?? null,
```

**Linee 152-157:** Aggiunta notifica progetto:
```php
if (!empty($data['project_id'])) {
    $project = \App\Models\Project::find($data['project_id']);
    if ($project) {
        $message .= "\n🎯 Progetto: {$project->name}";
    }
}
```

#### 3. `app/Services/ComponentImportService.php`
**Linea 581:** Aggiunto destination_project_id in batch insert:
```php
'destination_project_id' => $invoiceData['project_id'] ?? null,
```

**Linea 1533:** Aggiunto destination_project_id in single insert:
```php
'destination_project_id' => $invoiceData['project_id'] ?? null,
```

### Database

Il campo `destination_project_id` **esiste già** nella tabella `inventory_movements`:

```sql
inventory_movements:
  - destination_project_id (nullable)
  - invoice_number
  - invoice_path
  - invoice_date
  - invoice_total
  - supplier
  - notes
```

Relazione esistente: `destinationProject()` → `belongsTo(Project::class)`

Nessuna migrazione necessaria! ✅

---

## Esempi di Utilizzo

### Scenario 1: Acquisto per Progetto Specifico

**Situazione:** Acquisti componenti da Mouser specificamente per "Progetto Apollo"

**Workflow:**
1. Import → Carica CSV Mouser
2. Carica fattura MOUSER-2025-001
3. **Seleziona progetto:** "Progetto Apollo"
4. Import

**Risultato:**
- 50 componenti importati
- Fattura salvata in Nextcloud
- Tutti i movimenti collegati al "Progetto Apollo"
- Puoi vedere costi del progetto Apollo che includono questa fattura

### Scenario 2: Acquisto Magazzino Generico

**Situazione:** Acquisti resistenze e condensatori generici per magazzino

**Workflow:**
1. Import → Carica CSV DigiKey
2. Carica fattura DK-2025-002
3. **NON selezionare progetto** (lascia vuoto)
4. Import

**Risultato:**
- 100 componenti in magazzino generico
- Fattura salvata in Nextcloud
- Movimenti non collegati a nessun progetto (normale)

### Scenario 3: Acquisto Multi-Progetto

**Situazione:** Compri 500 resistenze, di cui 100 per Progetto Apollo, resto magazzino

**Workflow:**
1. **Prima import:** CSV con 100 resistenze → Progetto: "Apollo"
2. **Seconda import:** CSV con 400 resistenze → Progetto: (vuoto)
3. Due fatture separate (o dividi la fattura manualmente)

**Alternativa (consigliata):**
- Import tutto in magazzino generico (progetto vuoto)
- Poi assegna al progetto quando serve via "Project Component Allocation"

---

## Reportistica e Analisi

### Query Esempio: Costi per Progetto

```sql
SELECT
    p.name as project_name,
    im.invoice_number,
    im.invoice_date,
    im.invoice_total,
    im.supplier,
    COUNT(*) as components_count,
    SUM(im.quantity * im.unit_cost) as total_cost
FROM inventory_movements im
JOIN projects p ON p.id = im.destination_project_id
WHERE im.destination_project_id IS NOT NULL
GROUP BY p.name, im.invoice_number, im.invoice_date, im.invoice_total, im.supplier
ORDER BY im.invoice_date DESC;
```

### Filament: Filtro per Progetto

In **Inventory Movements**, puoi filtrare per:
- ✅ Progetto destinazione
- ✅ Numero fattura
- ✅ Fornitore
- ✅ Data fattura

E vedere tutti i componenti acquistati per quel progetto con le relative fatture.

---

## FAQ

### Q: Posso cambiare il progetto dopo l'import?
**A:** Sì! Vai su **Inventory Movements**, modifica il movimento, e cambia "Destination Project".

### Q: Cosa succede se non specifico un progetto?
**A:** Niente di male! I componenti vanno in magazzino generico. È l'uso normale per componenti comuni.

### Q: Posso collegare la stessa fattura a più progetti?
**A:** Tecnicamente sì, ma **sconsigliato**. Meglio dividere la fattura o importare in magazzino generico e poi allocare ai progetti.

### Q: Come vedo tutte le fatture di un progetto?
**A:** Vai su **Inventory Movements** → Filtra per "Destination Project" → Vedi colonna "Invoice Number".

### Q: Il collegamento progetto è retroattivo?
**A:** No, si applica solo ai nuovi import. Per import passati, puoi modificarli manualmente in Inventory Movements.

### Q: Posso collegare fatture che non sono import componenti?
**A:** Attualmente no, il collegamento fattura-progetto funziona solo durante l'import componenti. Per altre fatture usa il modulo "Fatture" separato.

---

## Vantaggi

### Prima (senza collegamento progetto)
❌ Non sapevi quali componenti erano per quale progetto
❌ Difficile calcolare costi reali per progetto
❌ Fatture generiche non collegate ai progetti

### Ora (con collegamento progetto)
✅ Tracci esattamente quali componenti sono per quale progetto
✅ Calcoli costi reali per progetto (materiali + fatture)
✅ Fatture collegate ai progetti per contabilità analitica
✅ Reportistica dettagliata per progetto
✅ Trasparenza totale: dalla fattura al prodotto finito

---

## Best Practices

### 1. Quando Usare il Collegamento Progetto

**✅ USA quando:**
- Acquisti componenti per un progetto specifico
- Vuoi tracciare costi per progetto
- Il progetto è in fase di preventivazione (vuoi costi reali)
- Devi rendicontare al cliente

**❌ NON usare quando:**
- Acquisti magazzino generico
- Componenti comuni (resistenze, condensatori standard)
- Non sai ancora l'uso finale

### 2. Organizzazione Fatture

**Consigliato:**
- Una fattura = un progetto (quando possibile)
- Fatture magazzino generico separate
- Numero fattura descrittivo: `MOUSER-2025-APOLLO-001`

### 3. Workflow Ideale

1. **Inizio progetto:** Crea il progetto in Filament
2. **Acquisto componenti:** Import con progetto selezionato
3. **Durante progetto:** Alloca componenti magazzino al progetto se serve
4. **Fine progetto:** Analisi costi (componenti + fatture collegati)

---

**Implementato:** 2025-10-07
**Versione:** 1.0
**File Modificati:** 3
**Database:** Nessuna migrazione necessaria (campo già esistente)
**Compatibilità:** Retrocompatibile (opzionale)
