#!/bin/bash
## WharfTales Upgrade Script
## Do not modify this file. You will lose the ability to autoupdate!

set -e

DATE=$(date +%Y-%m-%d-%H-%M-%S)
LOGFILE="/opt/wharftales/logs/upgrade-${DATE}.log"
BACKUP_DIR="/opt/wharftales/backups"
WHARFTALES_DIR="/opt/wharftales"
VERSION_URL="${1:-https://raw.githubusercontent.com/giodc/wharftales/main/VERSION}"
SKIP_BACKUP="${2:-false}"

# Create necessary directories
mkdir -p /opt/wharftales/logs
mkdir -p "$BACKUP_DIR"

echo "========================================" | tee -a "$LOGFILE"
echo "WharfTales Upgrade Started: $(date)" | tee -a "$LOGFILE"
echo "========================================" | tee -a "$LOGFILE"

# Get current version
CURRENT_VERSION=$(cat /opt/wharftales/VERSION 2>/dev/null || echo "unknown")
echo "Current version: $CURRENT_VERSION" | tee -a "$LOGFILE"

# Backup existing installation
if [ "$SKIP_BACKUP" != "true" ]; then
    echo "Creating backup..." | tee -a "$LOGFILE"
    BACKUP_FILE="$BACKUP_DIR/wharftales-backup-$DATE.tar.gz"
    
    tar -czf "$BACKUP_FILE" \
        -C /opt/wharftales \
        --exclude='backups' \
        --exclude='logs' \
        --exclude='data/database.sqlite' \
        --exclude='.git' \
        gui data ssl apps docker-compose.yml VERSION 2>&1 | tee -a "$LOGFILE"
    
    if [ $? -eq 0 ]; then
        echo "✓ Backup created: $BACKUP_FILE" | tee -a "$LOGFILE"
    else
        echo "✗ Backup failed, aborting upgrade" | tee -a "$LOGFILE"
        exit 1
    fi
else
    echo "Skipping backup as requested" | tee -a "$LOGFILE"
fi

# Store current directory
ORIGINAL_DIR=$(pwd)

# Navigate to WharfTales directory
cd "$WHARFTALES_DIR" || exit 1

# Check if git repository exists
if [ -d ".git" ]; then
    echo "Pulling latest changes from git..." | tee -a "$LOGFILE"
    
    # Stash any local changes
    git stash save "Auto-stash before upgrade $DATE" 2>&1 | tee -a "$LOGFILE"
    
    # Fetch latest changes
    git fetch origin 2>&1 | tee -a "$LOGFILE"
    
    # Checkout main branch
    git checkout main 2>&1 | tee -a "$LOGFILE"
    
    # Pull latest changes
    git pull origin main 2>&1 | tee -a "$LOGFILE"
    
    if [ $? -eq 0 ]; then
        echo "✓ Git pull successful" | tee -a "$LOGFILE"
    else
        echo "✗ Git pull failed" | tee -a "$LOGFILE"
        exit 1
    fi
else
    echo "Not a git repository, skipping git pull" | tee -a "$LOGFILE"
fi

# Get new version
NEW_VERSION=$(cat /opt/wharftales/VERSION 2>/dev/null || echo "unknown")
echo "New version: $NEW_VERSION" | tee -a "$LOGFILE"

# Update docker containers
echo "Updating Docker containers..." | tee -a "$LOGFILE"

# Stop containers gracefully to release ports
echo "Stopping containers to release ports..." | tee -a "$LOGFILE"
docker-compose down 2>&1 | tee -a "$LOGFILE"

# Wait for ports to be released
echo "Waiting for ports to be released..." | tee -a "$LOGFILE"
sleep 3

# Verify critical ports are free
echo "Checking if ports 80 and 443 are available..." | tee -a "$LOGFILE"
for i in {1..10}; do
    if ! ss -tlnp | grep -q ':80 '; then
        echo "✓ Port 80 is available" | tee -a "$LOGFILE"
        break
    fi
    if [ $i -eq 10 ]; then
        echo "✗ Port 80 is still in use after 10 seconds" | tee -a "$LOGFILE"
        echo "Checking what's using port 80..." | tee -a "$LOGFILE"
        ss -tlnp | grep ':80 ' | tee -a "$LOGFILE"
        exit 1
    fi
    echo "Port 80 still in use, waiting... ($i/10)" | tee -a "$LOGFILE"
    sleep 1
done

# Pull latest images
echo "Pulling latest images..." | tee -a "$LOGFILE"
docker-compose pull 2>&1 | tee -a "$LOGFILE"

# Start containers with new images
echo "Starting containers..." | tee -a "$LOGFILE"
docker-compose up -d --remove-orphans 2>&1 | tee -a "$LOGFILE"

if [ $? -eq 0 ]; then
    echo "✓ Docker containers updated successfully" | tee -a "$LOGFILE"
else
    echo "✗ Docker container update failed" | tee -a "$LOGFILE"
    echo "Attempting to restore from backup..." | tee -a "$LOGFILE"
    
    if [ "$SKIP_BACKUP" != "true" ] && [ -f "$BACKUP_FILE" ]; then
        tar -xzf "$BACKUP_FILE" -C /opt/wharftales 2>&1 | tee -a "$LOGFILE"
        docker-compose up -d 2>&1 | tee -a "$LOGFILE"
        echo "Restored from backup" | tee -a "$LOGFILE"
    fi
    
    exit 1
fi

# Wait for containers to be healthy
echo "Waiting for containers to be healthy..." | tee -a "$LOGFILE"
sleep 5

# Check if web-gui is running
if docker ps | grep -q wharftales_gui; then
    echo "✓ WharfTales GUI is running" | tee -a "$LOGFILE"
else
    echo "✗ WharfTales GUI is not running" | tee -a "$LOGFILE"
fi

# Clean up old backups (keep last 5)
echo "Cleaning up old backups..." | tee -a "$LOGFILE"
cd "$BACKUP_DIR"
ls -t wharftales-backup-*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
echo "✓ Old backups cleaned" | tee -a "$LOGFILE"

# Return to original directory
cd "$ORIGINAL_DIR"

echo "========================================" | tee -a "$LOGFILE"
echo "Upgrade completed successfully!" | tee -a "$LOGFILE"
echo "Version: $CURRENT_VERSION → $NEW_VERSION" | tee -a "$LOGFILE"
echo "Time: $(date)" | tee -a "$LOGFILE"
echo "========================================" | tee -a "$LOGFILE"

exit 0
