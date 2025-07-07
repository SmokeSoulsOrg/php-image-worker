#!/bin/bash

set -e
cd /var/www/html

echo "🔧 Ensuring SQLite database file exists..."
mkdir -p database
touch database/database.sqlite
chown www-data:www-data database/database.sqlite

echo "🔧 Changing .env ownership to www-data"
chown www-data:www-data /var/www/html/.env

echo "🔧 Ensuring .env is writable..."
chmod +w /var/www/html/.env || echo "⚠️  .env not writable and chmod failed"

echo "🔧 Ensuring storage/logs is writable..."
mkdir -p storage/logs
chown -R www-data:www-data storage
chmod -R 775 storage

echo "🔑 Generating app key..."
php artisan key:generate

echo "📦 Caching config..."
php artisan config:cache

echo "🛠 Running migrations on primary..."
php artisan migrate:fresh --force --database=sqlite

echo "🔗 Creating storage symlink..."
php artisan storage:link || echo "⚠️  storage:link failed (probably already linked)"

echo "🚀 Starting image consumer in background..."
php artisan consume:image-download > storage/logs/image-consumer.log 2>&1 &

echo "🚀 Starting Laravel queue worker for image-events..."
php artisan queue:work rabbitmq > storage/logs/image-worker.log 2>&1 &

echo "✅ Done. Tailing application logs using Pail..."
php artisan pail
