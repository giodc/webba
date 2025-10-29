#!/bin/bash
# Fix Laravel index.php parse errors in existing containers

if [ -z "$1" ]; then
    echo "Usage: $0 <container_name>"
    echo ""
    echo "Example: $0 laravel_myapp_123456"
    echo ""
    echo "Available Laravel containers:"
    docker ps --format '{{.Names}}' | grep -i laravel
    exit 1
fi

CONTAINER_NAME="$1"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Fix Laravel index.php Parse Error"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Container: $CONTAINER_NAME"
echo ""

# Check if container exists
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "❌ Error: Container '$CONTAINER_NAME' not found"
    echo ""
    echo "Available containers:"
    docker ps --format '{{.Names}}'
    exit 1
fi

# Check if index.php exists
if ! docker exec "$CONTAINER_NAME" test -f /var/www/html/index.php 2>/dev/null; then
    echo "❌ Error: /var/www/html/index.php not found in container"
    exit 1
fi

echo "📝 Checking current file..."
if docker exec "$CONTAINER_NAME" php -l /var/www/html/index.php 2>&1 | grep -q "Parse error"; then
    echo "⚠️  Parse error detected - fixing..."
    
    # Fix the escaping issues
    docker exec "$CONTAINER_NAME" sh -c "sed -i \"s/\\\\\\\\\\\\\\\"/'/g\" /var/www/html/index.php"
    docker exec "$CONTAINER_NAME" sh -c "sed -i 's/\\\\\\\\\\\\$/\\\$/g' /var/www/html/index.php"
    
    echo ""
    echo "✅ File updated. Checking syntax..."
    
    if docker exec "$CONTAINER_NAME" php -l /var/www/html/index.php 2>&1 | grep -q "No syntax errors"; then
        echo "✅ Parse error fixed!"
    else
        echo "❌ Still has errors. You may need to delete and recreate the site."
        docker exec "$CONTAINER_NAME" php -l /var/www/html/index.php
    fi
else
    echo "✅ No parse errors found - file is OK!"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
