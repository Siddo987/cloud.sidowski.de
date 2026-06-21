FROM php:8.5-apache

# Enable Apache mod_rewrite for nice URLs if used (.htaccess)
RUN a2enmod rewrite

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libzip-dev \
    perl \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Add cron job for cleanup script
RUN echo "0 3 * * * root php /var/www/html/scripts/cleanup_orphans.php >> /var/log/cron.log 2>&1" > /etc/cron.d/cleanup_cron && \
    chmod 0644 /etc/cron.d/cleanup_cron && \
    crontab /etc/cron.d/cleanup_cron

# Install PHP extensions
RUN docker-php-ext-install mysqli gd zip

# Get Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Install composer dependencies
# The --no-dev and --optimize-autoloader flags are good for production
RUN composer install --no-dev --optimize-autoloader

# Ensure required directories exist and are writable by www-data
RUN mkdir -p /var/www/html/user_uploads /var/www/html/temp \
    && chown -R www-data:www-data /var/www/html/user_uploads /var/www/html/temp \
    && chmod -R 755 /var/www/html/user_uploads /var/www/html/temp

# Make entrypoint executable
RUN chmod +x /var/www/html/entrypoint.sh

# Use custom entrypoint
ENTRYPOINT ["/var/www/html/entrypoint.sh"]
