                       update-wharftales.sh                                                                   
#!/bin/bash

# WharfTales Update Script
# This script pulls latest changes from git and rebuilds containers

set -e  # Exit on any error

echo "🔄 Starting WharfTales update process..."

# Check if we're in the right directory
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ Error: docker-compose.yml not found. Are you in the right directory?"
    exit 1
fi

# Backup critical files before update
echo "💾 Backing up configurations..."
BACKUP_DIR="data/backups/update-$(date +%Y%m%d-%H%M%S)"
sudo mkdir -p "$BACKUP_DIR"

if [ -f "docker-compose.yml" ]; then
    sudo cp docker-compose.yml "$BACKUP_DIR/docker-compose.yml"
    echo "  ✓ Backed up docker-compose.yml"
fi

if [ -f "data/database.sqlite" ]; then
    sudo cp data/database.sqlite "$BACKUP_DIR/database.sqlite"
    echo "  ✓ Backed up database"
fi

if [ -f "ssl/acme.json" ]; then
    sudo cp ssl/acme.json "$BACKUP_DIR/acme.json"
    echo "  ✓ Backed up acme.json"
fi

# Pull latest changes from git
echo "📥 Pulling latest changes from git master..."
sudo git pull origin master

# Restore docker-compose.yml to preserve user settings
if [ -f "$BACKUP_DIR/docker-compose.yml" ]; then
    echo "♻️  Restoring docker-compose.yml to preserve your settings..."
    sudo cp "$BACKUP_DIR/docker-compose.yml" docker-compose.yml
fi

# Restore acme.json to preserve SSL certificates
if [ -f "$BACKUP_DIR/acme.json" ]; then
    echo "♻️  Restoring acme.json to preserve SSL certificates..."
    sudo cp "$BACKUP_DIR/acme.json" ssl/acme.json
    sudo chmod 600 ssl/acme.json
    sudo chown root:root ssl/acme.json
fi

# Stop running containers
echo "⏹️  Stopping containers..."
sudo docker-compose down

# Rebuild containers with no cache to ensure fresh build
echo "🔨 Rebuilding containers..."
sudo docker-compose build --no-cache

# Start containers
echo "🚀 Starting containers..."
sudo docker-compose up -d

# Wait for containers to be ready
sleep 3

# Ensure settings table exists
echo "🗄️ Initializing database settings..."
docker exec wharftales_gui sqlite3 /app/data/database.sqlite << 'EOSQL' 2>/dev/null || echo "Settings table already exists"
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
EOSQL

# Run migrations
docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php 2>/dev/null || echo "Compose migration already applied"

# Show status
echo "📊 Container status:"
sudo docker-compose ps

echo "✅ Update complete! WharfTales has been updated and restarted."
echo ""
echo "💡 Tip: You can run this script anytime with: ./update-wharftales.sh"

