# Setup Supernova Management con Laragon

## 📋 Checklist Pre-Installazione

### 1. Download e Installazione Laragon
- Scarica Laragon Full da [laragon.org](https://laragon.org/)
- Installa in `C:\laragon` (percorso predefinito)
- Avvia Laragon come Administrator

### 2. Configurazione PHP
```bash
# Verifica versione PHP (deve essere 8.3+)
php --version

# In Laragon, vai a Menu > PHP > Versione > php-8.3.x se disponibile
# O usa la versione inclusa (8.1+ va bene)
```

### 3. Database Setup MySQL
```sql
-- Laragon include già MySQL
-- Avvia tutti i servizi: Laragon > Start All
-- Accedi a phpMyAdmin: http://localhost/phpmyadmin
-- Credenziali default: user=root, password=vuota

CREATE DATABASE supernova CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 🚀 Migrazione Progetto

### 1. Copia Progetto
```bash
# Copia la cartella supernova-management in C:\laragon\www\
cp -r /path/to/supernova-management C:\laragon\www\
```

### 2. Configurazione Environment
```bash
cd C:\laragon\www\supernova-management

# Copia il file .env ottimizzato per Laragon
cp .env.laragon .env

# Installa dipendenze
composer install
npm install
```

### 3. Database Migration
```bash
# Genera chiave applicazione se necessario
php artisan key:generate

# Esegui migrazioni
php artisan migrate

# Seeding (opzionale)
php artisan db:seed
```

### 4. Storage e Assets
```bash
# Crea link simbolici per storage
php artisan storage:link

# Compila assets
npm run build
# O per sviluppo:
npm run dev
```

### 5. Configurazione Pretty URLs
```bash
# In Laragon: Menu > Apache > Sites & Ports > Add
# Site Name: supernova-management
# Document Root: C:\laragon\www\supernova-management\public
```

## 🎨 Configurazione Tema Supernova

### Palette Colori Implementata
- **Primary Gradient:** #0A1A1A → #1A4A4A → #2A6A6A → #00BFBF
- **Secondary:** #00FFFF, #40E0D0  
- **Accent:** #20B2AA, #48D1CC
- **Background:** #0A1A1A con sfere luminose teal/cyan
- **Text:** #FFFFFF, #E0FFFF

### Variabili Environment per Theming
Tutte le variabili colore sono configurate nel `.env.laragon`:
```env
THEME_PRIMARY_DARK=#0A1A1A
THEME_PRIMARY_BRIGHT=#00BFBF
THEME_SECONDARY_AQUA=#00FFFF
# ... altre configurazioni
```

## ⚡ Performance Optimizations

### Configurazioni Laragon
```ini
# php.ini customizations (Laragon > Menu > PHP > php.ini)
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
```

### Laravel Optimizations
```bash
# Cache delle configurazioni
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Per development, pulisci cache quando necessario:
php artisan optimize:clear
```

## 🔧 Servizi Richiesti

### Servizi Laragon Attivi (Base)
- ✅ Apache
- ✅ PHP 8.1+ (ideale 8.3)
- ✅ MySQL 8.0
- ✅ Cache: File-based (no Redis needed)
- ✅ Search: Database-based (no MeiliSearch needed)
- ✅ Mail: Log-based (per sviluppo)

### URL di Accesso
- **App:** https://supernova-management.test
- **phpMyAdmin:** http://localhost/phpmyadmin
- **Laragon Dashboard:** http://localhost

## 🚨 Troubleshooting

### Problemi Comuni

#### 1. Errore Database Connection
```bash
# Verifica che MySQL sia avviato
# Laragon > Start All

# Controlla credenziali in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_USERNAME=root
DB_PASSWORD=
```

#### 2. Cache Issues
```bash
# Pulisci cache se necessario
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

#### 3. Permessi File
```bash
# Fix permessi storage e bootstrap
chmod -R 755 storage bootstrap/cache
```

#### 4. SSL Certificate Issues
```bash
# Rigenera certificati SSL in Laragon
# Menu > Nginx/Apache > ssl > Regenerate certificates
```

## 📊 Monitoring & Logs

### Log Files
- **Laravel:** `storage/logs/laravel.log`
- **Laragon:** `C:\laragon\data\logs\`
- **PostgreSQL:** Via pgAdmin
- **Redis:** Via Redis Commander

### Performance Monitoring
```bash
# Verifica performance con Laravel Telescope (se installato)
php artisan telescope:install
php artisan migrate
```

## 🔄 Backup & Restore

### Database Backup
```bash
# Export database MySQL
mysqldump -h 127.0.0.1 -u root supernova > backup.sql

# Import database  
mysql -h 127.0.0.1 -u root supernova < backup.sql
```

### File Backup
```bash
# Backup completo progetto
tar -czf supernova-backup.tar.gz C:\laragon\www\supernova-management
```

---

**✨ Il tuo ambiente Laragon è pronto per lo sviluppo ad alte performance di Supernova Management!**