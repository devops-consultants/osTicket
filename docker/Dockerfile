# Deployment doesn't work on Alpine
FROM php:8.3-cli AS deployer
# No need for OSTICKET_VERSION since we're using local files

# Create directory for osTicket files
WORKDIR /data

# Copy local osTicket files to the deployer container
COPY . /tmp/osticket/

RUN set -x \
    # Deploy osTicket from local files
    && cd /tmp/osticket \
    && php manage.php deploy -sv /var/www/html \
    # www-data is uid:gid 82:82 in php:7.0-fpm-alpine
    && chown -R 82:82 /var/www/html \
    # Hide setup
    && mv /var/www/html/setup /var/www/html/setup_hidden \
    && chown -R root:root /var/www/html/setup_hidden \
    && chmod -R go= /var/www/html/setup_hidden \
    # Clean up
    && rm -rf /tmp/osticket

FROM php:8.3-fpm-alpine AS production
LABEL maintainer="Rob Coward <rob@uptimelabs.io>"
# environment for osticket
ENV HOME=/data \
    PHP_MAX_EXECUTION_TIME=30 \
    PHP_MEMORY_LIMIT=128M \
    PHP_UPLOAD_MAX_FILESIZE=32M \
    PHP_POST_MAX_SIZE=32M

# setup workdir
WORKDIR /data
COPY --from=deployer /var/www/html /var/www/html

# Install production dependencies and PHP extensions
RUN set -x && \
    # Install runtime dependencies
    apk add --no-cache \
        wget \
        msmtp \
        ca-certificates \
        supervisor \
        nginx \
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

# Download language packs and plugins
RUN wget -nv -O /var/www/html/include/i18n/en_GB.phar https://s3.amazonaws.com/downloads.osticket.com/lang/1.18.x/en_GB.phar && \
    wget -nv -O /var/www/html/include/plugins/auth-oauth2.phar https://s3.amazonaws.com/downloads.osticket.com/plugin/auth-oauth2.phar && \
    # Configure logs and permissions
    touch /var/log/msmtp.log && \
    chown www-data:www-data /var/log/msmtp.log && \
    # File upload permissions
    mkdir -p /var/tmp/nginx && \
    chown nginx:www-data /var/tmp/nginx && chmod g+rx /var/tmp/nginx && \
    # Redirect Nginx logs to stdout and stderr
    ln -sf /dev/stdout /var/log/nginx/access.log && \
    ln -sf /dev/stderr /var/log/nginx/error.log

# Configure PHP
COPY docker/files/usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/files/usr/local/etc/php/conf.d/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Process environment variables in the PHP config file
RUN sed -i "s|\${PHP_MEMORY_LIMIT}|${PHP_MEMORY_LIMIT}|g" /usr/local/etc/php/conf.d/custom.ini && \
    sed -i "s|\${PHP_UPLOAD_MAX_FILESIZE}|${PHP_UPLOAD_MAX_FILESIZE}|g" /usr/local/etc/php/conf.d/custom.ini && \
    sed -i "s|\${PHP_POST_MAX_SIZE}|${PHP_POST_MAX_SIZE}|g" /usr/local/etc/php/conf.d/custom.ini && \
    sed -i "s|\${PHP_MAX_EXECUTION_TIME}|${PHP_MAX_EXECUTION_TIME}|g" /usr/local/etc/php/conf.d/custom.ini

# Create non-root user for running the application
RUN adduser -u 1000 -D -H -s /sbin/nologin osticket && \
    chown -R osticket:www-data /var/www/html && \
    chmod -R 775 /var/www/html/include

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD wget -q --no-cache --spider http://localhost/api/http.php/status || exit 1

# Copy application files
COPY docker/files/ /
VOLUME ["/var/www/html/include/plugins","/var/www/html/include/i18n","/var/log/nginx"]
EXPOSE 80
CMD ["/data/bin/start.sh"]
