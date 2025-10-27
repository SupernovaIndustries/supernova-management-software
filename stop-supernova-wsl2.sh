#!/bin/bash

# Script per fermare tutti i servizi Supernova su WSL2
echo "🛑 Stop Supernova Management su WSL2..."

echo "📦 Fermando tutti i servizi..."

# Stop Nginx
echo "🌐 Fermando Nginx..."
sudo systemctl stop nginx
echo "✅ Nginx fermato"

# Stop PHP-FPM
echo "🐘 Fermando PHP-FPM..."
sudo systemctl stop php8.3-fpm
echo "✅ PHP-FPM fermato"

# Stop MailPit
echo "📧 Fermando MailPit..."
sudo systemctl stop mailpit
echo "✅ MailPit fermato"

# Stop MeiliSearch
echo "🔍 Fermando MeiliSearch..."
sudo systemctl stop meilisearch
echo "✅ MeiliSearch fermato"

# Stop Redis
echo "🔴 Fermando Redis..."
sudo systemctl stop redis-server
echo "✅ Redis fermato"

# Stop PostgreSQL
echo "🐘 Fermando PostgreSQL..."
sudo systemctl stop postgresql
echo "✅ PostgreSQL fermato"

echo ""
echo "🎉 Tutti i servizi sono stati fermati!"
echo ""
echo "📋 Status finale:"
echo "🐘 PostgreSQL: $(sudo systemctl is-active postgresql)"
echo "🔴 Redis: $(sudo systemctl is-active redis-server)"
echo "🔍 MeiliSearch: $(sudo systemctl is-active meilisearch)"
echo "📧 MailPit: $(sudo systemctl is-active mailpit)"
echo "🐘 PHP-FPM: $(sudo systemctl is-active php8.3-fpm)"
echo "🌐 Nginx: $(sudo systemctl is-active nginx)"
echo ""
echo "🚀 Per riavviare: ./start-supernova-wsl2.sh"