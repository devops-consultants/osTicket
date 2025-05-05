#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
until mysql -h db -u osticket -posticket -e "SELECT 1" >/dev/null 2>&1; do
  echo "MySQL is unavailable - sleeping"
  sleep 2
done
echo "MySQL is up - continuing"

# Check if osTicket is already installed
DB_INITIALIZED=$(mysql -h db -u osticket -posticket -e "SHOW TABLES FROM osticket" 2>/dev/null | wc -l)
CONFIG_EXISTS=$([ -f "/var/www/html/include/ost-config.php" ] && echo "yes" || echo "no")

# Prepare setup directory if it doesn't exist
if [ ! -d "/var/www/html/setup_hidden" ] && [ -d "/var/www/html/setup" ]; then
    echo "Creating setup_hidden directory"
    cp -r /var/www/html/setup /var/www/html/setup_hidden
    chmod -R 755 /var/www/html/setup_hidden
fi

# Use install.php script to initialize osTicket if needed
if [ "$DB_INITIALIZED" -le "1" ] || [ "$CONFIG_EXISTS" = "no" ]; then
    echo "Initializing osTicket using install.php script..."
    
    # Create a modified version of the sample config file in a temporary location
    if [ ! -d "/var/www/html/include" ]; then
        mkdir -p /var/www/html/include
    fi

    # Make sure sample config exists
    if [ ! -f "/var/www/html/include/ost-sampleconfig.php" ]; then
        echo "Error: Sample config file not found!"
        exit 1
    fi
    
    # Copy sample config for the installer to use
    cp /var/www/html/include/ost-sampleconfig.php /tmp/ost-config-temp.php
    chmod 0666 /tmp/ost-config-temp.php

    # Set up environment variables for the installer
    export INSTALL_NAME="osTicket Support"
    export INSTALL_EMAIL="helpdesk@example.com"
    export INSTALL_URL="http://localhost:8080/"
    export ADMIN_FIRSTNAME="Admin"
    export ADMIN_LASTNAME="User"
    export ADMIN_EMAIL="admin@example.com"
    export ADMIN_USERNAME="ostadmin"
    export ADMIN_PASSWORD="Admin1"
    export MYSQL_PREFIX="ost_"
    # export MYSQL_HOST="db"
    # export MYSQL_PORT="3306"
    # export MYSQL_DATABASE="osticket"
    # export MYSQL_USER="osticket"
    # export MYSQL_PASSWORD="osticket"
    export INSTALL_CONFIG="/tmp/ost-config-temp.php"
    
    # Check if the install.php file exists in the correct location
    if [ -f "/var/www/html/docker/files/data/bin/install.php" ]; then
        echo "Found install.php at /var/www/html/docker/files/data/bin/install.php"
        php /var/www/html/docker/files/data/bin/install.php
    else
        echo "Warning: Could not find install.php in the expected location."
        echo "Falling back to setup methods..."
        
        # Use setup/install.php instead since we're building from source
        cd /var/www/html/setup
        php -f install.php
    fi
    
    # Set proper permissions for config file
    if [ -f "/var/www/html/include/ost-config.php" ]; then
        chmod 0644 /var/www/html/include/ost-config.php
        echo "Installation completed successfully!"
    else
        echo "Installation might have failed. Config file not found."
    fi
    
    # Clean up
    rm -f /tmp/ost-config-temp.php
else
    echo "osTicket is already installed."
fi

# Make sure setup is available for development
if [ -d "/var/www/html/setup_hidden" ] && [ ! -d "/var/www/html/setup" ]; then
    echo "Making setup directory available for development"
    cp -r /var/www/html/setup_hidden /var/www/html/setup
    chmod -R 755 /var/www/html/setup
fi

# Set proper permissions for development
chmod -R 775 /var/www/html/include

# Set proper permission for attachments directory
if [ ! -d "/var/www/html/uploads/tickets" ]; then
    mkdir -p /var/www/html/uploads/tickets
fi

chmod -R 775 /var/www/html/uploads

echo "osTicket development environment is ready!"
echo "Admin login: admin / admin"
echo "Access the helpdesk at: http://localhost:8080"
echo "Access the admin panel at: http://localhost:8080/scp"