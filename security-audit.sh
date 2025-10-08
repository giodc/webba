#!/bin/bash

# Webbadeploy Security Audit Script
# Checks for common security issues and compromises

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  Webbadeploy Security Audit Script   ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""

ISSUES_FOUND=0

# Check 1: File Permissions
echo -e "${YELLOW}[1/10] Checking file permissions...${NC}"
if [ -d "/opt/webbadeploy/apps" ]; then
    APPS_PERMS=$(stat -c "%a" /opt/webbadeploy/apps)
    if [ "$APPS_PERMS" == "777" ]; then
        echo -e "${RED}  ✗ CRITICAL: /opt/webbadeploy/apps has 777 permissions (world-writable)${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    else
        echo -e "${GREEN}  ✓ /opt/webbadeploy/apps permissions OK ($APPS_PERMS)${NC}"
    fi
fi

if [ -d "/opt/webbadeploy/data" ]; then
    DATA_PERMS=$(stat -c "%a" /opt/webbadeploy/data)
    if [ "$DATA_PERMS" == "777" ]; then
        echo -e "${RED}  ✗ CRITICAL: /opt/webbadeploy/data has 777 permissions (world-writable)${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    else
        echo -e "${GREEN}  ✓ /opt/webbadeploy/data permissions OK ($DATA_PERMS)${NC}"
    fi
fi

# Check 2: Docker Socket Permissions
echo -e "\n${YELLOW}[2/10] Checking Docker socket permissions...${NC}"
if [ -S "/var/run/docker.sock" ]; then
    SOCKET_PERMS=$(stat -c "%a" /var/run/docker.sock)
    if [ "$SOCKET_PERMS" == "666" ]; then
        echo -e "${RED}  ✗ CRITICAL: Docker socket has 666 permissions (world-writable)${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    else
        echo -e "${GREEN}  ✓ Docker socket permissions OK ($SOCKET_PERMS)${NC}"
    fi
fi

# Check 3: Exposed Ports
echo -e "\n${YELLOW}[3/10] Checking exposed ports...${NC}"
EXPOSED_PORTS=$(ss -tlnp | grep -E "0.0.0.0:(9000|2222|2223|2224|2225|3306)" | wc -l)
if [ "$EXPOSED_PORTS" -gt 0 ]; then
    echo -e "${YELLOW}  ⚠ Warning: Found $EXPOSED_PORTS potentially exposed ports:${NC}"
    ss -tlnp | grep -E "0.0.0.0:(9000|2222|2223|2224|2225|3306)" | awk '{print "    " $4}'
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo -e "${GREEN}  ✓ No exposed management ports found${NC}"
fi

# Check 4: Default Passwords
echo -e "\n${YELLOW}[4/10] Checking for default passwords...${NC}"
if docker exec webbadeploy_db mariadb -uroot -pwebbadeploy_root_pass -e "SELECT 1" &>/dev/null; then
    echo -e "${RED}  ✗ CRITICAL: Default database root password still in use${NC}"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo -e "${GREEN}  ✓ Default database password not detected${NC}"
fi

# Check 5: WordPress Malware Scan
echo -e "\n${YELLOW}[5/10] Scanning WordPress sites for suspicious files...${NC}"
WP_CONTAINERS=$(docker ps --filter "name=wordpress_" --format "{{.Names}}" | grep -v "_db\|_redis\|_sftp")
if [ -n "$WP_CONTAINERS" ]; then
    for container in $WP_CONTAINERS; do
        echo -e "  Scanning ${BLUE}$container${NC}..."
        
        # Check for recently modified PHP files
        RECENT_FILES=$(docker exec $container find /var/www/html -name "*.php" -type f -mtime -7 2>/dev/null | wc -l)
        if [ "$RECENT_FILES" -gt 0 ]; then
            echo -e "${YELLOW}    ⚠ Found $RECENT_FILES PHP files modified in last 7 days${NC}"
        fi
        
        # Check for suspicious patterns
        SUSPICIOUS=$(docker exec $container grep -r "eval(" /var/www/html --include="*.php" 2>/dev/null | wc -l)
        if [ "$SUSPICIOUS" -gt 0 ]; then
            echo -e "${RED}    ✗ Found $SUSPICIOUS files with eval() function${NC}"
            ISSUES_FOUND=$((ISSUES_FOUND + 1))
        fi
        
        BASE64=$(docker exec $container grep -r "base64_decode" /var/www/html --include="*.php" 2>/dev/null | wc -l)
        if [ "$BASE64" -gt 5 ]; then
            echo -e "${YELLOW}    ⚠ Found $BASE64 files with base64_decode (may be normal)${NC}"
        fi
        
        # Check for google verification files
        GOOGLE_FILES=$(docker exec $container find /var/www/html -name "google*.html" 2>/dev/null | wc -l)
        if [ "$GOOGLE_FILES" -gt 0 ]; then
            echo -e "${YELLOW}    ⚠ Found $GOOGLE_FILES Google verification files${NC}"
            docker exec $container find /var/www/html -name "google*.html" 2>/dev/null | sed 's/^/      /'
        fi
    done
