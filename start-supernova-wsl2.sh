#!/bin/bash

# Script per avviare tutti i servizi Supernova su WSL2
echo "🚀 Avvio Supernova Management su WSL2..."

# Check if services are installed
systemctl list-unit-files | grep -q postgresql || { echo "❌ PostgreSQL non installato. Esegui wsl2-setup.sh prima."; exit 1; }
command -v redis-server >/dev/null 2>&1 || { echo "❌ Redis non installato. Esegui wsl2-setup.sh prima."; exit 1; }
command -v meilisearch >/dev/null 2>&1 || { echo "❌ MeiliSearch non installato. Esegui wsl2-setup.sh prima."; exit 1; }
command -v mailpit >/dev/null 2>&1 || { echo "❌ MailPit non installato. Esegui wsl2-setup.sh prima."; exit 1; }

echo "📦 Avvio tutti i servizi..."

# Start PostgreSQL
echo "🐘 Avvio PostgreSQL..."
sudo systemctl start postgresql
if sudo systemctl is-active --quiet postgresql; then
    echo "✅ PostgreSQL avviato"
else
    echo "❌ Errore avvio PostgreSQL"
    exit 1
fi

# Start Redis
echo "🔴 Avvio Redis..."
sudo systemctl start redis-server
if sudo systemctl is-active --quiet redis-server; then
    echo "✅ Redis avviato"
else
    echo "❌ Errore avvio Redis"
    exit 1
fi

# Start MeiliSearch
echo "🔍 Avvio MeiliSearch..."
sudo systemctl start meilisearch
if sudo systemctl is-active --quiet meilisearch; then
    echo "✅ MeiliSearch avviato"
else
    echo "❌ Errore avvio MeiliSearch"
    exit 1
fi

# Start MailPit
echo "📧 Avvio MailPit..."
sudo systemctl start mailpit
if sudo systemctl is-active --quiet mailpit; then
    echo "✅ MailPit avviato"
else
    echo "❌ Errore avvio MailPit"
    exit 1
fi

# Start PHP-FPM
echo "🐘 Avvio PHP-FPM..."
sudo systemctl start php8.3-fpm
if sudo systemctl is-active --quiet php8.3-fpm; then
    echo "✅ PHP-FPM avviato"
else
    echo "❌ Errore avvio PHP-FPM"
    exit 1
fi

# Start Nginx
echo "🌐 Avvio Nginx..."
sudo systemctl start nginx
if sudo systemctl is-active --quiet nginx; then
    echo "✅ Nginx avviato"
else
    echo "❌ Errore avvio Nginx"
    exit 1
fi

echo ""
echo "🎉 Tutti i servizi sono avviati!"
echo ""
echo "📋 Status servizi:"
echo "🐘 PostgreSQL: $(sudo systemctl is-active postgresql)"
echo "🔴 Redis: $(sudo systemctl is-active redis-server)"
echo "🔍 MeiliSearch: $(sudo systemctl is-active meilisearch)"
echo "📧 MailPit: $(sudo systemctl is-active mailpit)"
echo "🐘 PHP-FPM: $(sudo systemctl is-active php8.3-fpm)"
echo "🌐 Nginx: $(sudo systemctl is-active nginx)"
echo ""
echo "🌐 URL di accesso:"
echo "  - App: http://localhost"
echo "  - MailPit UI: http://localhost:8025"
echo "  - MeiliSearch: http://localhost:7700"
echo ""
echo "🔧 Comandi utili:"
echo "  - Stop servizi: ./stop-supernova-wsl2.sh"
echo "  - Status: ./status-supernova-wsl2.sh"
echo "  - Logs: sudo journalctl -f -u [servizio]"
echo ""
echo "📁 Progetto Laravel:"
echo "  cd ~/supernova-management"
echo "  cp .env.wsl2 .env"
echo "  composer install"
echo "  php artisan migrate"
echo "  npm run dev"