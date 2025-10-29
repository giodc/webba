#!/bin/bash
# Reset Admin Password Script for WharfTales
# Usage: ./reset-admin-password.sh [username] [new_password]

USERNAME="${1:-admin}"
NEW_PASSWORD="${2}"

# Check if password is provided
if [ -z "$NEW_PASSWORD" ]; then
    echo "Usage: $0 [username] [new_password]"
    echo "Example: $0 admin MyNewPassword123"
    exit 1
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  WharfTales Password Reset"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if running in Docker
if [ -f /.dockerenv ]; then
    # Running inside container
    php /var/www/html/reset-password.php "$USERNAME" "$NEW_PASSWORD"
else
    # Running on host - execute in container
    CONTAINER_NAME=$(docker ps --format '{{.Names}}' | grep -E 'wharftales.*gui' | head -n 1)
    
    if [ -z "$CONTAINER_NAME" ]; then
        echo "❌ Error: Could not find WharfTales GUI container"
        echo ""
        echo "Available containers:"
        docker ps --format '{{.Names}}'
        exit 1
    fi
    
    echo "Using container: $CONTAINER_NAME"
    echo ""
    
    # Execute password reset in container
    docker exec "$CONTAINER_NAME" php /var/www/html/reset-password.php "$USERNAME" "$NEW_PASSWORD"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
