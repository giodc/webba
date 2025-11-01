#!/bin/bash
# Apply security updates to existing sites by adding user directive

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WHARFTALES_DIR="$(dirname "$SCRIPT_DIR")"

echo "=================================================="
echo "Apply Security to Existing Sites"
echo "=================================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Counters
TOTAL=0
SUCCESS=0
FAILED=0

# Find all sites
SITES=$(find "$WHARFTALES_DIR/apps/"{php,wordpress,laravel}"/sites/"* -maxdepth 0 -type d 2>/dev/null | sort)
TOTAL=$(echo "$SITES" | wc -l)

echo "Found $TOTAL sites to update"
echo ""
echo "This will:"
echo "  1. Add 'user: www-data:www-data' to docker-compose.yml"
echo "  2. Fix file permissions inside containers"
echo "  3. Restart containers with new security settings"
echo ""
read -p "Continue? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

echo ""
echo "Starting security update..."
echo ""

for SITE_DIR in $SITES; do
    SITE_NAME=$(basename "$SITE_DIR")
    
    echo "----------------------------------------"
    echo "Processing: $SITE_NAME"
    echo "----------------------------------------"
    
    if [ ! -f "$SITE_DIR/docker-compose.yml" ]; then
        echo -e "${YELLOW}⚠ Skipped: No docker-compose.yml${NC}"
        echo ""
        continue
    fi
    
    cd "$SITE_DIR"
    
    # Backup original
    sudo cp docker-compose.yml docker-compose.yml.backup
    
    # Check if user directive already exists
    if grep -q "^\s*user:" docker-compose.yml; then
        echo "  → User directive already exists"
    else
        echo "  → Adding user directive..."
        
        # Add user directive after container_name line
        sudo sed -i '/container_name:/a\    user: "33:33"  # www-data user' docker-compose.yml
    fi
    
    # Get container name
    CONTAINER_NAME=$(grep "container_name:" docker-compose.yml | awk '{print $2}')
    
    echo "  → Stopping container..."
    docker-compose down 2>&1 >/dev/null
    
    echo "  → Starting with new security settings..."
    if ! docker-compose up -d 2>&1 | grep -q "ERROR\|failed"; then
        sleep 3
        
        # Fix permissions inside container
        echo "  → Fixing permissions..."
        docker exec "$CONTAINER_NAME" chown -R www-data:www-data /var/www/html 2>/dev/null || true
        
        # Verify container is running
        if docker-compose ps | grep -q "Up"; then
            USER_INFO=$(docker exec "$CONTAINER_NAME" id 2>/dev/null || echo "unknown")
            echo -e "${GREEN}✓ Success${NC}"
            echo "    User: $USER_INFO"
            ((SUCCESS++))
        else
            echo -e "${RED}✗ Container not running${NC}"
            echo "  → Restoring backup..."
            sudo mv docker-compose.yml.backup docker-compose.yml
            docker-compose up -d 2>&1 >/dev/null
            ((FAILED++))
        fi
    else
        echo -e "${RED}✗ Failed to start${NC}"
        echo "  → Restoring backup..."
        sudo mv docker-compose.yml.backup docker-compose.yml
        docker-compose up -d 2>&1 >/dev/null
        ((FAILED++))
    fi
    
    echo ""
done

echo "=================================================="
echo "Security Update Complete"
echo "=================================================="
echo ""
echo "Results:"
echo "  Total sites:    $TOTAL"
echo -e "  ${GREEN}Successful:     $SUCCESS${NC}"
echo -e "  ${RED}Failed:         $FAILED${NC}"
echo ""

if [ $FAILED -gt 0 ]; then
    echo -e "${YELLOW}⚠ Some sites failed. Backups saved as docker-compose.yml.backup${NC}"
    exit 1
else
    echo -e "${GREEN}✓ All sites updated successfully!${NC}"
    echo ""
    echo "Cleaning up backups..."
    sudo find "$WHARFTALES_DIR/apps/"{php,wordpress,laravel}"/sites/"* -name "docker-compose.yml.backup" -delete 2>/dev/null
fi
