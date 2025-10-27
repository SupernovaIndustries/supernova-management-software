#!/bin/bash

# Supernova Management Startup Script
# This script ensures all required services are running

echo "🚀 Starting Supernova Management services..."

# Create nginx temp directories if they don't exist
if [ ! -d "/tmp/nginx" ]; then
    echo "Creating nginx temp directories..."
    mkdir -p /tmp/nginx/client_body_temp
    mkdir -p /tmp/nginx/proxy_temp
    mkdir -p /tmp/nginx/fastcgi_temp
    mkdir -p /tmp/nginx/uwsgi_temp
    mkdir -p /tmp/nginx/scgi_temp
fi

# Start/restart services
echo "Starting nginx..."
brew services restart nginx

echo "Ensuring PHP-FPM is running..."
brew services restart php@8.3

echo "Ensuring PostgreSQL is running..."
brew services restart postgresql@15

echo "Ensuring Redis is running..."
brew services restart redis

echo "Ensuring Meilisearch is running..."
brew services restart meilisearch

# Wait a moment for services to start
sleep 2

# Check if nginx is responding
if curl -s http://localhost > /dev/null 2>&1; then
    echo "✅ Services started successfully!"
else
    echo "⚠️  Services started but nginx may have issues. Checking logs..."
    tail -20 /opt/homebrew/var/log/nginx/error.log
fi

echo ""
echo "Service status:"
brew services list | grep -E "(nginx|php|postgresql|redis|meilisearch)"
