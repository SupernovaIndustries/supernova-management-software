# Sistema di Notifiche Email per Cambi di Stato Progetti

## Panoramica

Il sistema di notifiche email invia automaticamente un'email al cliente quando lo stato di un progetto viene modificato. Questo sistema è completamente integrato con il `ProjectObserver` e supporta tutti gli stati dei progetti configurabili.

## Componenti Implementati

### 1. Mailable Class
**File:** `/app/Mail/ProjectStatusChangedMail.php`

Classe Mailable che gestisce la creazione e l'invio dell'email di notifica. Include:
- Subject line personalizzato in base al nuovo stato
- Traduzione degli stati in italiano
- Integrazione con CompanyProfile per dati aziendali

### 2. Email Template
**File:** `/resources/views/emails/project-status-changed.blade.php`

Template HTML responsive con:
- Design professionale e moderno
- Badge colorati per visualizzare il cambio di stato
- Informazioni dettagliate sul progetto
- Messaggi contestuali in base allo stato
- Footer con dati aziendali dal CompanyProfile

### 3. Observer Update
**File:** `/app/Observers/ProjectObserver.php`

Aggiornato per:
- Intercettare i cambi di stato nel metodo `updated()`
- Inviare email automaticamente quando lo stato cambia
- Loggare successi ed errori di invio

### 4. Comando di Test
**File:** `/app/Console/Commands/TestProjectStatusEmailCommand.php`

Comando artisan per testare l'invio delle email.

## Stati Supportati

Il sistema supporta tutti gli stati configurati in `ProjectResource`:

| Stato Tecnico | Label Italiano | Colore Badge | Messaggio Personalizzato |
|---------------|---------------|--------------|---------------------------|
| `planning` | In Pianificazione | Grigio | - |
| `in_progress` | In Corso | Blu | "Il progetto è ora in fase di sviluppo" |
| `testing` | In Fase di Test | Blu | "Stiamo effettuando tutti i controlli necessari" |
| `consegna_prototipo_test` | Consegna Prototipo Test | Giallo | "Il prototipo è pronto per i test" |
| `completed` | Completato | Verde | "Il progetto è stato completato con successo!" |
| `on_hold` | In Pausa | Giallo | "Il progetto è momentaneamente in pausa" |
| `cancelled` | Annullato | Rosso | "Il progetto è stato annullato" |

## Configurazione

### Prerequisiti

1. **Configurazione Email nel .env**
```env
MAIL_MAILER=smtp
MAIL_HOST=ssl0.ovh.net
MAIL_PORT=465
MAIL_USERNAME=gestionale@supernovaindustries.it
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=gestionale@supernovaindustries.it
MAIL_FROM_NAME="Supernova Industries"
```

2. **Email Cliente nel Progetto**
Il campo `client_email` deve essere compilato nel progetto per ricevere le notifiche.

### Campi Database Utilizzati

Dal modello `Project`:
- `client_email` - Email del cliente per le notifiche (obbligatorio)
- `status` - Stato corrente del progetto
- Altri campi visualizzati: `code`, `name`, `description`, `due_date`

## Come Funziona

1. **Cambio Stato**: Quando si modifica lo stato di un progetto tramite Filament
2. **Observer Triggered**: Il `ProjectObserver` intercetta la modifica nel metodo `updated()`
3. **Verifica Email**: Controlla se `client_email` è configurato
4. **Invio Email**: Invia email usando la classe `ProjectStatusChangedMail`
5. **Log**: Registra successo o errore in `/storage/logs/laravel.log`

## Testing

### Test Email Manuale

```bash
# Test con progetto esistente
php artisan project:test-status-email 1 test@example.com

# Test con stati specifici
php artisan project:test-status-email 1 test@example.com --old-status=planning --new-status=in_progress

# Test completamento progetto
php artisan project:test-status-email 1 test@example.com --old-status=testing --new-status=completed
```

### Test Email Configurazione Generale

```bash
php artisan email:test test@example.com
```

### Test Tramite Filament

1. Accedi al pannello admin Filament
2. Vai su Progetti → Seleziona un progetto
3. Assicurati che il campo `client_email` sia compilato
4. Cambia lo stato del progetto
5. Salva
6. Verifica i log in `/storage/logs/laravel.log`

## Contenuto Email

L'email include:

