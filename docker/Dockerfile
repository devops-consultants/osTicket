# Create a PHP-FPM base container with pre-requisites for osTicket
FROM php:8.4-fpm-alpine AS base

RUN set -x && \
    # Install runtime dependencies
    apk add --no-cache \
        wget \
        msmtp \
        ca-certificates \
        libpng \
        c-client \
        libintl \
        libxml2 \
        icu \
        openssl && \
    # Install build dependencies
    apk add --no-cache --virtual .build-deps \
        linux-headers \
        imap-dev \
        libpng-dev \
        oniguruma-dev \
        curl-dev \
        openldap-dev \
        gettext-dev \
        libxml2-dev \
        icu-dev \
        autoconf \
        g++ \
        make \
        pcre-dev && \
    # Install and configure PHP extensions
    docker-php-ext-configure gd && \
    docker-php-ext-install -j$(nproc) \
        gd \
        curl \
        mysqli \
        sockets \
        gettext \
        mbstring \
        xml \
        intl \
        opcache && \
    # Cleanup
    apk del .build-deps && \
    rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# Stage 1: Deploy osTicket files
FROM php:8.4-cli AS deployer
WORKDIR /data

RUN apt update && apt install -y git

# Copy local osTicket files to the deployer container
COPY . /tmp/osticket/

RUN set -x \
    # Deploy osTicket from local files
    && cd /tmp/osticket \
    && php manage.php deploy -sv /var/www/html \
    # www-data is uid:gid 82:82 in php-fpm-alpine
    && chown -R 82:82 /var/www/html \
    # Hide setup
    && mv /var/www/html/setup /var/www/html/setup_hidden \
    && chown -R root:root /var/www/html/setup_hidden \
    && chmod -R go= /var/www/html/setup_hidden \
    # Clean up
    && rm -rf /tmp/osticket

# Stage 2: Create production PHP-FPM container (without Nginx)
FROM quay.io/devops_consultants/php-fpm:latest AS production
LABEL maintainer="Rob Coward <rob.coward@devops-consultants.co.uk>"

RUN apk add --no-cache sed grep fcgi && \
    rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# environment for osticket
ENV PHP_MAX_EXECUTION_TIME=30 \
    PHP_MEMORY_LIMIT=128M \
    PHP_UPLOAD_MAX_FILESIZE=32M \
    PHP_POST_MAX_SIZE=32M

# setup workdir
WORKDIR /var/www/html

# Copy osTicket files from deployer
COPY --from=deployer /var/www/html /var/www/html

# Download language packs and plugins
RUN wget -nv -O /var/www/html/include/i18n/en_GB.phar https://s3.amazonaws.com/downloads.osticket.com/lang/1.18.x/en_GB.phar && \
    wget -nv -O /var/www/html/include/plugins/auth-oauth2.phar https://s3.amazonaws.com/downloads.osticket.com/plugin/auth-oauth2.phar 

RUN wget -O /usr/local/bin/php-fpm-healthcheck \
    https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck && \
    echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/zz-docker.conf 

# Configure PHP
# COPY docker/files/usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf
# COPY docker/files/usr/local/etc/php/conf.d/php-osticket.ini /usr/local/etc/php/conf.d/php-osticket.ini
COPY docker/files/data/bin /usr/local/bin

# # Process environment variables in the PHP config file
# RUN sed -i "s|\${PHP_MEMORY_LIMIT}|${PHP_MEMORY_LIMIT}|g" /usr/local/etc/php/conf.d/php-osticket.ini && \
#     sed -i "s|\${PHP_UPLOAD_MAX_FILESIZE}|${PHP_UPLOAD_MAX_FILESIZE}|g" /usr/local/etc/php/conf.d/php-osticket.ini && \
#     sed -i "s|\${PHP_POST_MAX_SIZE}|${PHP_POST_MAX_SIZE}|g" /usr/local/etc/php/conf.d/php-osticket.ini && \
#     sed -i "s|\${PHP_MAX_EXECUTION_TIME}|${PHP_MAX_EXECUTION_TIME}|g" /usr/local/etc/php/conf.d/php-osticket.ini
    # Optimize for production
RUN echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache-recommended.ini && \
    echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache-recommended.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache-recommended.ini && \
    echo "opcache.revalidate_freq=60" >> /usr/local/etc/php/conf.d/opcache-recommended.ini && \
    echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache-recommended.ini && \
    echo "opcache.enable_cli=0" >> /usr/local/etc/php/conf.d/opcache-recommended.ini && \
    # Set proper permissions
    chown -R www-data:www-data /var/www/html

# Run as non-root user
# USER www-data

# Add PHP-FPM healthcheck
HEALTHCHECK --interval=5s --timeout=1s \
    CMD php-fpm-healthcheck || exit 1

VOLUME ["/var/www/html/include/plugins","/var/www/html/include/i18n"]
EXPOSE 9000

CMD ["/usr/local/bin/start.sh"]

# Stage 3: Create Nginx container (for local development)
FROM nginx:alpine AS nginx
LABEL maintainer="Rob Coward <rob.coward@devops-consultants.co.uk>"

# Copy nginx config
# COPY docker/files/etc/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/files/etc/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Create volume for logs
VOLUME ["/var/log/nginx"]

# Run as non-root
RUN touch /var/run/nginx.pid && \
    chown -R nginx:nginx /var/cache/nginx /var/run/nginx.pid /var/log/nginx

USER nginx

# Add nginx healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -q --no-cache --spider http://localhost/api/http.php/status || exit 1

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
