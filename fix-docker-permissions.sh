#!/bin/bash

# Fix Docker Socket Permissions for Webbadeploy
# Run this script if you get "permission denied" errors when creating sites

set -e

echo "🔧 Fixing Docker socket permissions..."

# Get the docker group ID
DOCKER_GID=$(getent group docker | cut -d: -f3)

if [ -z "$DOCKER_GID" ]; then
    echo "❌ Error: Docker group not found"
    exit 1
fi

echo "📋 Docker group ID: $DOCKER_GID"

# Set socket permissions
echo "🔐 Setting socket permissions..."
sudo chmod 666 /var/run/docker.sock

# Rebuild web-gui container with correct docker group
echo "🔨 Rebuilding web-gui container..."
sudo docker-compose build --build-arg DOCKER_GID=$DOCKER_GID web-gui

# Restart the container
echo "🔄 Restarting web-gui..."
sudo docker-compose up -d web-gui

# Verify permissions
echo "✅ Verifying Docker access..."
sudo docker exec webbadeploy_gui docker ps > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "✅ Success! Docker socket is now accessible from web-gui container"
    echo ""
    echo "You can now create sites without permission errors."
else
    echo "⚠️  Warning: Docker access verification failed"
    echo "You may need to restart Docker or the host system"
fi
