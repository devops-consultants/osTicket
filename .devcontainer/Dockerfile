FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and enable PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    wget \
    supervisor \
    default-mysql-client \
    sudo \
    nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        zip \
        intl \
        mbstring \
        xml \
        curl \
        opcache \
        sockets

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure PHP
COPY .devcontainer/php.ini /usr/local/etc/php/conf.d/php.ini

# Configure Nginx
COPY .devcontainer/nginx.conf /etc/nginx/sites-available/default

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Install xdebug for debugging
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Development tools
RUN apt-get install -y \
    vim \
    nano \
    iputils-ping \
    procps \
    less \
    htop \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Create developer user - simplified approach
RUN useradd -m -s /bin/bash developer && \
    echo "developer:developer" | chpasswd && \
    adduser developer sudo && \
    echo "developer ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers && \
    usermod -aG www-data developer && \
    chown -R developer:developer /var/www/html

# Expose port 80
EXPOSE 80

# Create start script
COPY .devcontainer/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Start Nginx and PHP-FPM
CMD ["/usr/local/bin/start.sh"]