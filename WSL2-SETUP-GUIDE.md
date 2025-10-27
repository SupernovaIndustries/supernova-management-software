# 🚀 Setup WSL2 per Supernova Management

## Perché WSL2 è Perfetto per Testing

✅ **Mini VM Linux** identica al VPS di produzione  
✅ **Molto più veloce** di Docker su Windows  
✅ **Accesso diretto** ai file Windows via `/mnt/`  
✅ **Port forwarding automatico** - accedi da Windows  
✅ **Ambiente identico** al server di produzione  
✅ **Avvio/stop rapido** con script automatici  

## 📋 Setup Iniziale (Una Volta Sola)

### 1. Abilita WSL2 su Windows
```powershell
# PowerShell come Administrator
wsl --install
# Riavvia Windows
```

### 2. Installa Ubuntu 22.04
```powershell
wsl --install -d Ubuntu-22.04
# Crea username e password quando richiesto
```

### 3. Setup Automatico Servizi
```bash
# Entra in WSL2
wsl

# Vai alla cartella del progetto (accessibile da WSL2)
cd /mnt/g/Supernova/supernova-management

# Rendi eseguibili gli script
chmod +x *.sh

# SETUP COMPLETO AUTOMATICO (una volta sola)
./wsl2-setup.sh
```

Lo script `wsl2-setup.sh` installa automaticamente:
- 🐘 **PostgreSQL 15** con database `supernova`
- 🔴 **Redis** per cache e queue
- 🔍 **MeiliSearch** per ricerca
- 📧 **MailPit** per testing email
- 🌐 **Nginx** web server
- 🐘 **PHP 8.3** con tutte le estensioni
- 📦 **Composer** e **Node.js 20**

## 🎮 Uso Quotidiano (Super Facile)

### Avvio Mini VM
```bash
# Da Windows, apri WSL2
wsl

# Vai al progetto
cd /mnt/g/Supernova/supernova-management

# AVVIA TUTTO (in 10 secondi)
./start-supernova-wsl2.sh
```

### Accesso App
- **App Laravel**: http://localhost  
- **MailPit UI**: http://localhost:8025  
- **MeiliSearch**: http://localhost:7700  

### Setup Progetto Laravel (Prima Volta)
```bash
# Copia progetto in WSL2 (più veloce) 
cp -r /mnt/g/Supernova/supernova-management ~/supernova-management
cd ~/supernova-management

# Configura environment
cp .env.wsl2 .env

# Installa dipendenze
composer install
npm install

# Setup database
php artisan migrate --seed

# Avvia frontend
npm run dev
```

### Stop Mini VM
```bash
./stop-supernova-wsl2.sh
```

### Check Status
```bash
./status-supernova-wsl2.sh
```

## 🔧 Configurazione Environment

Il file `.env.wsl2` è già configurato per:
- **Database**: PostgreSQL su `localhost:5432`
- **Cache**: Redis su `localhost:6379`  
- **Search**: MeiliSearch su `localhost:7700`
- **Mail**: MailPit su `localhost:1025`
- **Files**: Accesso diretto a `G:\Supernova` via `/mnt/g/Supernova`

## 📁 Struttura File

```
Windows: G:\Supernova\supernova-management\
WSL2:    /mnt/g/Supernova/supernova-management/  (stesso progetto)
Copy:    ~/supernova-management/                  (copia veloce per dev)
```

## 🚀 Vantaggi vs Docker

| Feature | WSL2 | Docker |
|---------|------|--------|
| **Startup** | 10 sec | 2-3 min |
| **Performance** | Nativo | Virtualizzato |
| **File I/O** | Veloce | Lento |
| **Memory** | Condivisa | Isolata |
| **Debugging** | Diretto | Port mapping |
| **Production-like** | ✅ 100% | ⚠️ Simile |

## 🔄 Workflow di Sviluppo

### Sviluppo Locale
```bash
# Avvia WSL2
wsl
cd ~/supernova-management

# Avvia servizi
./start-supernova-wsl2.sh

# Sviluppa normalmente
php artisan serve  # se non usi nginx
npm run dev
php artisan test
```

### Testing Pre-Produzione
```bash
# Test identico al VPS
php artisan migrate:fresh --seed
php artisan config:cache
php artisan route:cache
npm run build
```

### Deploy VPS
Poiché WSL2 usa **Ubuntu 22.04**, il codice testato funzionerà **identicamente** sul VPS Ubuntu!

## 🛠️ Troubleshooting

### WSL2 non avvia
```powershell
# Windows PowerShell
wsl --shutdown
wsl --unregister Ubuntu-22.04
wsl --install -d Ubuntu-22.04
```

### Servizi non partono
```bash
# Check logs
sudo journalctl -f -u postgresql
sudo journalctl -f -u redis-server
sudo journalctl -f -u meilisearch
```

### Reset completo
```bash
# Reinstalla tutto
./wsl2-setup.sh
```

### Performance issues
```bash
# Ottimizza WSL2 memory
echo '[wsl2]
memory=4GB
processors=4' > /mnt/c/Users/$(whoami)/.wslconfig
```

## 🎯 Risultato Finale

Hai una **mini VM Linux** che:
- ✅ Si avvia in **10 secondi**  
- ✅ È **identica al VPS** di produzione  
- ✅ Ha **performance native**  
- ✅ Accede ai **file Windows**  
- ✅ È **perfetta per testing**  
- ✅ Si **spegne/accende** facilmente  

**Ideale per sviluppo e testing prima del deploy VPS!** 🚀