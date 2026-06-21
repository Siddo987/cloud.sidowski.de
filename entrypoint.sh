#!/bin/bash
set -e

# The database is already ready because of 'depends_on: condition: service_healthy' in docker-compose.yml.

# Run migrations
echo "Applying database migrations..."
php scripts/apply_migrations.php

# Start cron in background
echo "Starting cron daemon..."
cron

# Start Apache in foreground
echo "Starting Apache..."
exec apache2-foreground
