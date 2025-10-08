#!/bin/bash

# Fix docker-compose.yml permissions for GUI access
# This allows the web GUI to update Traefik configuration

echo "Fixing docker-compose.yml permissions..."

if [ ! -f "/opt/webbadeploy/docker-compose.yml" ]; then
    echo "Error: docker-compose.yml not found"
    exit 1
fi

# Set ownership to www-data (GUI runs as www-data in Apache)
chown www-data:www-data /opt/webbadeploy/docker-compose.yml

# Set permissions to 664 (owner and group can write, others can read)
chmod 664 /opt/webbadeploy/docker-compose.yml

echo "✓ Permissions fixed:"
ls -la /opt/webbadeploy/docker-compose.yml

echo ""
echo "The GUI should now be able to update dashboard configuration."
