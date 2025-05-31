#!/bin/sh
# (C) Campbell Software Solutions 2015
set -e

# Populate "/var/www/html/include/i18n" volume with language packs
if [ ! "$(ls -A /var/www/html/include/i18n)" ]; then
    cp -r /var/www/html/include/i18n.dist/* /var/www/html/include/i18n
    chown -R www-data:www-data /var/www/html/include/i18n
fi

# Automate installation
php /usr/local/bin/install.php
echo Applying configuration file security
chmod 644 /var/www/html/include/ost-config.php

# mkdir -p /run/nginx
# chown -R www-data:www-data /run/nginx
# chown -R www-data:www-data /var/lib/nginx
mkdir -p /var/log/php
chown -R www-data:www-data /var/log/php

# exec php-fpm -c $PHP_INI_DIR -y /usr/local/etc/php-fpm.conf
php-fpm 