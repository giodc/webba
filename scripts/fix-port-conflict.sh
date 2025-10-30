#!/bin/bash
# WharfTales Port Conflict Resolution Script
# Use this if you encounter "address already in use" errors

echo "=========================================="
echo "WharfTales Port Conflict Resolver"
echo "=========================================="
echo ""

# Check what's using port 80
echo "Checking what's using port 80..."
PORT_80_USERS=$(ss -tlnp | grep ':80 ')

if [ -z "$PORT_80_USERS" ]; then
    echo "✓ Port 80 is free"
else
    echo "Port 80 is in use:"
    echo "$PORT_80_USERS"
    echo ""
fi

# Check what's using port 443
echo "Checking what's using port 443..."
PORT_443_USERS=$(ss -tlnp | grep ':443 ')

if [ -z "$PORT_443_USERS" ]; then
    echo "✓ Port 443 is free"
else
    echo "Port 443 is in use:"
    echo "$PORT_443_USERS"
    echo ""
fi

# Check Docker containers
echo ""
echo "Checking Docker containers..."
docker ps --format "table {{.Names}}\t{{.Ports}}" | grep -E "(80|443)"

echo ""
echo "=========================================="
echo "Resolution Options:"
echo "=========================================="
echo ""
echo "1. Stop WharfTales containers and restart them:"
echo "   cd /opt/wharftales && docker-compose down && docker-compose up -d"
echo ""
echo "2. If nginx is running on the host:"
echo "   sudo systemctl stop nginx"
echo "   sudo systemctl disable nginx"
echo ""
echo "3. If another service is using the ports, stop it:"
echo "   sudo systemctl stop <service-name>"
echo ""
echo "4. Force remove all containers and restart:"
echo "   cd /opt/wharftales && docker-compose down -v && docker-compose up -d"
echo ""

# Offer to fix automatically
read -p "Would you like to automatically restart WharfTales containers? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Stopping containers..."
    cd /opt/wharftales
    docker-compose down
    
    echo "Waiting for ports to be released..."
    sleep 5
    
    echo "Starting containers..."
    docker-compose up -d
    
    echo ""
    echo "✓ Containers restarted!"
    echo "Check status with: docker-compose ps"
fi
