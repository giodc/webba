#!/bin/bash

# Fix existing Laravel sites to use /public as DocumentRoot

echo "=========================================="
echo "Fixing Existing Laravel Sites"
echo "=========================================="
echo ""

# Find all Laravel containers
LARAVEL_CONTAINERS=$(docker ps --format "{{.Names}}" | grep "^laravel_")

if [ -z "$LARAVEL_CONTAINERS" ]; then
    echo "No Laravel containers found."
    exit 0
fi

echo "Found Laravel containers:"
echo "$LARAVEL_CONTAINERS"
echo ""

# Fix each container
for CONTAINER in $LARAVEL_CONTAINERS; do
    echo "=========================================="
    echo "Fixing: $CONTAINER"
    echo "=========================================="
    
    # 1. Set Apache DocumentRoot to /public
    echo "Setting DocumentRoot to /public..."
    docker exec "$CONTAINER" bash -c "cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF"
    
    # 2. Enable mod_rewrite
    echo "Enabling mod_rewrite..."
    docker exec "$CONTAINER" a2enmod rewrite 2>/dev/null || echo "  Already enabled"
    
    # 3. Fix storage permissions
    echo "Fixing storage permissions..."
    docker exec "$CONTAINER" bash -c "
        if [ -d /var/www/html/storage ]; then
            chmod -R 775 /var/www/html/storage
            chown -R www-data:www-data /var/www/html/storage
        fi
        if [ -d /var/www/html/bootstrap/cache ]; then
            chmod -R 775 /var/www/html/bootstrap/cache
            chown -R www-data:www-data /var/www/html/bootstrap/cache
        fi
    " 2>/dev/null || echo "  Storage directories not found (may not be Laravel yet)"
    
    # 4. Restart Apache
    echo "Restarting Apache..."
    docker exec "$CONTAINER" apache2ctl restart
    
    echo "✅ Fixed: $CONTAINER"
    echo ""
done

echo "=========================================="
echo "✅ All Laravel sites fixed!"
echo "=========================================="
echo ""
echo "Changes applied:"
echo "  • DocumentRoot set to /var/www/html/public"
echo "  • mod_rewrite enabled"
echo "  • Storage permissions fixed"
echo "  • Apache restarted"
echo ""
echo "Test your Laravel sites now!"
