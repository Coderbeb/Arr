#!/bin/bash

# Cache configurations for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
php artisan migrate --force

# Start Apache in the foreground
apache2-foreground
