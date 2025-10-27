# Test Import Progress System

## Quick Test Checklist

### Pre-requisiti
- [ ] Redis server attivo: `redis-cli ping` → PONG
- [ ] Queue worker avviato: `php artisan queue:work --queue=default --tries=1 --timeout=3600`
- [ ] Applicazione Laravel attiva: `php artisan serve` o Docker

### Test 1: Syntax Check (PASSED ✅)
```bash
php -l app/Jobs/ImportComponentsJob.php
php -l app/Livewire/ImportProgress.php
php -l app/Services/ComponentImportService.php
php -l app/Filament/Resources/ComponentResource/Pages/ListComponents.php
```

**Risultato**: Tutti i file senza errori di sintassi

### Test 2: Route Registration (PASSED ✅)
```bash
php artisan route:list | grep import-progress
```

**Risultato**: Route `/admin/import-progress/{jobId}` registrata correttamente

### Test 3: Avvio Queue Worker

**Comando**:
```bash
# In un terminale separato
php artisan queue:work --queue=default --tries=1 --timeout=3600 --verbose
```

**Verifica**:
- [ ] Worker si avvia senza errori
- [ ] Output mostra "Processing jobs from the [default] queue"

### Test 4: Import Componenti (UI Test)

1. **Accedi a Filament**
   - URL: `http://localhost/admin/components`
   - Login con credenziali admin

2. **Click "Import Componenti"**
   - Verifica apertura modal

3. **Compila Form**
   - Tab "File Componenti":
     - [ ] Upload file CSV/Excel (esempio: file Mouser/DigiKey)
     - [ ] Seleziona fornitore
   - Tab "Fattura d'Acquisto":
     - [ ] Upload fattura PDF
     - [ ] Numero fattura: INV-TEST-001
     - [ ] Data fattura: oggi
     - [ ] Totale: 100.00

4. **Submit Import**
   - [ ] Notifica verde "Import avviato in background"
   - [ ] Presente link "Monitora Progresso"
   - [ ] Job ID visibile (es: import_67058...)

5. **Click "Monitora Progresso"**
   - [ ] Si apre nuova tab con pagina progress
   - [ ] Mostra Job ID
   - [ ] Progress bar visibile

6. **Osserva Progress**
   - [ ] Progress bar si aggiorna ogni secondo
   - [ ] Percentuale incrementa
   - [ ] Messaggio "Processati X/Y componenti"
   - [ ] Contatore aggiornato

7. **Completamento**
   - [ ] Progress bar arriva a 100%
   - [ ] Box verde "Import completato!"
   - [ ] Mostra statistiche: Importati/Aggiornati/Falliti
   - [ ] Mostra "Fattura collegata con N movimenti"

### Test 5: Chiusura/Riapertura Pagina

1. **Durante Import**:
   - [ ] Chiudi tab progress
   - [ ] Riapri cliccando link dalla notifica
   - [ ] Progress ancora visibile e aggiornato

2. **Verifica Cache**:
   ```bash
   redis-cli --scan --pattern "import_progress_*"
   ```
   - [ ] Chiave presente in Redis

### Test 6: Queue Worker Logs

**Controlla output worker**:
```bash
# Nel terminale del worker
# Dovresti vedere:
[2024-10-08 16:00:00][job-id] Processing: App\Jobs\ImportComponentsJob
[2024-10-08 16:05:00][job-id] Processed: App\Jobs\ImportComponentsJob
```

**Controlla Laravel logs**:
```bash
tail -50 storage/logs/laravel.log
```

Cerca:
- [ ] "Excel/CSV import started"
- [ ] "AI category generated" (se AI attiva)
- [ ] "Batch inventory movements created"
- [ ] Nessun errore critico

### Test 7: Database Verification

```bash
# Componenti importati
php artisan tinker
>>> \App\Models\Component::latest()->take(5)->get(['sku', 'manufacturer_part_number', 'created_at'])

# Movimenti inventario creati
>>> \App\Models\InventoryMovement::latest()->take(5)->get(['component_id', 'quantity', 'invoice_number'])
```

- [ ] Componenti presenti in DB
- [ ] Movimenti inventario con invoice_number corretto

### Test 8: Nextcloud Verification

