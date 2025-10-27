# Script di Gestione Supernova Management

## 📁 Script Disponibili

Sono disponibili script sia per Windows (.bat) che per Linux/WSL2 (.sh) con le stesse funzionalità.

### Per Windows:
- `start-supernova.bat` - Avvia l'applicazione
- `stop-supernova.bat` - Ferma l'applicazione
- `check-supernova.bat` - Diagnostica e monitoraggio
- `fix-supernova.bat` - Risoluzione problemi

### Per Linux/WSL2:
- `start-supernova.sh` - Avvia l'applicazione
- `stop-supernova.sh` - Ferma l'applicazione
- `check-supernova.sh` - Diagnostica e monitoraggio
- `fix-supernova.sh` - Risoluzione problemi

## 🐧 Utilizzo su Linux/WSL2

### Prima volta:
```bash
# Rendi eseguibili gli script (già fatto)
chmod +x *.sh

# Avvia l'applicazione
./start-supernova.sh
```

### Comandi rapidi:
```bash
# Avvio
./start-supernova.sh

# Stop
./stop-supernova.sh

# Diagnostica
./check-supernova.sh

# Fix problemi
./fix-supernova.sh
```

## 🪟 Utilizzo su Windows

Basta fare doppio click sui file `.bat` oppure da CMD/PowerShell:
```cmd
start-supernova.bat
stop-supernova.bat
check-supernova.bat
fix-supernova.bat
```

## 🔧 Funzionalità degli Script

### 1. **Start Script** (`start-supernova`)
- ✅ Verifica Docker
- ✅ Avvia tutti i container
- ✅ Attende servizi pronti
- ✅ Esegue migrazioni
- ✅ Pulisce cache
- ✅ Apre browser automaticamente

### 2. **Stop Script** (`stop-supernova`)
- ✅ Ferma tutti i container
- ✅ Opzione per rimuovere volumi
- ✅ Gestione errori

### 3. **Check Script** (`check-supernova`)
Menu interattivo per:
1. Stato container
2. Log Laravel
3. Log PostgreSQL
4. Log Nginx
5. Log Redis
6. Test database
7. Comandi Artisan
8. Shell interattiva
9. Riavvio servizi

### 4. **Fix Script** (`fix-supernova`)
Risoluzione problemi:
1. Errore 504 Gateway Timeout
2. Errori database
3. Errore 500/pagina bianca
4. Container non avviabili
5. Permessi file
6. Reset completo
7. Backup database
8. Restore database

## 📝 Note Importanti

### Per WSL2:
- Assicurati che Docker Desktop sia in esecuzione su Windows
- Gli script usano il path `/mnt/g/` per accedere al drive G:
- I comandi browser aprono automaticamente in Windows

### Per Ubuntu nativo:
- Docker deve essere installato e avviato
- Modifica il path da `/mnt/g/` al tuo path locale se necessario

### Differenze principali tra .bat e .sh:
- Output colorato negli script Linux
- Gestione migliore degli errori in Linux
- Apertura browser compatibile con WSL2

## 🚀 Quick Start

### Windows:
```cmd
REM Avvia tutto
start-supernova.bat

REM Se hai problemi
fix-supernova.bat
```

### Linux/WSL2:
```bash
# Avvia tutto
./start-supernova.sh

# Se hai problemi
./fix-supernova.sh
```

## 🆘 Troubleshooting

Se gli script non funzionano:

1. **Permission denied** (Linux):
   ```bash
   chmod +x *.sh
   ```

2. **Docker not found**:
   - Windows: Avvia Docker Desktop
   - Linux: `sudo systemctl start docker`

3. **Path not found**:
   - Verifica di essere nella directory corretta
   - Modifica i path negli script se necessario

4. **504 Gateway Timeout**:
   - Usa fix-supernova (.bat o .sh)
   - Scegli opzione 1