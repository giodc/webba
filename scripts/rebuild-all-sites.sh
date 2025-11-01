#!/bin/bash
# Rebuild all existing sites with security improvements

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WHARFTALES_DIR="$(dirname "$SCRIPT_DIR")"

echo "=================================================="
echo "Rebuild All Sites - Security Update"
echo "=================================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
TOTAL=0
SUCCESS=0
FAILED=0
SKIPPED=0

# Find all sites (excluding mariadb)
SITES=$(find "$WHARFTALES_DIR/apps/"{php,wordpress,laravel}"/sites/"* -maxdepth 0 -type d 2>/dev/null | sort)

# Count total sites
TOTAL=$(echo "$SITES" | wc -l)

echo "Found $TOTAL sites to rebuild"
echo ""
echo "This will:"
echo "  1. Rebuild each container with new Dockerfile"
echo "  2. Stop the old container"
echo "  3. Start the new container"
echo ""
read -p "Continue? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

echo ""
echo "Starting rebuild process..."
echo ""

# Process each site
for SITE_DIR in $SITES; do
    SITE_NAME=$(basename "$SITE_DIR")
    
    echo "----------------------------------------"
    echo "Processing: $SITE_NAME"
    echo "----------------------------------------"
    
    # Check if docker-compose.yml exists
    if [ ! -f "$SITE_DIR/docker-compose.yml" ]; then
        echo -e "${YELLOW}⚠ Skipped: No docker-compose.yml found${NC}"
        ((SKIPPED++))
        echo ""
        continue
    fi
    
    cd "$SITE_DIR"
    
    # Check if container is running
    CONTAINER_RUNNING=$(docker-compose ps -q 2>/dev/null | wc -l)
    
    echo "  → Building new image..."
    if docker-compose build --no-cache 2>&1 | grep -q "ERROR\|failed"; then
        echo -e "${RED}✗ Build failed${NC}"
        ((FAILED++))
        echo ""
        continue
    fi
    
    echo "  → Stopping old container..."
    docker-compose down 2>&1 >/dev/null
    
    echo "  → Starting new container..."
    if docker-compose up -d 2>&1 | grep -q "ERROR\|failed"; then
        echo -e "${RED}✗ Start failed${NC}"
        ((FAILED++))
        echo ""
        continue
    fi
    
    # Wait a moment for container to start
    sleep 2
    
    # Verify container is running
    if docker-compose ps | grep -q "Up"; then
        # Get container name
        CONTAINER_NAME=$(docker-compose ps --format "{{.Name}}" | head -1)
        
        # Check user
        USER_INFO=$(docker exec "$CONTAINER_NAME" id 2>/dev/null || echo "unknown")
        
        echo -e "${GREEN}✓ Success${NC}"
        echo "    Container: $CONTAINER_NAME"
        echo "    User: $USER_INFO"
        ((SUCCESS++))
    else
        echo -e "${RED}✗ Container not running${NC}"
        ((FAILED++))
    fi
    
    echo ""
done

echo "=================================================="
echo "Rebuild Complete"
echo "=================================================="
echo ""
echo "Results:"
echo "  Total sites:    $TOTAL"
echo -e "  ${GREEN}Successful:     $SUCCESS${NC}"
echo -e "  ${RED}Failed:         $FAILED${NC}"
echo -e "  ${YELLOW}Skipped:        $SKIPPED${NC}"
echo ""

if [ $FAILED -gt 0 ]; then
    echo -e "${RED}⚠ Some sites failed to rebuild. Check logs above.${NC}"
    exit 1
else
    echo -e "${GREEN}✓ All sites rebuilt successfully!${NC}"
fi
