#!/bin/bash

# WharfTales Security Fix Script
# This script fixes the critical permission issues

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}WharfTales Security Fix Script${NC}"
echo "================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (use sudo)${NC}"
    exit 1
fi

# Backup current permissions
echo -e "${YELLOW}Creating backup of current state...${NC}"
BACKUP_DIR="/opt/wharftales_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
getfacl -R /opt/wharftales > "$BACKUP_DIR/permissions_backup.txt" 2>/dev/null || true
echo -e "${GREEN}✓ Backup created at $BACKUP_DIR${NC}"

# Fix directory permissions (remove world-writable)
echo -e "\n${YELLOW}Fixing directory permissions...${NC}"
cd /opt/wharftales

# Main directory
chmod 755 /opt/wharftales

# Apps directory - 755 instead of 777
chmod 755 /opt/wharftales/apps
find /opt/wharftales/apps -type d -exec chmod 755 {} \;
find /opt/wharftales/apps -type f -exec chmod 644 {} \;

# Data directory - 755 instead of 777
chmod 755 /opt/wharftales/data
find /opt/wharftales/data -type d -exec chmod 755 {} \;
find /opt/wharftales/data -type f -exec chmod 644 {} \;

# Make sure www-data owns the files
chown -R www-data:www-data /opt/wharftales/apps
chown -R www-data:www-data /opt/wharftales/data

echo -e "${GREEN}✓ Directory permissions fixed${NC}"

# Fix Docker socket permissions
echo -e "\n${YELLOW}Fixing Docker socket permissions...${NC}"
if [ -S /var/run/docker.sock ]; then
    # Create docker group if it doesn't exist
    groupadd -f docker
    
    # Add www-data to docker group
    usermod -aG docker www-data
    
    # Set proper permissions (660 instead of 666)
    chmod 660 /var/run/docker.sock
    chown root:docker /var/run/docker.sock
    
    echo -e "${GREEN}✓ Docker socket secured (660 permissions, docker group)${NC}"
else
    echo -e "${YELLOW}Warning: Docker socket not found${NC}"
fi

# Fix docker-compose.yml permissions
if [ -f /opt/wharftales/docker-compose.yml ]; then
    chmod 640 /opt/wharftales/docker-compose.yml
    chown root:www-data /opt/wharftales/docker-compose.yml
    echo -e "${GREEN}✓ docker-compose.yml permissions fixed${NC}"
fi

# Fix SSL directory permissions
if [ -d /opt/wharftales/ssl ]; then
    chmod 750 /opt/wharftales/ssl
    chown -R root:www-data /opt/wharftales/ssl
    find /opt/wharftales/ssl -type f -exec chmod 640 {} \;
    echo -e "${GREEN}✓ SSL directory secured${NC}"
fi

# Restart GUI container to apply new permissions
echo -e "\n${YELLOW}Restarting GUI container...${NC}"
docker restart wharftales_gui
sleep 5
echo -e "${GREEN}✓ GUI container restarted${NC}"

# Summary
echo -e "\n${GREEN}================================${NC}"
echo -e "${GREEN}Security fixes applied!${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo "Changes made:"
echo "  • /opt/wharftales/apps: 777 → 755"
echo "  • /opt/wharftales/data: 777 → 755"
echo "  • /var/run/docker.sock: 666 → 660 (with docker group)"
echo "  • Ownership: www-data:www-data"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Review /opt/wharftales/SECURITY_FIXES.md for additional fixes"
echo "  2. Secure SFTP access with firewall rules"
echo "  3. Change database passwords"
echo "  4. Scan WordPress for malware"
echo ""
echo -e "${YELLOW}Backup location: $BACKUP_DIR${NC}"
