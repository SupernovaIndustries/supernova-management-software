#!/bin/bash

# Script per avviare tutti i servizi necessari per Supernova Management

echo "🚀 Avvio servizi Supernova Management..."

# Crea le directory temporanee per nginx
echo "📁 Creazione directory temporanee nginx..."
mkdir -p /tmp/nginx/client_body_temp /tmp/nginx/fastcgi_temp /tmp/nginx/proxy_temp /tmp/nginx/scgi_temp /tmp/nginx/uwsgi_temp

# Avvia PostgreSQL
echo "🐘 Avvio PostgreSQL..."
brew services start postgresql@15

# Avvia Redis
echo "🔴 Avvio Redis..."
brew services start redis

# Avvia PHP-FPM
echo "🐘 Avvio PHP-FPM..."
brew services start php@8.3

# Avvia nginx
echo "🌐 Avvio nginx..."
brew services start nginx

# Attendi un momento per permettere ai servizi di avviarsi
sleep 3

# Verifica lo stato dei servizi
echo ""
echo "📊 Stato servizi:"
brew services list | grep -E '(nginx|redis|postgresql|php@8.3)'

# Test connessione
echo ""
echo "🧪 Test connessione HTTP..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8090)
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Applicazione disponibile su http://localhost:8090"
else
    echo "⚠️  Applicazione non risponde (HTTP $HTTP_CODE)"
fi

echo ""
echo "✨ Completato!"
