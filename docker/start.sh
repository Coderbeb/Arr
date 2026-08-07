#!/bin/bash

# Cache configurations for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
php artisan migrate --force

# Create storage symlink (critical for proof file serving)
php artisan storage:link 2>/dev/null || true

# Fix permissions for logs that might have been created as root during migrations
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Start queue worker in background (for queued jobs like notifications)
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &

# Start Apache in the foreground
apache2-foreground
