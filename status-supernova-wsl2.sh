#!/bin/bash

# Script per verificare status servizi Supernova su WSL2
echo "📊 Status Supernova Management su WSL2"
echo "======================================"

# Function to check service status with colored output
check_service() {
    local service=$1
    local name=$2
    local port=$3
    
    if sudo systemctl is-active --quiet $service; then
        echo "✅ $name: ATTIVO"
        if [ ! -z "$port" ]; then
            if netstat -tuln 2>/dev/null | grep -q ":$port "; then
                echo "   🔌 Porta $port: APERTA"
            else
                echo "   ❌ Porta $port: CHIUSA"
            fi
        fi
    else
        echo "❌ $name: INATTIVO"
    fi
}

echo ""
echo "🔧 Status Servizi:"
check_service "postgresql" "PostgreSQL" "5432"
check_service "redis-server" "Redis" "6379"
check_service "meilisearch" "MeiliSearch" "7700"
check_service "mailpit" "MailPit" "8025"
check_service "php8.3-fpm" "PHP-FPM" ""
check_service "nginx" "Nginx" "80"

echo ""
echo "🌐 URL di Accesso:"
if sudo systemctl is-active --quiet nginx; then
    echo "  ✅ App: http://localhost"
else
    echo "  ❌ App: Non disponibile (Nginx spento)"
fi

if sudo systemctl is-active --quiet mailpit; then
    echo "  ✅ MailPit: http://localhost:8025"
else
    echo "  ❌ MailPit: Non disponibile"
fi

if sudo systemctl is-active --quiet meilisearch; then
    echo "  ✅ MeiliSearch: http://localhost:7700"
else
    echo "  ❌ MeiliSearch: Non disponibile"
fi

echo ""
echo "💾 Database Info:"
if sudo systemctl is-active --quiet postgresql; then
    echo "  ✅ Database: supernova"
    echo "  👤 User: supernova"
    echo "  🔑 Password: password"
    echo "  📡 Host: localhost:5432"
else
    echo "  ❌ PostgreSQL non attivo"
fi

echo ""
echo "📈 Risorse Sistema:"
echo "  💾 RAM: $(free -h | awk 'NR==2{printf "%.1f/%.1f GB (%.0f%%)\n", $3/1024/1024, $2/1024/1024, $3*100/$2}')"
echo "  💽 Disk: $(df -h / | awk 'NR==2{printf "%s/%s (%s)\n", $3, $2, $5}')"
echo "  🔄 Load: $(uptime | awk -F'load average:' '{print $2}' | sed 's/^[ \t]*//')"

echo ""
echo "🔧 Comandi Utili:"
echo "  🚀 Start: ./start-supernova-wsl2.sh"
echo "  🛑 Stop: ./stop-supernova-wsl2.sh"
echo "  📋 Status: ./status-supernova-wsl2.sh"
echo "  📝 Logs: sudo journalctl -f -u [servizio]"

echo ""
echo "📁 Laravel Commands:"
if [ -d "~/supernova-management" ]; then
    echo "  📂 cd ~/supernova-management"
    echo "  ⚙️  php artisan migrate"
    echo "  🎨 npm run dev"
    echo "  🧪 php artisan test"
else
    echo "  ❌ Progetto non trovato in ~/supernova-management"
    echo "  📥 git clone /mnt/g/Supernova/supernova-management ~/supernova-management"
fi