                       update-webbadeploy.sh                                                                   
#!/bin/bash

# WebbaDeploy Update Script
# This script pulls latest changes from git and rebuilds containers

set -e  # Exit on any error

echo "🔄 Starting WebbaDeploy update process..."

# Check if we're in the right directory
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ Error: docker-compose.yml not found. Are you in the right directory?"
    exit 1
fi

# Pull latest changes from git
echo "📥 Pulling latest changes from git master..."
sudo git pull origin master

# Stop running containers
echo "⏹️  Stopping containers..."
sudo docker-compose down

# Rebuild containers with no cache to ensure fresh build
echo "🔨 Rebuilding containers..."
sudo docker-compose build --no-cache

# Start containers
echo "🚀 Starting containers..."
sudo docker-compose up -d

# Show status
echo "📊 Container status:"
sudo docker-compose ps

echo "✅ Update complete! WebbaDeploy has been updated and restarted."
echo ""
echo "💡 Tip: You can run this script anytime with: ./update-webbadeploy.sh"

