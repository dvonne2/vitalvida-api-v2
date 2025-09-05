#!/usr/bin/env bash
set -o errexit

echo "🔄 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

echo "🔧 Configuring Laravel..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "🗄️ Running database migrations..."
php artisan migrate --force

echo "✅ Build completed!"
