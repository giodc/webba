#!/bin/bash
# SSL Diagnostic and Fix Script for Webbadeploy
# This script checks all SSL components and provides actionable fixes

set -e

echo "=========================================="
echo "Webbadeploy SSL Diagnostic Tool"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check 1: Traefik Container Status
echo "1. Checking Traefik Container..."
if docker ps | grep -q webbadeploy_traefik; then
    echo -e "${GREEN}✓ Traefik container is running${NC}"
else
    echo -e "${RED}✗ Traefik container is NOT running${NC}"
    echo "  Fix: cd /opt/webbadeploy && docker-compose up -d traefik"
    exit 1
fi
echo ""

# Check 2: Let's Encrypt Email Configuration
echo "2. Checking Let's Encrypt Email..."
EMAIL=$(docker inspect webbadeploy_traefik --format '{{range .Config.Cmd}}{{println .}}{{end}}' | grep "acme.email" | cut -d'=' -f2)
echo "  Current email: $EMAIL"

if echo "$EMAIL" | grep -qE "@example\.(com|net|org)|@test\."; then
    echo -e "${RED}✗ Invalid email domain detected!${NC}"
    echo "  Let's Encrypt will reject certificates with example.com or test domains"
    echo "  Fix: Update email in Settings → SSL Configuration"
    INVALID_EMAIL=1
else
    echo -e "${GREEN}✓ Email looks valid${NC}"
    INVALID_EMAIL=0
fi
echo ""

# Check 3: acme.json File
echo "3. Checking acme.json file..."
ACME_FILE="/opt/webbadeploy/ssl/acme.json"
if [ -f "$ACME_FILE" ]; then
    echo -e "${GREEN}✓ acme.json exists${NC}"
    
    # Check permissions
    PERMS=$(stat -c "%a" "$ACME_FILE")
    if [ "$PERMS" = "600" ]; then
        echo -e "${GREEN}✓ Permissions are correct (600)${NC}"
    else
        echo -e "${YELLOW}⚠ Permissions are $PERMS (should be 600)${NC}"
        echo "  Fix: sudo chmod 600 $ACME_FILE"
    fi
    
    # Check if it has certificates
    CERT_COUNT=$(sudo cat "$ACME_FILE" | grep -c '"Certificates"' || true)
    if sudo cat "$ACME_FILE" | grep -q '"Certificates": null'; then
        echo -e "${YELLOW}⚠ acme.json is empty (no certificates issued yet)${NC}"
        EMPTY_ACME=1
    else
        echo -e "${GREEN}✓ acme.json contains certificate data${NC}"
        EMPTY_ACME=0
    fi
else
    echo -e "${RED}✗ acme.json does NOT exist${NC}"
    echo "  Fix: Will be created automatically when Traefik starts"
    EMPTY_ACME=1
fi
echo ""

# Check 4: Ports 80 and 443
echo "4. Checking ports 80 and 443..."
if netstat -tuln 2>/dev/null | grep -q ":80 " || ss -tuln 2>/dev/null | grep -q ":80 "; then
    echo -e "${GREEN}✓ Port 80 is open${NC}"
else
    echo -e "${RED}✗ Port 80 is NOT listening${NC}"
fi

if netstat -tuln 2>/dev/null | grep -q ":443 " || ss -tuln 2>/dev/null | grep -q ":443 "; then
    echo -e "${GREEN}✓ Port 443 is open${NC}"
else
    echo -e "${RED}✗ Port 443 is NOT listening${NC}"
fi
echo ""

# Check 5: Sites with SSL enabled
echo "5. Checking sites with SSL enabled..."
SITES=$(docker exec webbadeploy_gui php -r "require '/var/www/html/includes/functions.php'; \$db = initDatabase(); \$sites = getAllSites(\$db); foreach(\$sites as \$site) { if(\$site['ssl'] == 1) { echo \$site['id'] . '|' . \$site['name'] . '|' . \$site['domain'] . '|' . \$site['ssl_cert_issued'] . PHP_EOL; } }")

if [ -z "$SITES" ]; then
    echo -e "${YELLOW}⚠ No sites with SSL enabled${NC}"
else
    echo "Sites with SSL:"
    echo "$SITES" | while IFS='|' read -r id name domain cert_issued; do
        if [ "$cert_issued" = "1" ]; then
            echo -e "  - ${GREEN}$domain (Certificate: Issued)${NC}"
        else
            echo -e "  - ${YELLOW}$domain (Certificate: Pending)${NC}"
        fi
    done
fi
echo ""

# Check 6: Recent Traefik Logs
echo "6. Checking recent Traefik logs for errors..."
ERRORS=$(docker logs webbadeploy_traefik --tail 100 2>&1 | grep -iE "error|fail|unable" | tail -5)
if [ -z "$ERRORS" ]; then
    echo -e "${GREEN}✓ No recent errors in Traefik logs${NC}"
else
    echo -e "${YELLOW}⚠ Recent errors found:${NC}"
    echo "$ERRORS" | sed 's/^/  /'
fi
echo ""

# Summary and Recommendations
echo "=========================================="
echo "SUMMARY & RECOMMENDATIONS"
echo "=========================================="

if [ "$INVALID_EMAIL" = "1" ]; then
    echo -e "${RED}CRITICAL: Invalid Let's Encrypt email${NC}"
    echo "  1. Go to your dashboard Settings → SSL Configuration"
    echo "  2. Update email to a real address (not @example.com or @test.*)"
    echo "  3. Run: cd /opt/webbadeploy && docker-compose restart traefik"
    echo ""
fi

if [ "$EMPTY_ACME" = "1" ] && [ "$INVALID_EMAIL" = "0" ]; then
    echo -e "${YELLOW}INFO: acme.json is empty${NC}"
    echo "  This is normal for new installations or after email changes"
    echo "  Certificates will be requested automatically when:"
    echo "  - A site with SSL is deployed"
    echo "  - The domain points to this server"
    echo "  - Ports 80/443 are accessible from the internet"
    echo ""
fi

if [ -n "$SITES" ]; then
    PENDING_COUNT=$(echo "$SITES" | grep -c "|0$" || true)
    if [ "$PENDING_COUNT" -gt 0 ]; then
        echo -e "${YELLOW}INFO: $PENDING_COUNT site(s) with pending certificates${NC}"
        echo "  Certificates will be issued automatically if:"
        echo "  - The Let's Encrypt email is valid"
        echo "  - The domain DNS points to this server"
        echo "  - Ports 80/443 are accessible from the internet"
        echo "  - The site container is running"
        echo ""
    fi
fi

echo "To manually trigger certificate issuance:"
echo "  1. Ensure domain DNS points to this server"
echo "  2. Restart Traefik: docker-compose restart traefik"
echo "  3. Check logs: docker logs webbadeploy_traefik -f"
echo ""

echo "For more details, visit the SSL Debug page in your dashboard"
echo "=========================================="
