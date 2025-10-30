#!/bin/bash
# WharfTales Installation Script

set -e

echo "=========================================="
echo "WharfTales Installation"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root or with sudo"
    exit 1
fi

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "Docker is not installed. Please install Docker first."
    echo "Visit: https://docs.docker.com/engine/install/"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "Docker Compose is not installed. Please install Docker Compose first."
    echo "Visit: https://docs.docker.com/compose/install/"
    exit 1
fi

# Clone or update repository
if [ -d "/opt/wharftales/.git" ]; then
    echo "WharfTales already installed. Updating..."
    cd /opt/wharftales
    
    # Stop containers
    echo "Stopping containers..."
    docker-compose down
    
    # Pull latest changes
    git stash
    git pull origin main
    
    # Apply port fix if needed
    if [ -f "scripts/fix-upgrade-port-issue.sh" ]; then
        bash scripts/fix-upgrade-port-issue.sh
    fi
else
    echo "Installing WharfTales..."
    cd /opt
    git clone https://github.com/giodc/wharftales.git
    cd wharftales
fi

# Create necessary directories
mkdir -p /opt/wharftales/logs
mkdir -p /opt/wharftales/backups
mkdir -p /opt/wharftales/data
mkdir -p /opt/wharftales/ssl
mkdir -p /opt/wharftales/apps

# Set permissions
chown -R www-data:www-data /opt/wharftales/data
chown -R www-data:www-data /opt/wharftales/ssl
chown -R www-data:www-data /opt/wharftales/apps
chmod -R 755 /opt/wharftales/scripts

# Start containers
echo "Starting containers..."
docker-compose up -d

echo ""
echo "=========================================="
echo "Installation Complete!"
echo "=========================================="
echo ""
echo "WharfTales is now running at:"
echo "  http://your-server-ip:9000"
echo ""
echo "Next steps:"
echo "1. Access the web interface"
echo "2. Complete the setup wizard"
echo "3. Configure your first site"
echo ""
echo "For updates, run:"
echo "  /opt/wharftales/scripts/upgrade.sh"
echo ""
