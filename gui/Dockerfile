FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite ssl

# Install required packages
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create necessary directories for the application
RUN mkdir -p /var/www/html/data /var/www/html/nginx/sites /var/www/html/ssl \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/nginx/sites /var/www/html/ssl

# Create app directories
RUN mkdir -p /app/apps/php/sites /app/apps/wordpress/sites /app/apps/laravel/sites /app/nginx/sites \
    && chown -R www-data:www-data /app

# Add www-data to docker group (use host's docker GID)
# The docker.sock will be mounted with proper permissions via docker-compose
ARG DOCKER_GID=988
RUN groupadd -g ${DOCKER_GID} docker || groupmod -g ${DOCKER_GID} docker || true \
    && usermod -aG docker www-data \
    && usermod -aG docker root

# Expose port
EXPOSE 80

CMD ["apache2-foreground"]
