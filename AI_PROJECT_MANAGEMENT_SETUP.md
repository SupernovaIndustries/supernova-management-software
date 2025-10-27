# Sistema AI Project Management - Setup Completo

## Overview

Sistema completo di gestione intelligente progetti con AI, auto-generazione milestone, notifiche email automatiche, e analisi priorità.

## Features Implementate

### 1. Auto-Generazione Milestone alla Creazione Progetto
- Milestone generate automaticamente dall'AI quando si crea un progetto con descrizione
- Date "rilassate" con moltiplicatore 1.5x per margini di sicurezza
- Milestone automaticamente allegate al progetto
- Visibili nella email di creazione progetto

**File modificati:**
- `app/Observers/ProjectObserver.php` - Esecuzione sincrona generazione milestone
- `app/Jobs/GenerateProjectMilestones.php` - Applicazione moltiplicatore 1.5x per date rilassate
- `app/Mail/ProjectStatusChangedMail.php` - Inclusione milestone nella email
- `resources/views/emails/project-status-changed.blade.php` - Sezione milestone nel template

### 2. Email Completamento Milestone
- Email automatica quando una milestone viene completata
- Include milestone successiva automaticamente segnata "in progress"
- Percentuale completamento progetto calcolata automaticamente
- Sempre inviata ad admin + cliente

**File creati:**
- `app/Mail/MilestoneCompletedMail.php` - Mailable per email completamento
- `resources/views/emails/milestone-completed.blade.php` - Template HTML email con progress bar

**File modificati:**
- `app/Filament/Resources/ProjectResource/RelationManagers/MilestonesRelationManager.php` - Logica invio email e calcolo percentuale

### 3. Auto-Calcolo Percentuale Progetto
- Campo `completion_percentage` aggiunto alla tabella projects
- Calcolo automatico: (milestone completate / totale milestone) * 100
- Aggiornamento automatico status progetto:
  - 0% → 'planning'
  - 1-99% → 'in_progress'
  - 100% → 'completed'

**File modificati:**
- `app/Models/Project.php` - Metodi `updateCompletionPercentage()`, `calculateCompletionPercentage()`
- `app/Observers/ProjectObserver.php` - Auto-update status basato su percentuale

### 4. Gestione Scheda Test
Workflow completo:
- Prima scheda prodotta (`boards_produced >= 1`) = scheda test
- Action Filament "Approva Prima Scheda Test"
- Quando approvata: incrementa `boards_assembled` a 1
- Completa automaticamente milestone "testing"
- Ricalcola percentuale progetto

**File modificati:**
- `app/Filament/Resources/ProjectResource.php` - Action `approve_test_board`
- `app/Observers/ProjectObserver.php` - Tracking prima scheda prodotta

### 5. Gestione Priorità Intelligente AI
Sistema completo di analisi priorità progetti con AI.

**Algoritmo calcolo priorità (score 0-100):**
- **40% - Giorni a scadenza:** Overdue=100, <7gg=90, <14gg=70, <30gg=50, >30gg=30
- **30% - Milestone rimanenti:** >5=80, 3-5=60, 1-2=40, 0=20
- **20% - Sovrapposizioni temporali:** ≥5 progetti=100, ≥3=70, ≥2=50, ≥1=30
- **10% - Budget cliente:** >50k€=80, >20k€=60, standard=40

**File creati:**
- `app/Services/ProjectManagementAiService.php` - Servizio calcolo priorità con AI
- `app/Console/Commands/CalculateProjectPriorities.php` - Comando artisan

**Comando:**
```bash
php artisan projects:calculate-priorities
php artisan projects:calculate-priorities --show-details
```

### 6. Calendario Intelligente (Widget Timeline)
Widget Filament per visualizzazione timeline progetti con priorità AI.

**Features:**
- Timeline progetti ordinata per priorità AI
- Badge priorità con colori (90+=rosso, 70+=giallo, 50+=blu, <50=verde)
- Progress bar con marker "oggi"
- Alert scadenze imminenti (7 giorni)
- Milestone prossime per progetto
- Rilevamento sovrapposizioni temporali

**File creati:**
- `app/Filament/Widgets/ProjectsTimelineWidget.php` - Widget PHP
- `resources/views/filament/widgets/projects-timeline-widget.blade.php` - Vista Blade

**Utilizzo:**
Aggiungere al dashboard in `app/Filament/Pages/Dashboard.php`:
```php
protected function getWidgets(): array
{
    return [
        ProjectsTimelineWidget::class,
    ];
}
```

## Database Changes

**Migration:** `2025_10_06_180000_add_ai_fields_to_projects_table.php`

Campi aggiunti alla tabella `projects`:
- `completion_percentage` (decimal 5,2) - Percentuale completamento basata su milestone
- `ai_priority_score` (integer) - Score priorità calcolato da AI (1-100)
- `ai_priority_data` (json) - Dati dettagliati calcolo priorità
- `ai_priority_calculated_at` (timestamp) - Timestamp ultimo calcolo

## Setup e Configurazione

### 1. Eseguire Migration
```bash
php artisan migrate
```

### 2. Abilitare Auto-Generazione Milestone
Nel CompanyProfile, assicurarsi che `auto_generate_milestones` sia `true`.

