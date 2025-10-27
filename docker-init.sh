#!/bin/bash

echo "🚀 Initializing Supernova Management Software..."

# Build and start Docker containers
echo "📦 Building Docker containers..."
docker compose build

echo "🔧 Starting Docker containers..."
docker compose up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 10

# Install Laravel via Composer inside the container
echo "🎼 Installing Laravel..."
docker compose exec app composer create-project laravel/laravel . --prefer-dist

# Set proper permissions
echo "🔐 Setting permissions..."
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache

# Generate application key
echo "🔑 Generating application key..."
docker compose exec app php artisan key:generate

# Install Filament
echo "📊 Installing Filament v3..."
docker compose exec app composer require filament/filament:"^3.0" -W
docker compose exec app php artisan filament:install --panels

# Install additional packages
echo "📚 Installing additional packages..."
docker compose exec app composer require laravel/scout
docker compose exec app composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle
docker compose exec app composer require predis/predis

# Run migrations
echo "🗄️ Running migrations..."
docker compose exec app php artisan migrate

echo "✅ Supernova Management Software initialized successfully!"
echo "🌐 Access the application at http://localhost"
echo "📧 Access Mailpit at http://localhost:8025"
echo "🔍 Access Meilisearch at http://localhost:7700"