**Verifica upload fattura**:
- Path: `Magazzino/Fatture_Magazzino/Fornitori/{YEAR}/{SUPPLIER}_{DATE}_{INVOICE_NUMBER}.pdf`
- [ ] File presente in Nextcloud
- [ ] Nome file corretto
- [ ] Contenuto leggibile

### Test 9: Performance Test

**Import Piccolo (10-20 componenti)**:
- Tempo atteso: 30-60 secondi
- [ ] Progress aggiornato correttamente
- [ ] Completato senza errori

**Import Grande (100+ componenti)** (se disponibile):
- Tempo atteso: 5-15 minuti
- [ ] Worker non va in timeout
- [ ] Progress incrementale
- [ ] Memoria non esaurita

### Test 10: Error Handling

**Scenario 1: File malformato**
- Upload file CSV corrotto
- [ ] Job fallisce gracefully
- [ ] Box rosso "Import fallito"
- [ ] Messaggio errore comprensibile

**Scenario 2: Worker non attivo**
- Stop queue worker
- Avvia import
- [ ] Job entra in coda
- [ ] Quando worker riavviato, job processato

## Troubleshooting durante Test

### Problema: Progress resta "Caricamento..."

**Possibili cause**:
1. Queue worker non attivo
2. Job non ancora partito
3. Cache non accessibile

**Debug**:
```bash
# Check worker
ps aux | grep "queue:work"

# Check Redis
redis-cli ping

# Check job in queue
php artisan queue:work --once

# View cache
redis-cli get "import_progress_{JOB_ID}"
```

### Problema: Job fallisce immediatamente

**Debug**:
```bash
# View failed jobs
php artisan queue:failed

# View error details
php artisan tinker
>>> \Illuminate\Support\Facades\DB::table('failed_jobs')->latest()->first()

# Check logs
tail -100 storage/logs/laravel.log
```

### Problema: Progress non si aggiorna

**Possibili cause**:
1. Livewire polling non funziona
2. Browser cache issues
3. JavaScript errors

**Debug**:
- F12 → Console (cercare errori JS)
- F12 → Network (verificare richieste Livewire)
- Hard refresh: Ctrl+Shift+R

## Test Results Template

Copia e compila:

```
## Test Results - [DATA]

### Environment
- OS: macOS/Linux/Windows
- PHP: 8.x
- Laravel: 11.x
- Redis: 7.x
- Browser: Chrome/Firefox/Safari

### Test 1: Syntax Check
Status: ✅ PASSED / ❌ FAILED
Notes:

### Test 2: Route Registration
Status: ✅ PASSED / ❌ FAILED
Notes:

### Test 3: Queue Worker
Status: ✅ PASSED / ❌ FAILED
Notes:

### Test 4: Import UI
Status: ✅ PASSED / ❌ FAILED
Notes:

### Test 5: Progress Tracking
Status: ✅ PASSED / ❌ FAILED
Notes:

### Test 6: Completion
Status: ✅ PASSED / ❌ FAILED
Notes:

### Issues Found
1.
2.

### Performance Metrics
- Components imported:
- Time taken:
- Memory used:
- Progress updates: smooth / laggy
```

## Production Deployment Checklist

Prima di deployare in produzione:

- [ ] Configura Supervisor per queue worker
- [ ] Test con dataset reale (1000+ componenti)
- [ ] Monitor memory usage
- [ ] Test Redis persistence
- [ ] Backup database prima di import grandi
- [ ] Documenta procedura rollback
- [ ] Alert su failed jobs
- [ ] Log rotation configurato

## Success Criteria

Il sistema è considerato **FUNZIONANTE** se:

1. ✅ Import parte in background
2. ✅ Progress bar si aggiorna ogni secondo
3. ✅ Percentuale accurata
4. ✅ Import completa con successo
5. ✅ Componenti salvati in DB
6. ✅ Fattura salvata su Nextcloud
7. ✅ Movimenti inventario creati
8. ✅ Nessun timeout
9. ✅ Pagina chiudibile/riapribile
10. ✅ Gestione errori corretta

---

**Data Test**: ________________
**Tester**: ________________
**Risultato Finale**: ✅ PASS / ❌ FAIL
**Note**: ________________________________
