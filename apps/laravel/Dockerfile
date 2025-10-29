FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    supervisor \
    nginx \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd opcache bcmath

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory permissions
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy existing application directory contents
COPY --chown=www:www . /var/www/html

# Copy Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# Copy PHP-FPM config
COPY php.ini /usr/local/etc/php/conf.d/laravel.ini

# Copy supervisor config
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Don't switch to www user - supervisor will handle user switching
# This allows root commands like composer to work when exec'ing into container

# Expose port 9000 and start php-fpm server
EXPOSE 9000 80
CMD ["/usr/bin/supervisord"]