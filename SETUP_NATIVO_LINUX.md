# Setup Nativo Supernova Management (Senza Docker)

Questa guida permette di installare Supernova Management direttamente su Ubuntu/Debian/Raspberry Pi OS senza Docker.

## Requisiti di Sistema

- **Ubuntu 22.04+**, **Debian 11+**, o **Raspberry Pi OS**
- **4GB RAM** minimo (8GB consigliato)
- **20GB spazio disco** libero
- **Connessione internet** stabile

---

## Fase 1: Preparazione Sistema

### 1.1 Aggiornamento sistema
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip software-properties-common
```

### 1.2 Installazione PHP 8.3
```bash
# Aggiungi repository PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Installa PHP 8.3 e estensioni
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mysql php8.3-pgsql php8.3-xml php8.3-xmlrpc \
    php8.3-curl php8.3-gd php8.3-imagick php8.3-dev \
    php8.3-imap php8.3-mbstring php8.3-opcache \
    php8.3-soap php8.3-zip php8.3-intl php8.3-bcmath \
    php8.3-redis php8.3-xdebug
```

### 1.3 Installazione Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### 1.4 Installazione Node.js e NPM
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## Fase 2: Database e Cache

### 2.1 Installazione PostgreSQL
```bash
sudo apt install -y postgresql postgresql-contrib

# Avvia e abilita PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Configura database
sudo -u postgres psql <<EOF
CREATE USER supernova WITH PASSWORD 'password';
CREATE DATABASE supernova OWNER supernova;
GRANT ALL PRIVILEGES ON DATABASE supernova TO supernova;
\q
EOF
```

### 2.2 Installazione Redis
```bash
sudo apt install -y redis-server

# Configura Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Test Redis
redis-cli ping
```

### 2.3 Installazione Nginx
```bash
sudo apt install -y nginx

# Avvia e abilita Nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

---

## Fase 3: Configurazione Progetto

### 3.1 Clona/Copia il progetto
```bash
# Se da Git (sostituisci con il tuo repo)
# git clone https://github.com/tuo-repo/supernova-management.git

# Se copi da Windows
sudo mkdir -p /var/www/supernova-management
sudo chown -R $USER:$USER /var/www/supernova-management

# Copia i file del progetto in /var/www/supernova-management
```

### 3.2 Installa dipendenze PHP
```bash
cd /var/www/supernova-management

# Installa dipendenze Composer
composer install --optimize-autoloader --no-dev

# Genera chiave applicazione
cp .env.example .env
php artisan key:generate
```

### 3.3 Configura .env per setup nativo
```bash
# Modifica .env con i seguenti valori:
cat > .env << 'EOF'
APP_NAME="Supernova Management"
APP_ENV=production
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
APP_DEBUG=false
APP_URL=http://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=supernova
DB_USERNAME=supernova
DB_PASSWORD=password

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache & Session
BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Mail Configuration (opzionale)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@supernova.test"
MAIL_FROM_NAME="${APP_NAME}"

# File System Configuration
SYNCTHING_ROOT_PATH=/opt/supernova-data
SYNCTHING_CLIENTS_PATH=${SYNCTHING_ROOT_PATH}/Clienti
SYNCTHING_DOCUMENTS_PATH="${SYNCTHING_ROOT_PATH}/Documenti SRL"
SYNCTHING_WAREHOUSE_PATH=${SYNCTHING_ROOT_PATH}/Magazzino
SYNCTHING_TEMPLATES_PATH="${SYNCTHING_ROOT_PATH}/Modelli Documenti"
SYNCTHING_PROTOTYPES_PATH=${SYNCTHING_ROOT_PATH}/Prototipi
EOF
```

### 3.4 Configura permessi
```bash
# Imposta proprietario
sudo chown -R www-data:www-data /var/www/supernova-management

# Imposta permessi corretti
sudo chmod -R 755 /var/www/supernova-management
sudo chmod -R 775 /var/www/supernova-management/storage
sudo chmod -R 775 /var/www/supernova-management/bootstrap/cache

# Crea directory Syncthing
sudo mkdir -p /opt/supernova-data/{Clienti,Magazzino,Prototipi}
sudo mkdir -p "/opt/supernova-data/Documenti SRL"
sudo mkdir -p "/opt/supernova-data/Modelli Documenti"
sudo chown -R www-data:www-data /opt/supernova-data
```

---

## Fase 4: Database e Assets

### 4.1 Esegui migrazioni
```bash
cd /var/www/supernova-management

# Esegui migrazioni
php artisan migrate --force

# Esegui seeder (opzionale)
php artisan db:seed --force

# Crea utente admin
php artisan make:filament-user
```

