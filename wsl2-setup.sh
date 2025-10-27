#!/bin/bash

# WSL2 Setup Script per Supernova Management
# Setup completo PostgreSQL + Redis + MeiliSearch + MailPit + Nginx

echo "🚀 Setup Supernova Management su WSL2..."

# Update system
sudo apt update && sudo apt upgrade -y

# Install essential tools
sudo apt install -y curl wget git unzip software-properties-common apt-transport-https ca-certificates

echo "📦 Installazione PHP 8.3..."
# Add PHP 8.3 repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 and extensions
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-common php8.3-curl php8.3-zip \
    php8.3-gd php8.3-mysql php8.3-xml php8.3-mbstring php8.3-bcmath php8.3-intl \
    php8.3-readline php8.3-pgsql php8.3-redis php8.3-dom php8.3-soap

# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

echo "🐘 Installazione PostgreSQL 15..."
# Install PostgreSQL
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
echo "deb http://apt.postgresql.org/pub/repos/apt/ $(lsb_release -cs)-pgdg main" | sudo tee /etc/apt/sources.list.d/pgdg.list
sudo apt update
sudo apt install -y postgresql-15 postgresql-client-15 postgresql-contrib-15

# Configure PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Create database and user
sudo -u postgres psql -c "CREATE DATABASE supernova;"
sudo -u postgres psql -c "CREATE USER supernova WITH ENCRYPTED PASSWORD 'password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE supernova TO supernova;"
sudo -u postgres psql -c "ALTER USER supernova CREATEDB;"

echo "🔴 Installazione Redis..."
# Install Redis
sudo apt install -y redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Configure Redis
sudo sed -i 's/bind 127.0.0.1 ::1/bind 0.0.0.0/' /etc/redis/redis.conf
sudo systemctl restart redis-server

echo "🔍 Installazione MeiliSearch..."
# Install MeiliSearch
curl -L https://install.meilisearch.com | sh
sudo mv ./meilisearch /usr/local/bin/

# Create MeiliSearch service
sudo tee /etc/systemd/system/meilisearch.service > /dev/null <<EOF
[Unit]
Description=MeiliSearch
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/usr/local/bin/meilisearch --http-addr 0.0.0.0:7700 --master-key=masterKey
Restart=on-failure
RestartSec=1

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl start meilisearch
sudo systemctl enable meilisearch

echo "📧 Installazione MailPit..."
# Install MailPit
MAILPIT_VERSION=$(curl -s https://api.github.com/repos/axllent/mailpit/releases/latest | grep -Po '"tag_name": "v\K[^"]*')
wget "https://github.com/axllent/mailpit/releases/latest/download/mailpit-linux-amd64.tar.gz"
sudo tar -xzf mailpit-linux-amd64.tar.gz -C /usr/local/bin/
sudo chmod +x /usr/local/bin/mailpit
rm mailpit-linux-amd64.tar.gz

# Create MailPit service
sudo tee /etc/systemd/system/mailpit.service > /dev/null <<EOF
[Unit]
Description=MailPit
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/usr/local/bin/mailpit --listen 0.0.0.0:8025 --smtp 0.0.0.0:1025
Restart=on-failure
RestartSec=1

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl start mailpit
sudo systemctl enable mailpit

echo "🌐 Installazione Nginx..."
# Install Nginx
sudo apt install -y nginx

# Create Nginx config for Supernova
sudo tee /etc/nginx/sites-available/supernova > /dev/null <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name supernova-management.test localhost;
    root /home/$(whoami)/supernova-management/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Enable site
sudo ln -sf /etc/nginx/sites-available/supernova /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test and start Nginx
sudo nginx -t
sudo systemctl start nginx
sudo systemctl enable nginx

echo "📋 Installazione Node.js e npm..."
# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

echo "✅ Setup completato!"
echo ""
echo "🔧 Servizi configurati:"
echo "  - PostgreSQL: localhost:5432"
echo "  - Redis: localhost:6379"  
echo "  - MeiliSearch: localhost:7700"
echo "  - MailPit: localhost:8025"
echo "  - Nginx: localhost:80"
echo ""
echo "🚀 Per avviare tutti i servizi:"
echo "  sudo systemctl start postgresql redis-server meilisearch mailpit nginx php8.3-fpm"
echo ""
echo "📁 Clona il progetto in:"
echo "  cd /home/$(whoami)"
echo "  git clone /mnt/g/Supernova/supernova-management"
echo ""
echo "🔑 Database configurato:"
echo "  Database: supernova"
echo "  User: supernova"
echo "  Password: password"