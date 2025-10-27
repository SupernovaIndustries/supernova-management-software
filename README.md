# Supernova Management Software

Sistema completo per la gestione aziendale di Supernova, sviluppato con Laravel 11 e Filament v3.

## 🚀 Quick Start

### Prerequisiti
- Docker e Docker Compose installati
- Git

### Installazione

1. Clona il repository:
```bash
git clone <repository-url>
cd supernova-management
```

2. Copia il file di environment:
```bash
cp .env.example .env
```

3. Avvia l'installazione automatica:
```bash
./docker-init.sh
```

Questo comando:
- Costruisce i container Docker
- Installa Laravel
- Installa Filament v3
- Configura il database
- Esegue le migrazioni

### Accesso all'applicazione

- **Applicazione**: http://localhost
- **Mailpit (Email testing)**: http://localhost:8025
- **Meilisearch**: http://localhost:7700

## 🔧 Configurazione Environment

Il sistema è completamente modulare e supporta diversi ambienti:

### Sviluppo Windows
```env
SYNCTHING_ROOT_PATH=G:\Supernova
```

### Produzione VPS OVH
```env
SYNCTHING_ROOT_PATH=/opt/supernova-data
```

## 📁 Struttura Syncthing

Il sistema integra automaticamente la struttura esistente di Syncthing:

- `Clienti/` - Gestione clienti e progetti
- `Documenti SRL/` - Documentazione aziendale
- `Magazzino/` - Inventario componenti
- `Modelli Documenti/` - Template documenti
- `Prototipi/` - Progetti prototipo

## 🛠️ Comandi Utili

### Docker
```bash
# Avvia i container
docker compose up -d

# Ferma i container
docker compose down

# Visualizza i log
docker compose logs -f

# Accedi al container PHP
docker compose exec app bash
```

### Artisan
```bash
# Verifica stato Syncthing
docker compose exec app php artisan syncthing:status

# Setup directory Syncthing
docker compose exec app php artisan syncthing:setup

# Migrazioni database
docker compose exec app php artisan migrate

# Clear cache
docker compose exec app php artisan cache:clear
```

## 🏗️ Architettura

- **Backend**: Laravel 11 con PHP 8.3
- **Admin Panel**: Filament v3
- **Database**: PostgreSQL 15
- **Cache/Queue**: Redis
- **Search**: Meilisearch
- **Mail Testing**: Mailpit
- **Web Server**: Nginx

## 📚 Documentazione

- [Laravel Documentation](https://laravel.com/docs)
- [Filament Documentation](https://filamentphp.com/docs)
- [Docker Documentation](https://docs.docker.com/)

## 🔐 Sicurezza

- Tutte le variabili sensibili sono gestite tramite `.env`
- I percorsi dei file sono completamente configurabili
- Storage privato per documenti sensibili
- Autenticazione e autorizzazione gestite da Filament

## 🚀 Deploy su VPS OVH

1. Configura le variabili d'ambiente per produzione
2. Aggiorna `SYNCTHING_ROOT_PATH` nel `.env`
3. Esegui le migrazioni
4. Configura SSL con Certbot
5. Attiva le ottimizzazioni Laravel:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```