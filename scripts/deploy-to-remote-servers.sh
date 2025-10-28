#!/bin/bash

# Deploy WebbaDeploy Updates to Multiple Remote Servers
# This script updates all your remote WebbaDeploy installations

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "=========================================="
echo "WebbaDeploy Remote Deployment Script"
echo "=========================================="
echo ""

# Configuration file for servers
CONFIG_FILE="${1:-servers.txt}"

if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${YELLOW}Creating example servers.txt file...${NC}"
    cat > servers.txt << 'EOF'
# WebbaDeploy Remote Servers
# Format: user@hostname or just hostname (if using SSH config)
# One server per line, lines starting with # are ignored

# Example:
# root@server1.example.com
# deploy@server2.example.com
# server3.example.com
EOF
    echo -e "${GREEN}✓ Created servers.txt${NC}"
    echo ""
    echo "Please edit servers.txt and add your server addresses, then run this script again."
    exit 0
fi

# Read servers from file (skip comments and empty lines)
SERVERS=()
while IFS= read -r line; do
    # Skip comments and empty lines
    [[ "$line" =~ ^#.*$ ]] && continue
    [[ -z "$line" ]] && continue
    SERVERS+=("$line")
done < "$CONFIG_FILE"

if [ ${#SERVERS[@]} -eq 0 ]; then
    echo -e "${RED}No servers found in $CONFIG_FILE${NC}"
    echo "Please add server addresses to the file (one per line)"
    exit 1
fi

echo "Found ${#SERVERS[@]} server(s) to update:"
for server in "${SERVERS[@]}"; do
    echo "  - $server"
done
echo ""

# Confirm before proceeding
read -p "Do you want to update all these servers? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Update cancelled."
    exit 0
fi

echo ""
echo "Starting deployment..."
echo ""

# Track results
SUCCESS_COUNT=0
FAILED_COUNT=0
FAILED_SERVERS=()

# Update each server
for server in "${SERVERS[@]}"; do
    echo "=========================================="
    echo -e "${BLUE}Updating: $server${NC}"
    echo "=========================================="
    
    # Test SSH connection first
    if ! ssh -o ConnectTimeout=5 -o BatchMode=yes "$server" "echo 'SSH connection successful'" 2>/dev/null; then
        echo -e "${RED}✗ Cannot connect to $server (SSH failed)${NC}"
        FAILED_COUNT=$((FAILED_COUNT + 1))
        FAILED_SERVERS+=("$server (SSH failed)")
        echo ""
        continue
    fi
    
    # Run update on remote server
    if ssh "$server" << 'ENDSSH'
        set -e
        
        # Check if WebbaDeploy is installed
        if [ ! -d "/opt/webbadeploy" ]; then
            echo "ERROR: WebbaDeploy not found at /opt/webbadeploy"
            exit 1
        fi
        
        cd /opt/webbadeploy
        
        # Check if safe-update.sh exists
        if [ ! -f "safe-update.sh" ]; then
            echo "WARNING: safe-update.sh not found, using install.sh instead"
            sudo ./install.sh
        else
            sudo ./safe-update.sh
        fi
ENDSSH
    then
        echo -e "${GREEN}✓ $server updated successfully${NC}"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    else
        echo -e "${RED}✗ $server update failed${NC}"
        FAILED_COUNT=$((FAILED_COUNT + 1))
        FAILED_SERVERS+=("$server (update failed)")
    fi
    
    echo ""
done

# Summary
echo "=========================================="
echo "DEPLOYMENT SUMMARY"
echo "=========================================="
echo -e "Total servers: ${#SERVERS[@]}"
echo -e "${GREEN}Successful: $SUCCESS_COUNT${NC}"
echo -e "${RED}Failed: $FAILED_COUNT${NC}"

if [ $FAILED_COUNT -gt 0 ]; then
    echo ""
    echo "Failed servers:"
    for failed in "${FAILED_SERVERS[@]}"; do
        echo -e "  ${RED}✗ $failed${NC}"
    done
fi

echo ""

if [ $FAILED_COUNT -eq 0 ]; then
    echo -e "${GREEN}All servers updated successfully! 🎉${NC}"
    exit 0
else
    echo -e "${YELLOW}Some servers failed to update. Please check the errors above.${NC}"
    exit 1
fi