### 4.2 Compila assets (se necessario)
```bash
# Installa dipendenze NPM
npm install

# Compila assets per produzione
npm run build
```

### 4.3 Ottimizza Laravel
```bash
# Cache configurazioni
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components

# Ottimizza Composer
composer dump-autoload --optimize
```

---

## Fase 5: Configurazione Nginx

### 5.1 Crea virtual host
```bash
sudo tee /etc/nginx/sites-available/supernova << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;  # Cambia con il tuo dominio o IP
    root /var/www/supernova-management/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "upload_max_filesize=50M \n post_max_size=50M";
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Filament assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
EOF
```

### 5.2 Abilita il sito
```bash
# Abilita il sito
sudo ln -s /etc/nginx/sites-available/supernova /etc/nginx/sites-enabled/

# Rimuovi sito default
sudo rm -f /etc/nginx/sites-enabled/default

# Test configurazione
sudo nginx -t

# Riavvia Nginx
sudo systemctl restart nginx
```

---

## Fase 6: Configurazione PHP-FPM

### 6.1 Ottimizza PHP-FPM
```bash
sudo tee /etc/php/8.3/fpm/pool.d/supernova.conf << 'EOF'
[supernova]
user = www-data
group = www-data
listen = /var/run/php/php8.3-fpm-supernova.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 256M
EOF
```

### 6.2 Aggiorna Nginx per usare pool dedicato
```bash
sudo sed -i 's|unix:/var/run/php/php8.3-fpm.sock|unix:/var/run/php/php8.3-fpm-supernova.sock|' /etc/nginx/sites-available/supernova
```

### 6.3 Riavvia servizi
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

---

## Fase 7: Queue Worker (opzionale)

### 7.1 Crea servizio systemd per queue
```bash
sudo tee /etc/systemd/system/supernova-queue.service << 'EOF'
[Unit]
Description=Supernova Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5s
ExecStart=/usr/bin/php /var/www/supernova-management/artisan queue:work redis --sleep=3 --tries=3
WorkingDirectory=/var/www/supernova-management

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable supernova-queue
sudo systemctl start supernova-queue
```

---

## Fase 8: Cron Jobs

### 8.1 Configura scheduler Laravel
```bash
# Aggiungi al crontab di www-data
sudo crontab -u www-data -e

# Aggiungi questa riga:
* * * * * cd /var/www/supernova-management && php artisan schedule:run >> /dev/null 2>&1
```

---

## Fase 9: Firewall e Sicurezza

### 9.1 Configura UFW
```bash
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw --force enable
```

---

## Fase 10: Test e Verifica

### 10.1 Verifica servizi
```bash
# Controlla status servizi
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status postgresql
sudo systemctl status redis-server

# Test database
psql -h localhost -U supernova -d supernova -c "SELECT version();"

# Test Redis
redis-cli ping

# Test PHP
php -v
```

### 10.2 Accedi all'applicazione
```bash
# Trova il tuo IP
ip addr show | grep "inet " | grep -v 127.0.0.1

# Visita: http://YOUR-IP/admin
# Login con le credenziali create in fase 4.1
```

---

## Script di Avvio/Arresto

### start-supernova-native.sh
```bash
#!/bin/bash
echo "Avvio Supernova Management..."
sudo systemctl start nginx
sudo systemctl start php8.3-fpm
sudo systemctl start postgresql
sudo systemctl start redis-server
sudo systemctl start supernova-queue
echo "Supernova avviato! Visita http://$(hostname -I | awk '{print $1}')/admin"
```

### stop-supernova-native.sh
```bash
#!/bin/bash
echo "Arresto Supernova Management..."
sudo systemctl stop supernova-queue
sudo systemctl stop nginx
sudo systemctl stop php8.3-fpm
echo "Supernova arrestato."
```

---

## Troubleshooting

### Log utili
```bash
# Log Nginx
sudo tail -f /var/log/nginx/error.log

# Log PHP-FPM
sudo tail -f /var/log/php8.3-fpm.log

# Log Laravel
tail -f /var/www/supernova-management/storage/logs/laravel.log

# Log PostgreSQL
sudo tail -f /var/log/postgresql/postgresql-15-main.log
```

### Comandi di manutenzione
```bash
cd /var/www/supernova-management

# Pulisci cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Ricompila cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Aggiorna database
php artisan migrate

# Restart queue worker
sudo systemctl restart supernova-queue
```

---

## Performance Tips

1. **SSD**: Usa SSD per il database
2. **RAM**: 8GB+ per performance migliori
3. **OPcache**: Già configurato in PHP
4. **Redis**: Usato per cache e sessioni
5. **Nginx**: Configurato con cache headers

---

**Setup completato!** L'applicazione dovrebbe essere accessibile tramite browser.