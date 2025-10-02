#!/bin/bash

# WebbaDeploy Quick Rebuild Script
# This script only rebuilds and restarts containers without pulling from git
# Useful for local changes or when you know containers just need a restart

set -e

echo "🔄 Quick rebuilding WebbaDeploy containers..."

# Stop running containers
echo "⏹️  Stopping containers..."
sudo docker-compose down

# Rebuild containers
echo "🔨 Rebuilding containers..."
sudo docker-compose build --no-cache

# Start containers
echo "🚀 Starting containers..."
sudo docker-compose up -d

# Show status
echo "📊 Container status:"
sudo docker-compose ps

echo "✅ Quick rebuild complete!"




