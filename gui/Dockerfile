FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite ssl

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

# Expose port
EXPOSE 80

CMD ["apache2-foreground"]