### 3. Configurare AI Service
Il sistema usa il servizio AI già configurato (Ollama/Claude).

Verificare in `.env`:
```env
AI_PROVIDER=ollama
OLLAMA_API_URL=http://localhost:11434
OLLAMA_MODEL=llama3.1:8b
```

### 4. Test AI Service
```bash
php artisan ai:test-milestone-generation
```

### 5. Calcolare Priorità Progetti
Eseguire manualmente o schedulare:
```bash
php artisan projects:calculate-priorities
```

### 6. Schedule (Opzionale)
Aggiungere in `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule): void
{
    // Calcola priorità ogni giorno alle 6:00
    $schedule->command('projects:calculate-priorities')
        ->dailyAt('06:00');
}
```

## Testing

### Test Creazione Progetto con Milestone
1. Creare nuovo progetto con descrizione dettagliata
2. Verificare che milestone siano generate automaticamente
3. Controllare email ricevuta con lista milestone
4. Log: `/storage/logs/laravel.log`

### Test Completamento Milestone
1. Aprire progetto con milestone
2. Nella tab "Milestone del Progetto", cliccare "Segna Completata"
3. Verificare:
   - Email inviata con milestone completata
   - Milestone successiva mostrata
   - Percentuale progetto aggiornata
4. Controllare Log

### Test Approvazione Scheda Test
1. Progetto con `boards_produced >= 1` e `boards_assembled = 0`
2. Action "Approva Prima Scheda Test" visibile nella lista progetti
3. Cliccare e confermare
4. Verificare:
   - `boards_assembled` incrementato a 1
   - Milestone "testing" completata se esiste
   - Percentuale aggiornata

### Test Calcolo Priorità
```bash
php artisan projects:calculate-priorities --show-details
```

Verificare:
- Score calcolato per ogni progetto
- Progetti ordinati per priorità
- Alert per progetti con score >= 80
- Suggerimenti AI (se Ollama attivo)

### Test Widget Timeline
1. Accedere al dashboard Filament
2. Verificare widget "Timeline Progetti Intelligente"
3. Controllare:
   - Progetti ordinati per priorità AI
   - Progress bar corrette
   - Alert scadenze imminenti
   - Sovrapposizioni evidenziate

## Email Templates

### Email Completamento Milestone
Template: `resources/views/emails/milestone-completed.blade.php`

Contiene:
- Milestone completata con data
- Progress bar animata con percentuale
- Prossima milestone con data target
- Informazioni progetto

### Email Creazione Progetto (aggiornata)
Template: `resources/views/emails/project-status-changed.blade.php`

Aggiunto:
- Sezione milestone generate
- Lista milestone con date target
- Badge categoria per ogni milestone

## Troubleshooting

### Le milestone non vengono generate
- Verificare che `CompanyProfile::current()->auto_generate_milestones` sia `true`
- Verificare AI service configurato: `php artisan ai:test-milestone-generation`
- Controllare log: `storage/logs/laravel.log`

### Email non inviate
- Verificare configurazione mail in `.env`
- Email admin: `alessandro.cursoli@supernovaindustries.it`
- Email cliente: da campo `client_email` o `customer.email`

### Priorità non calcolate
- Eseguire comando manualmente: `php artisan projects:calculate-priorities`
- Verificare progetti abbiano `start_date` e `due_date`
- Controllare log per errori

### Widget non visibile
- Cache: `php artisan optimize:clear`
- Verificare widget aggiunto al dashboard
- Controllare permessi utente

## Performance

### Ottimizzazioni Implementate
- Generazione milestone sincrona alla creazione (evita ritardi email)
- Calcolo priorità on-demand (comando artisan)
- Widget con eager loading (previene N+1 queries)
- AI timeout 5 minuti per operazioni lunghe

### Limiti
- Generazione milestone: max 120 secondi (timeout job)
- AI priority calculation: progetti attivi solo
- Widget timeline: max 50 progetti consigliati

## Log e Debugging

Tutti gli eventi loggati in `storage/logs/laravel.log`:
- `GenerateProjectMilestones job started`
- `Milestones generated successfully`
- `Milestone completed email sent`
- `Project completion percentage updated`
- `AI project priority calculation completed`

## Sicurezza

- Solo admin può calcolare priorità (comando artisan)
- Email sempre CC admin
- Validazione input su tutte le action Filament
- Transaction DB per operazioni multiple
- Log completo per audit trail

## Manutenzione

### Pulizia Periodica
```bash
# Ricalcola tutte le percentuali (se necessario)
php artisan tinker
>>> \App\Models\Project::active()->each->updateCompletionPercentage();

# Pulisce calcoli priorità vecchi (>30 giorni)
>>> \App\Models\Project::where('ai_priority_calculated_at', '<', now()->subDays(30))
    ->update(['ai_priority_score' => null, 'ai_priority_data' => null]);
```

### Backup
Assicurarsi di includere nei backup:
- Tabella `projects` (nuovi campi AI)
- Tabella pivot `project_milestone` (completamenti)
- Email log per audit

## Credits

Implementato da Claude Code per Supernova Industries S.R.L.
Data: 2025-10-06