### Header
- Titolo professionale "Aggiornamento Stato Progetto"

### Badge Cambio Stato
- Visualizzazione visuale: `Vecchio Stato → Nuovo Stato`
- Colori distintivi per ogni stato

### Informazioni Progetto
- Codice Progetto
- Nome Progetto
- Nome Cliente
- Data Cambio
- Scadenza Prevista (se presente)
- Descrizione (se presente)

### Messaggio Contestuale
Messaggio personalizzato in base al nuovo stato:
- **In Corso**: Informazioni su sviluppo attivo
- **Testing**: Informazioni su fase di test
- **Prototipo Test**: Coordinamento consegna
- **Completato**: Messaggio di congratulazioni
- **In Pausa**: Comunicazione pausa temporanea
- **Annullato**: Disponibilità per chiarimenti

### Footer
- Firma con dati da CompanyProfile:
  - Nome responsabile
  - Titolo/ruolo
  - Nome azienda
  - Telefono
  - Email
  - Sito web

## Log e Debugging

### Log Successo
```
[timestamp] local.INFO: Project status change email sent
{
    "project_id": 1,
    "project_code": "PROJ-001",
    "old_status": "planning",
    "new_status": "in_progress",
    "client_email": "cliente@example.com"
}
```

### Log Errore
```
[timestamp] local.ERROR: Failed to send project status change email
{
    "project_id": 1,
    "project_code": "PROJ-001",
    "error": "Error message details"
}
```

### Visualizzare i Log
```bash
# Real-time log watching
tail -f storage/logs/laravel.log

# Filtrare solo notifiche progetto
tail -f storage/logs/laravel.log | grep "Project status"
```

## Estensioni Future

### 1. Stati Custom
Per aggiungere nuovi stati:

1. Aggiorna `ProjectResource.php` nella sezione `status->options()`
2. Aggiorna il metodo `getStatusLabel()` in `ProjectStatusChangedMail.php`
3. (Opzionale) Aggiungi messaggio personalizzato nel template Blade

### 2. Notifiche Multiple
Per inviare a più destinatari:

```php
// In ProjectObserver.php
if ($project->client_email) {
    $recipients = explode(',', $project->client_email);
    foreach ($recipients as $email) {
        Mail::to(trim($email))
            ->send(new ProjectStatusChangedMail($project, $oldStatus, $newStatus));
    }
}
```

### 3. CC/BCC Automatico
```php
Mail::to($project->client_email)
    ->cc('pm@supernovaindustries.it')
    ->bcc('archive@supernovaindustries.it')
    ->send(new ProjectStatusChangedMail($project, $oldStatus, $newStatus));
```

### 4. Queue per Performance
```php
// In ProjectObserver.php
Mail::to($project->client_email)
    ->queue(new ProjectStatusChangedMail($project, $oldStatus, $newStatus));
```

Richiede configurazione queue in `.env`:
```env
QUEUE_CONNECTION=redis
```

## Troubleshooting

### Email Non Inviata

1. **Verifica configurazione SMTP nel .env**
2. **Controlla i log**: `storage/logs/laravel.log`
3. **Testa configurazione email base**: `php artisan email:test test@example.com`
4. **Verifica `client_email` nel progetto**

### Email in Spam

1. Configura SPF record per il dominio
2. Configura DKIM
3. Usa email aziendale nel FROM
4. Verifica reputazione IP server SMTP

### Errori Comuni

**"Email configuration is incomplete"**
- Verifica tutti i campi email nel CompanyProfile

**"Connection refused"**
- Verifica MAIL_HOST e MAIL_PORT
- Controlla firewall

**"Authentication failed"**
- Verifica MAIL_USERNAME e MAIL_PASSWORD

## File Modificati/Creati

### Creati
- `/app/Mail/ProjectStatusChangedMail.php`
- `/resources/views/emails/project-status-changed.blade.php`
- `/app/Console/Commands/TestProjectStatusEmailCommand.php`
- `/PROJECT-STATUS-NOTIFICATIONS.md`

### Modificati
- `/app/Observers/ProjectObserver.php`

## Supporto

Per problemi o domande:
1. Verifica questa documentazione
2. Controlla i log in `storage/logs/laravel.log`
3. Usa il comando di test per debugging
4. Verifica configurazione email in CompanyProfile
