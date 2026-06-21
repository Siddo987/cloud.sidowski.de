#!/bin/bash
# cloud.sidowski.de - Installer Script
# Usage: curl -sL https://raw.githubusercontent.com/Siddo987/cloud.sidowski.de/main/install.sh | bash

set -e

echo "================================================="
echo "   cloud.sidowski.de - Automated Installer       "
echo "================================================="
echo ""

# Check dependencies
for cmd in git php curl; do
    if ! command -v $cmd &> /dev/null; then
        echo "Error: $cmd is required but not installed."
        exit 1
    fi
done

# Check if php extensions are installed (basic check)
for ext in mysqli mbstring json openssl gd; do
    if ! php -m | grep -qi "$ext"; then
        echo "Warning: PHP extension '$ext' is missing. You might need to install it (e.g. php-$ext)."
    fi
done

# Define installation directory
INSTALL_DIR="cloud.sidowski.de"
if [ -f "composer.json" ] && grep -qi "cloud.sidowski" "composer.json"; then
    echo "Existing installation found in current directory. Updating..."
    INSTALL_DIR="."
    git pull origin main || true
else
    echo "Cloning repository..."
    if [ ! -d "$INSTALL_DIR" ]; then
        git clone https://github.com/Siddo987/cloud.sidowski.de.git "$INSTALL_DIR"
    else
        echo "Directory $INSTALL_DIR already exists. We will try to install inside it."
    fi
fi

cd "$INSTALL_DIR" || exit 1

# Setup permissions for specific dirs if they exist
mkdir -p user_uploads
chmod 775 user_uploads
mkdir -p temp
chmod 775 temp

# Install dependencies using local composer
echo ""
echo "Installing PHP dependencies..."
if [ -f "composer.phar" ]; then
    php composer.phar install --no-dev --optimize-autoloader
else
    echo "composer.phar not found! Trying global composer..."
    composer install --no-dev --optimize-autoloader
fi

# Environment Configuration
echo ""
echo "Setting up configuration..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo ".env file created from example."
    
    echo "Do you want to configure the database now? [Y/n]"
    read -r setup_db
    if [[ ! "$setup_db" =~ ^[Nn] ]]; then
        read -p "Database Host [localhost]: " db_host
        db_host=${db_host:-localhost}
        read -p "Database Name: " db_name
        read -p "Database User: " db_user
        read -p "Database Password: " db_pass
        read -p "Base URL (e.g., https://cloud.example.com): " base_url

        # Replace in .env
        sed -i "s/^DB_SERVER=.*/DB_SERVER=$db_host/" .env
        sed -i "s/^DB_NAME=.*/DB_NAME=$db_name/" .env
        sed -i "s/^DB_USERNAME=.*/DB_USERNAME=$db_user/" .env
        sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$db_pass/" .env
        sed -i "s|^BASE_URL=.*|BASE_URL=$base_url|" .env

        echo ""
        echo "Applying database migrations..."
        php scripts/apply_migrations.php
    else
        echo "Please configure the .env file manually and run 'php scripts/apply_migrations.php' later."
    fi
else
    echo ".env file already exists. Skipping configuration."
fi

echo ""
echo "================================================="
echo " Installation Complete! "
echo "================================================="
echo "Next steps:"
echo " 1. Configure your Webserver (Apache/Nginx) to point to $PWD"
echo " 2. Ensure the web server has read/write access to user_uploads/ and temp/"
echo " 3. Verify your SMTP settings in the .env file for email functionality."
echo ""
