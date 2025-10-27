#!/bin/bash

# SCRIPT WSL2 NATIVO - NO DOCKER!
echo "🚀 SUPERNOVA WSL2 NATIVE (NO DOCKER)"
echo "🏠 Directory: $(pwd)"

cd /home/alessandro/supernova-management

# Setup Laravel se necessario
if [ ! -f ".env" ]; then
    echo "⚙️  Setup iniziale Laravel..."
    cp .env.wsl2 .env 2>/dev/null || echo "❌ .env.wsl2 non trovato"
    composer install --no-interaction 2>/dev/null || true
    npm install 2>/dev/null || true
fi

echo "🔧 Avvio servizi WSL2 nativi..."

# PostgreSQL
echo "🐘 PostgreSQL..."
sudo systemctl start postgresql
echo "✅ PostgreSQL: $(sudo systemctl is-active postgresql)"

# Redis  
echo "🔴 Redis..."
sudo systemctl start redis-server
echo "✅ Redis: $(sudo systemctl is-active redis-server)"

# MeiliSearch
echo "🔍 MeiliSearch..."
sudo systemctl start meilisearch
echo "✅ MeiliSearch: $(sudo systemctl is-active meilisearch)"

# MailPit
echo "📧 MailPit..."
sudo systemctl start mailpit
echo "✅ MailPit: $(sudo systemctl is-active mailpit)"

# PHP-FPM
echo "🐘 PHP-FPM..."
sudo systemctl start php8.3-fpm
echo "✅ PHP-FPM: $(sudo systemctl is-active php8.3-fpm)"

echo ""
echo "🗄️  Database setup..."
php artisan migrate --force 2>/dev/null || echo "⚠️  Migrate error (normal se prima volta)"

echo ""
echo "🎉 SERVIZI WSL2 ATTIVI!"
echo "🌐 URLs:"
echo "  💻 App: http://localhost:8000"
echo "  📧 MailPit: http://localhost:8025"
echo "  🔍 MeiliSearch: http://localhost:7700"
echo ""
echo "🚀 Avvio server Laravel in background..."

# Avvia Laravel server in background
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &

echo "✅ Server Laravel avviato in background!"
echo "📋 PID: $!"
echo "📝 Logs: tail -f storage/logs/serve.log"
echo ""
echo "🛑 Per fermare: ./stop-supernova.sh"