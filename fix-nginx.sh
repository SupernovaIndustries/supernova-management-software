#!/bin/bash

echo "🔧 Fixing Nginx - usando porta 8080..."

# Stop any conflicting services
sudo systemctl stop apache2 2>/dev/null || true

# Update Nginx config to use port 8080
sudo tee /etc/nginx/sites-available/supernova > /dev/null <<EOF
server {
    listen 8080;
    listen [::]:8080;
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

# Test and start Nginx
sudo nginx -t
if [ $? -eq 0 ]; then
    sudo systemctl start nginx
    echo "✅ Nginx avviato sulla porta 8080"
    echo "🌐 App disponibile su: http://localhost:8080"
else
    echo "❌ Errore configurazione Nginx"
fi