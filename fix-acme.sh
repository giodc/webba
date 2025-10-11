#!/bin/bash

# Fix ACME file for Traefik SSL certificate management
# Run: sudo bash fix-acme.sh

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

WEBBADEPLOY_DIR="/opt/webbadeploy"
ACME_FILE="$WEBBADEPLOY_DIR/ssl/acme.json"

echo -e "${BLUE}Fixing ACME file for Traefik SSL...${NC}\n"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (use sudo)${NC}"
    exit 1
fi

# Create ssl directory if it doesn't exist
if [ ! -d "$WEBBADEPLOY_DIR/ssl" ]; then
    echo -e "${YELLOW}Creating ssl directory...${NC}"
    mkdir -p "$WEBBADEPLOY_DIR/ssl"
fi

# Check if acme.json exists
if [ -f "$ACME_FILE" ]; then
    FILE_SIZE=$(stat -c%s "$ACME_FILE")
    if [ "$FILE_SIZE" -eq 0 ]; then
        echo -e "${YELLOW}ACME file exists but is empty (0 bytes)${NC}"
        echo -e "${YELLOW}Removing empty file...${NC}"
        rm "$ACME_FILE"
    else
        echo -e "${GREEN}ACME file exists and has content ($FILE_SIZE bytes)${NC}"
        echo -e "${YELLOW}Backing up existing acme.json...${NC}"
        cp "$ACME_FILE" "$ACME_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    fi
fi

# Create new acme.json with proper structure
echo -e "${YELLOW}Creating new acme.json...${NC}"
cat > "$ACME_FILE" << 'EOF'
{
  "letsencrypt": {
    "Account": {
      "Email": "",
      "Registration": null,
      "PrivateKey": null,
      "KeyType": ""
    },
    "Certificates": null
  }
}
EOF

# Set correct permissions (600 = rw-------)
echo -e "${YELLOW}Setting permissions to 600...${NC}"
chmod 600 "$ACME_FILE"

# Set correct ownership
echo -e "${YELLOW}Setting ownership to root:root...${NC}"
chown root:root "$ACME_FILE"

# Verify
FINAL_PERMS=$(stat -c "%a" "$ACME_FILE")
FINAL_OWNER=$(stat -c "%U:%G" "$ACME_FILE")
FINAL_SIZE=$(stat -c%s "$ACME_FILE")

echo -e "\n${GREEN}✓ ACME file configured successfully!${NC}\n"
echo -e "File: $ACME_FILE"
echo -e "Permissions: $FINAL_PERMS"
echo -e "Owner: $FINAL_OWNER"
echo -e "Size: $FINAL_SIZE bytes"

echo -e "\n${BLUE}Next steps:${NC}"
echo -e "1. Restart Traefik: ${YELLOW}docker-compose restart traefik${NC}"
echo -e "2. Check logs: ${YELLOW}docker logs webbadeploy_traefik -f${NC}"
echo -e "3. Verify certificates are being issued"

echo -e "\n${YELLOW}Note: Traefik will populate this file with certificates automatically${NC}"
echo -e "${YELLOW}when it successfully obtains SSL certificates from Let's Encrypt.${NC}"