else
    echo -e "${GREEN}  ✓ No WordPress containers found${NC}"
fi

# Check 6: SFTP Configuration
echo -e "\n${YELLOW}[6/10] Checking SFTP security...${NC}"
SFTP_CONTAINERS=$(docker ps --filter "name=_sftp" --format "{{.Names}}")
if [ -n "$SFTP_CONTAINERS" ]; then
    for container in $SFTP_CONTAINERS; do
        # Check if SFTP is bound to 0.0.0.0
        PORT_BINDING=$(docker port $container 2>/dev/null | grep "0.0.0.0")
        if [ -n "$PORT_BINDING" ]; then
            echo -e "${RED}  ✗ $container is publicly exposed: $PORT_BINDING${NC}"
            ISSUES_FOUND=$((ISSUES_FOUND + 1))
        else
            echo -e "${GREEN}  ✓ $container is not publicly exposed${NC}"
        fi
    done
else
    echo -e "${GREEN}  ✓ No SFTP containers running${NC}"
fi

# Check 7: Firewall Status
echo -e "\n${YELLOW}[7/10] Checking firewall status...${NC}"
if command -v ufw &> /dev/null; then
    UFW_STATUS=$(ufw status | grep "Status:" | awk '{print $2}')
    if [ "$UFW_STATUS" == "active" ]; then
        echo -e "${GREEN}  ✓ UFW firewall is active${NC}"
    else
        echo -e "${RED}  ✗ UFW firewall is not active${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    fi
else
    echo -e "${YELLOW}  ⚠ UFW not installed${NC}"
fi

# Check 8: SSL Certificates
echo -e "\n${YELLOW}[8/10] Checking SSL certificates...${NC}"
if [ -d "/opt/webbadeploy/ssl" ]; then
    CERT_COUNT=$(find /opt/webbadeploy/ssl -name "*.json" 2>/dev/null | wc -l)
    if [ "$CERT_COUNT" -gt 0 ]; then
        echo -e "${GREEN}  ✓ Found $CERT_COUNT SSL certificate(s)${NC}"
    else
        echo -e "${YELLOW}  ⚠ No SSL certificates found${NC}"
    fi
else
    echo -e "${YELLOW}  ⚠ SSL directory not found${NC}"
fi

# Check 9: Container Security
echo -e "\n${YELLOW}[9/10] Checking container security settings...${NC}"
PRIVILEGED=$(docker ps --format "{{.Names}}" | while read container; do
    docker inspect $container --format '{{.Name}}: Privileged={{.HostConfig.Privileged}}' 2>/dev/null
done | grep "Privileged=true" | wc -l)

if [ "$PRIVILEGED" -gt 0 ]; then
    echo -e "${RED}  ✗ Found $PRIVILEGED privileged container(s)${NC}"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo -e "${GREEN}  ✓ No privileged containers found${NC}"
fi

# Check 10: Recent Docker Events
echo -e "\n${YELLOW}[10/10] Checking recent suspicious Docker events...${NC}"
SUSPICIOUS_EVENTS=$(docker events --since 24h --until 1s 2>/dev/null | grep -E "exec_create|exec_start" | wc -l)
if [ "$SUSPICIOUS_EVENTS" -gt 100 ]; then
    echo -e "${YELLOW}  ⚠ High number of exec commands in last 24h: $SUSPICIOUS_EVENTS${NC}"
else
    echo -e "${GREEN}  ✓ Docker events look normal${NC}"
fi

# Summary
echo -e "\n${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║           Audit Summary               ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"

if [ "$ISSUES_FOUND" -eq 0 ]; then
    echo -e "${GREEN}✓ No critical issues found!${NC}"
    exit 0
else
    echo -e "${RED}✗ Found $ISSUES_FOUND security issue(s)${NC}"
    echo ""
    echo -e "${YELLOW}Recommended actions:${NC}"
    echo "  1. Run: sudo bash /opt/webbadeploy/fix-permissions-secure.sh"
    echo "  2. Review: /opt/webbadeploy/SECURITY_FIXES.md"
    echo "  3. Scan WordPress for malware"
    echo "  4. Change all default passwords"
    echo "  5. Configure firewall rules"
    exit 1
fi
