#!/bin/bash

# Fix Docker permissions for local development
# This script fixes common Docker permission issues on local machines

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║  WharfTales - Fix Local Docker Permissions                   ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Please run as root (use sudo)"
    exit 1
fi

cd /opt/wharftales

# Get the actual Docker group ID
DOCKER_GID=$(getent group docker | cut -d: -f3)
echo "📋 Detected Docker GID: $DOCKER_GID"

# Update docker-compose.yml with correct DOCKER_GID
echo "🔧 Updating docker-compose.yml with correct Docker GID..."
sed -i "s/DOCKER_GID: [0-9]*/DOCKER_GID: $DOCKER_GID/" docker-compose.yml

# Fix Docker socket permissions
echo "🔐 Fixing Docker socket permissions..."
groupadd -f docker
usermod -aG docker $USER
chmod 666 /var/run/docker.sock
chown root:docker /var/run/docker.sock

# Fix host directory permissions
echo "📁 Fixing host directory permissions..."
mkdir -p /opt/wharftales/data
mkdir -p /opt/wharftales/apps
mkdir -p /opt/wharftales/ssl
mkdir -p /opt/wharftales/logs

# Set ownership to current user (for local development)
chown -R $SUDO_USER:$SUDO_USER /opt/wharftales/data
chown -R $SUDO_USER:$SUDO_USER /opt/wharftales/apps
chown -R $SUDO_USER:$SUDO_USER /opt/wharftales/ssl
chown -R $SUDO_USER:$SUDO_USER /opt/wharftales/logs

# Set permissions
chmod -R 777 /opt/wharftales/data
chmod -R 777 /opt/wharftales/apps
chmod 755 /opt/wharftales/ssl
chmod 755 /opt/wharftales/logs

# Fix docker-compose.yml permissions
chown $SUDO_USER:$SUDO_USER /opt/wharftales/docker-compose.yml
chmod 664 /opt/wharftales/docker-compose.yml

echo "🔨 Rebuilding containers with correct permissions..."
docker-compose down
docker-compose build --no-cache web-gui
docker-compose up -d

echo "⏳ Waiting for containers to start..."
sleep 5

# Fix permissions inside containers
echo "🔧 Fixing permissions inside containers..."
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data
docker exec -u root wharftales_gui chmod -R 777 /app/data

docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
docker exec -u root wharftales_gui chmod -R 777 /app/apps

# Fix database permissions
echo "💾 Fixing database permissions..."
docker exec -u root wharftales_gui bash -c "if [ -f /app/data/database.sqlite ]; then chown www-data:www-data /app/data/database.sqlite && chmod 666 /app/data/database.sqlite; fi"

# Run migrations
echo "🔄 Running database migrations..."
docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || true
docker exec wharftales_gui php /var/www/html/migrate-php-version.php 2>/dev/null || true
docker exec wharftales_gui php /var/www/html/migrations/add_github_fields.php 2>/dev/null || true
docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php 2>/dev/null || true

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║  ✅ Local Docker permissions fixed!                            ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "📝 Summary:"
echo "  • Docker GID: $DOCKER_GID"
echo "  • Docker socket: 666 permissions (local dev)"
echo "  • Data directory: 777 permissions (local dev)"
echo "  • Apps directory: 777 permissions (local dev)"
echo "  • Containers rebuilt with correct GID"
echo ""
echo "🌐 Access dashboard at: http://localhost:9000"
echo ""
echo "⚠️  Note: These are permissive settings for LOCAL DEVELOPMENT only!"
echo "   For production, use install-production.sh instead."
echo ""
