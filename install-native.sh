#!/bin/bash

# Script automatico di installazione Supernova Management (nativo)
# Per Ubuntu/Debian/Raspberry Pi OS

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzioni di output
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verifica se è root
if [[ $EUID -eq 0 ]]; then
   log_error "Non eseguire questo script come root!"
   exit 1
fi

# Banner
echo -e "${BLUE}"
cat << 'EOF'
╔═══════════════════════════════════════╗
║     SUPERNOVA MANAGEMENT INSTALLER    ║
║           Setup Nativo Linux          ║
╚═══════════════════════════════════════╝
EOF
echo -e "${NC}"

# Richiedi conferma
read -p "Vuoi procedere con l'installazione nativa? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_info "Installazione annullata."
    exit 0
fi

# Variabili
PROJECT_DIR="/var/www/supernova-management"
DOMAIN="localhost"

log_info "Iniziando installazione Supernova Management..."

# FASE 1: Aggiornamento sistema
log_info "Fase 1/10: Aggiornamento sistema..."
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip software-properties-common apt-transport-https ca-certificates

# FASE 2: Installazione PHP 8.3
log_info "Fase 2/10: Installazione PHP 8.3..."
if ! php --version | grep -q "PHP 8.3"; then
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt update
    sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common \
        php8.3-mysql php8.3-pgsql php8.3-xml php8.3-xmlrpc \
        php8.3-curl php8.3-gd php8.3-imagick php8.3-dev \
        php8.3-imap php8.3-mbstring php8.3-opcache \
        php8.3-soap php8.3-zip php8.3-intl php8.3-bcmath \
        php8.3-redis
fi

# FASE 3: Installazione Composer
log_info "Fase 3/10: Installazione Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
fi

# FASE 4: Installazione Node.js
log_info "Fase 4/10: Installazione Node.js..."
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# FASE 5: Installazione PostgreSQL
log_info "Fase 5/10: Installazione PostgreSQL..."
if ! command -v psql &> /dev/null; then
    sudo apt install -y postgresql postgresql-contrib
    sudo systemctl start postgresql
    sudo systemctl enable postgresql
    
    # Configura database
    sudo -u postgres psql << 'EOF'
CREATE USER supernova WITH PASSWORD 'password';
CREATE DATABASE supernova OWNER supernova;
GRANT ALL PRIVILEGES ON DATABASE supernova TO supernova;
\q
EOF
fi

# FASE 6: Installazione Redis
log_info "Fase 6/10: Installazione Redis..."
if ! command -v redis-cli &> /dev/null; then
    sudo apt install -y redis-server
    sudo systemctl start redis-server
    sudo systemctl enable redis-server
fi

# FASE 7: Installazione Nginx
log_info "Fase 7/10: Installazione Nginx..."
if ! command -v nginx &> /dev/null; then
    sudo apt install -y nginx
    sudo systemctl start nginx
    sudo systemctl enable nginx
fi

# FASE 8: Setup progetto
log_info "Fase 8/10: Configurazione progetto..."
if [ ! -d "$PROJECT_DIR" ]; then
    sudo mkdir -p "$PROJECT_DIR"
    sudo chown -R $USER:$USER "$PROJECT_DIR"
    
    # Se eseguito dalla directory del progetto, copia i file
    if [ -f "composer.json" ]; then
        log_info "Copiando file del progetto..."
        cp -r . "$PROJECT_DIR/"
    else
        log_error "File del progetto non trovati!"
        log_info "Copia manualmente i file in $PROJECT_DIR"
        exit 1
    fi
fi

cd "$PROJECT_DIR"

# Installa dipendenze
log_info "Installando dipendenze Composer..."
composer install --optimize-autoloader --no-dev

