#!/bin/bash

# Cache configurations for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
php artisan migrate --force

# Fix permissions for logs that might have been created as root during migrations
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Start Apache in the foreground
apache2-foreground
