FROM wordpress:6.4-php8.2-fpm

# Install optimization tools and extensions
RUN apt-get update && apt-get install -y \
    nginx \
    redis-tools \
    libmemcached-dev \
    libz-dev \
    libpq-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libssl-dev \
    libmcrypt-dev \
    libzip-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions for performance
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        zip \
        opcache \
        bcmath \
        exif

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Memcached extension
RUN pecl install memcached && docker-php-ext-enable memcached

# Copy optimized PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/wordpress.ini

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/sites-available/default

# Copy WordPress optimization plugins
COPY wp-config-optimized.php /tmp/wp-config-optimized.php

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80 9000