# Configura .env
if [ ! -f ".env" ]; then
    log_info "Configurando file .env..."
    cp .env.example .env
    
    # Genera chiave
    php artisan key:generate --force
    
    # Aggiorna configurazione database
    sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=pgsql/' .env
    sed -i 's/DB_HOST=.*/DB_HOST=127.0.0.1/' .env
    sed -i 's/DB_DATABASE=.*/DB_DATABASE=supernova/' .env
    sed -i 's/DB_USERNAME=.*/DB_USERNAME=supernova/' .env
    sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=password/' .env
    
    # Aggiorna configurazione Redis
    sed -i 's/CACHE_DRIVER=.*/CACHE_DRIVER=redis/' .env
    sed -i 's/SESSION_DRIVER=.*/SESSION_DRIVER=redis/' .env
    sed -i 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env
fi

# Configura permessi
sudo chown -R www-data:www-data "$PROJECT_DIR"
sudo chmod -R 755 "$PROJECT_DIR"
sudo chmod -R 775 "$PROJECT_DIR/storage"
sudo chmod -R 775 "$PROJECT_DIR/bootstrap/cache"

# Crea directory Syncthing
sudo mkdir -p /opt/supernova-data/{Clienti,Magazzino,Prototipi}
sudo mkdir -p "/opt/supernova-data/Documenti SRL"
sudo mkdir -p "/opt/supernova-data/Modelli Documenti"
sudo chown -R www-data:www-data /opt/supernova-data

# FASE 9: Database
log_info "Fase 9/10: Configurazione database..."
php artisan migrate --force

# Crea utente admin se non esiste
if ! php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null | grep -q "1"; then
    log_info "Creando utente admin..."
    php artisan make:filament-user
fi

# Ottimizza Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components

# FASE 10: Configurazione Nginx
log_info "Fase 10/10: Configurazione Nginx..."

# Crea virtual host
sudo tee /etc/nginx/sites-available/supernova > /dev/null << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $PROJECT_DIR/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "upload_max_filesize=50M \\n post_max_size=50M";
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }
}
EOF

# Abilita il sito
sudo ln -sf /etc/nginx/sites-available/supernova /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test e riavvio Nginx
sudo nginx -t && sudo systemctl restart nginx

# Riavvia PHP-FPM
sudo systemctl restart php8.3-fpm

# Crea script di gestione
cat > start-supernova-native.sh << 'EOF'
#!/bin/bash
echo "Avvio Supernova Management..."
sudo systemctl start nginx
sudo systemctl start php8.3-fpm
sudo systemctl start postgresql
sudo systemctl start redis-server
echo "Supernova avviato!"
IP=$(hostname -I | awk '{print $1}')
echo "Visita: http://$IP/admin"
EOF

cat > stop-supernova-native.sh << 'EOF'
#!/bin/bash
echo "Arresto Supernova Management..."
sudo systemctl stop nginx
sudo systemctl stop php8.3-fpm
echo "Supernova arrestato."
EOF

chmod +x start-supernova-native.sh stop-supernova-native.sh

# Configurazione firewall
if command -v ufw &> /dev/null; then
    log_info "Configurando firewall..."
    sudo ufw allow 22/tcp
    sudo ufw allow 80/tcp
    sudo ufw allow 443/tcp
    sudo ufw --force enable
fi

# Test finale
log_info "Verifica installazione..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost | grep -q "200\|302"; then
    log_success "✅ Installazione completata con successo!"
    echo
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║           INSTALLAZIONE COMPLETA       ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
    echo
    echo -e "${BLUE}🌐 URL:${NC} http://$(hostname -I | awk '{print $1}')/admin"
    echo -e "${BLUE}📁 Directory:${NC} $PROJECT_DIR"
    echo -e "${BLUE}🚀 Avvio:${NC} ./start-supernova-native.sh"
    echo -e "${BLUE}🛑 Stop:${NC} ./stop-supernova-native.sh"
    echo
    echo -e "${YELLOW}📋 Credenziali admin create durante l'installazione${NC}"
    echo
else
    log_error "❌ Installazione completata ma l'applicazione non risponde"
    echo "Controlla i log:"
    echo "- sudo tail -f /var/log/nginx/error.log"
    echo "- tail -f $PROJECT_DIR/storage/logs/laravel.log"
fi