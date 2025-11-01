#!/bin/bash
# Apply Security Updates - Docker Socket Proxy & Non-Root Users
# This script updates existing installations with security improvements

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WHARFTALES_DIR="$(dirname "$SCRIPT_DIR")"

echo "=================================================="
echo "WharfTales Security Update Script"
echo "=================================================="
echo ""

# Check if running from correct directory
if [ ! -f "$WHARFTALES_DIR/docker-compose.yml" ]; then
    echo "Error: docker-compose.yml not found in $WHARFTALES_DIR"
    exit 1
fi

# Backup existing docker-compose.yml
BACKUP_FILE="$WHARFTALES_DIR/data/backups/docker-compose-$(date +%Y%m%d-%H%M%S).yml"
sudo mkdir -p "$WHARFTALES_DIR/data/backups"
echo "📦 Backing up current docker-compose.yml to:"
echo "   $BACKUP_FILE"
sudo cp "$WHARFTALES_DIR/docker-compose.yml" "$BACKUP_FILE"

# Check if docker-proxy already exists
if grep -q "docker-proxy:" "$WHARFTALES_DIR/docker-compose.yml"; then
    echo "✅ Docker proxy already configured"
else
    echo "🔧 Adding docker-proxy service..."
    
    # Create updated docker-compose.yml from template
    if [ -f "$WHARFTALES_DIR/docker-compose.yml.template" ]; then
        # Extract user's custom values from current docker-compose.yml
        EMAIL=$(grep -oP 'letsencrypt\.acme\.email=\K[^"]+' "$WHARFTALES_DIR/docker-compose.yml" | head -1 || echo "CHANGE_ME@example.com")
        DOMAIN=$(grep -oP 'Host\(`\K[^`]+' "$WHARFTALES_DIR/docker-compose.yml" | head -1 || echo "CHANGE_ME.example.com")
        
        echo "   Using email: $EMAIL"
        echo "   Using domain: $DOMAIN"
        
        # Copy template and replace values
        sudo cp "$WHARFTALES_DIR/docker-compose.yml.template" "$WHARFTALES_DIR/docker-compose.yml"
        sudo sed -i "s/CHANGE_ME@example.com/$EMAIL/g" "$WHARFTALES_DIR/docker-compose.yml"
        sudo sed -i "s/CHANGE_ME.example.com/$DOMAIN/g" "$WHARFTALES_DIR/docker-compose.yml"
        
        echo "✅ Updated docker-compose.yml with security improvements"
    else
        echo "❌ Error: docker-compose.yml.template not found"
        exit 1
    fi
fi

echo ""
echo "🔨 Rebuilding GUI container with security updates..."
cd "$WHARFTALES_DIR"
docker-compose build web-gui

echo ""
echo "🔄 Restarting services..."
docker-compose down
docker-compose up -d

echo ""
echo "⏳ Waiting for services to start..."
sleep 5

echo ""
echo "🔍 Checking service status..."
docker-compose ps

echo ""
echo "=================================================="
echo "✅ Security Updates Applied Successfully!"
echo "=================================================="
echo ""
echo "Changes made:"
echo "  ✅ Docker socket proxy added"
echo "  ✅ GUI container now runs as www-data user"
echo "  ✅ Security hardening enabled (cap_drop, no-new-privileges)"
echo "  ✅ GUI uses non-privileged port 8080 internally"
echo ""
echo "Verify the setup:"
echo "  docker ps | grep docker-proxy"
echo "  docker exec wharftales_gui id"
echo "  docker logs wharftales_gui"
echo ""
echo "Backup saved to: $BACKUP_FILE"
echo ""
