#!/bin/bash

# Webbadeploy Production Readiness & Security Hardening Script
# Run: sudo bash production-readiness-check.sh [--dry-run]

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

ISSUES_FOUND=0
WARNINGS_FOUND=0
FIXES_APPLIED=0
DRY_RUN=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --help)
            echo "Usage: sudo bash production-readiness-check.sh [OPTIONS]"
            echo "Options:"
            echo "  --dry-run    Check only, don't apply fixes"
            echo "  --help       Show this help message"
            exit 0 ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done

echo -e "${BLUE}${BOLD}"
echo "╔════════════════════════════════════════════════════╗"
echo "║   Webbadeploy Production Readiness Check          ║"
echo "║   Security Hardening & Verification Script        ║"
echo "╚════════════════════════════════════════════════════╝"
echo -e "${NC}"

[ "$DRY_RUN" = true ] && echo -e "${CYAN}Running in DRY-RUN mode (no changes will be made)${NC}\n"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (use sudo)${NC}"
    exit 1
fi

WEBBADEPLOY_DIR="/opt/webbadeploy"
if [ ! -d "$WEBBADEPLOY_DIR" ]; then
    echo -e "${RED}Error: Webbadeploy directory not found at $WEBBADEPLOY_DIR${NC}"
    exit 1
fi

cd "$WEBBADEPLOY_DIR"

# ============================================================================
# SECTION 1: SYSTEM SECURITY
# ============================================================================
echo -e "${BLUE}${BOLD}[SECTION 1/8] System Security${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -e "\n${YELLOW}[1.1] Checking firewall configuration...${NC}"
if command -v ufw &> /dev/null; then
    UFW_STATUS=$(ufw status | grep "Status:" | awk '{print $2}')
    if [ "$UFW_STATUS" == "active" ]; then
        echo -e "${GREEN}  ✓ UFW firewall is active${NC}"
        
        if ! ufw status | grep -q "80/tcp"; then
            echo -e "${YELLOW}  ⚠ Port 80 (HTTP) not allowed${NC}"
            WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
            [ "$DRY_RUN" = false ] && ufw allow 80/tcp && FIXES_APPLIED=$((FIXES_APPLIED + 1))
        fi
        
        if ! ufw status | grep -q "443/tcp"; then
            echo -e "${YELLOW}  ⚠ Port 443 (HTTPS) not allowed${NC}"
            WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
            [ "$DRY_RUN" = false ] && ufw allow 443/tcp && FIXES_APPLIED=$((FIXES_APPLIED + 1))
        fi
        
        if ufw status | grep -q "9000.*ALLOW.*Anywhere"; then
            echo -e "${RED}  ✗ CRITICAL: Port 9000 (Dashboard) is publicly accessible!${NC}"
            ISSUES_FOUND=$((ISSUES_FOUND + 1))
        fi
    else
        echo -e "${RED}  ✗ CRITICAL: UFW firewall is not active${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    fi
else
    echo -e "${RED}  ✗ UFW not installed${NC}"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
fi

echo -e "\n${YELLOW}[1.2] Checking automatic security updates...${NC}"
if dpkg -l | grep -q unattended-upgrades; then
    echo -e "${GREEN}  ✓ Unattended upgrades installed${NC}"
else
    echo -e "${YELLOW}  ⚠ Automatic security updates not configured${NC}"
    WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
fi

echo -e "\n${YELLOW}[1.3] Checking fail2ban...${NC}"
if command -v fail2ban-client &> /dev/null && systemctl is-active --quiet fail2ban; then
    echo -e "${GREEN}  ✓ Fail2ban is active${NC}"
else
    echo -e "${YELLOW}  ⚠ Fail2ban not installed/active (recommended)${NC}"
    WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
fi

# ============================================================================
# SECTION 2: FILE PERMISSIONS & OWNERSHIP
# ============================================================================
echo -e "\n${BLUE}${BOLD}[SECTION 2/8] File Permissions & Ownership${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check_and_fix_perms() {
    local path=$1 expected_perms=$2 expected_owner=$3 critical=$4
    
    [ ! -e "$path" ] && echo -e "${YELLOW}  ⚠ $path does not exist${NC}" && return
    
    current_perms=$(stat -c "%a" "$path")
    current_owner=$(stat -c "%U:%G" "$path")
    
    if [ "$current_perms" != "$expected_perms" ]; then
        if [ "$critical" = "true" ]; then
            echo -e "${RED}  ✗ CRITICAL: $path has $current_perms (expected $expected_perms)${NC}"
            ISSUES_FOUND=$((ISSUES_FOUND + 1))
        else
            echo -e "${YELLOW}  ⚠ $path has $current_perms (expected $expected_perms)${NC}"
            WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
        fi
        
        if [ "$DRY_RUN" = false ]; then
            chmod "$expected_perms" "$path"
            echo -e "${GREEN}    ✓ Fixed permissions to $expected_perms${NC}"
            FIXES_APPLIED=$((FIXES_APPLIED + 1))
        fi
    else
        echo -e "${GREEN}  ✓ $path permissions OK ($current_perms)${NC}"
    fi
    
    if [ "$current_owner" != "$expected_owner" ] && [ "$DRY_RUN" = false ]; then
        chown "$expected_owner" "$path"
        FIXES_APPLIED=$((FIXES_APPLIED + 1))
    fi
}

echo -e "\n${YELLOW}[2.1] Checking directory permissions...${NC}"
if id -u www-data &>/dev/null; then
    check_and_fix_perms "$WEBBADEPLOY_DIR/data" "755" "www-data:www-data" "true"
    check_and_fix_perms "$WEBBADEPLOY_DIR/apps" "755" "www-data:www-data" "true"
    check_and_fix_perms "$WEBBADEPLOY_DIR/ssl" "750" "root:www-data" "true"
    check_and_fix_perms "$WEBBADEPLOY_DIR/docker-compose.yml" "640" "root:www-data" "true"
    
    [ -f "$WEBBADEPLOY_DIR/data/database.sqlite" ] && \
        check_and_fix_perms "$WEBBADEPLOY_DIR/data/database.sqlite" "664" "www-data:www-data" "true"
else
    echo -e "${RED}  ✗ www-data user does not exist${NC}"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
fi

echo -e "\n${YELLOW}[2.2] Scanning for world-writable files...${NC}"
WORLD_WRITABLE=$(find "$WEBBADEPLOY_DIR" -type f -perm -002 2>/dev/null | wc -l)
if [ "$WORLD_WRITABLE" -gt 0 ]; then
    echo -e "${RED}  ✗ Found $WORLD_WRITABLE world-writable files${NC}"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
    [ "$DRY_RUN" = false ] && find "$WEBBADEPLOY_DIR" -type f -perm -002 -exec chmod o-w {} \; && FIXES_APPLIED=$((FIXES_APPLIED + 1))
else
    echo -e "${GREEN}  ✓ No world-writable files found${NC}"
fi

# ============================================================================
# SECTION 3: DOCKER SECURITY
# ============================================================================
echo -e "\n${BLUE}${BOLD}[SECTION 3/8] Docker Security${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -e "\n${YELLOW}[3.1] Checking Docker socket permissions...${NC}"
if [ -S "/var/run/docker.sock" ]; then
    SOCKET_PERMS=$(stat -c "%a" /var/run/docker.sock)
    if [ "$SOCKET_PERMS" == "666" ]; then
        echo -e "${RED}  ✗ CRITICAL: Docker socket has 666 permissions (world-writable)${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
        [ "$DRY_RUN" = false ] && chmod 660 /var/run/docker.sock && FIXES_APPLIED=$((FIXES_APPLIED + 1))
    elif [ "$SOCKET_PERMS" == "660" ]; then
        echo -e "${GREEN}  ✓ Docker socket permissions OK (660)${NC}"
    else
        echo -e "${YELLOW}  ⚠ Docker socket has unusual permissions: $SOCKET_PERMS${NC}"
        WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
    fi
fi

echo -e "\n${YELLOW}[3.2] Checking Docker GID configuration...${NC}"
SYSTEM_DOCKER_GID=$(getent group docker | cut -d: -f3)
COMPOSE_DOCKER_GID=$(grep "DOCKER_GID:" docker-compose.yml | awk '{print $2}' | head -1)

if [ "$SYSTEM_DOCKER_GID" != "$COMPOSE_DOCKER_GID" ]; then
    echo -e "${RED}  ✗ Docker GID mismatch: System=$SYSTEM_DOCKER_GID, Compose=$COMPOSE_DOCKER_GID${NC}"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
    [ "$DRY_RUN" = false ] && sed -i "s/DOCKER_GID: .*/DOCKER_GID: $SYSTEM_DOCKER_GID/" docker-compose.yml && FIXES_APPLIED=$((FIXES_APPLIED + 1))
else
    echo -e "${GREEN}  ✓ Docker GID matches (${SYSTEM_DOCKER_GID})${NC}"
fi

echo -e "\n${YELLOW}[3.3] Checking for privileged containers...${NC}"
if docker ps &>/dev/null; then
    PRIVILEGED=$(docker ps --format "{{.Names}}" | while read container; do
        docker inspect $container --format '{{.HostConfig.Privileged}}' 2>/dev/null
    done | grep -c "true" || echo "0")
    
    if [ "$PRIVILEGED" -gt 0 ]; then
        echo -e "${RED}  ✗ Found $PRIVILEGED privileged container(s)${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    else
        echo -e "${GREEN}  ✓ No privileged containers found${NC}"
    fi
fi

# ============================================================================
# SECTION 4: DATABASE SECURITY
# ============================================================================
echo -e "\n${BLUE}${BOLD}[SECTION 4/8] Database Security${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -e "\n${YELLOW}[4.1] Checking for default database passwords...${NC}"
if docker ps | grep -q webbadeploy_db; then
    if docker exec webbadeploy_db mariadb -uroot -pwebbadeploy_root_pass -e "SELECT 1" &>/dev/null; then
        echo -e "${RED}  ✗ CRITICAL: Default database root password still in use!${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    else
        echo -e "${GREEN}  ✓ Default database password not detected${NC}"
    fi
else
    echo -e "${YELLOW}  ⚠ Database container not running${NC}"
fi

echo -e "\n${YELLOW}[4.2] Checking SQLite database security...${NC}"
if [ -f "$WEBBADEPLOY_DIR/data/database.sqlite" ]; then
    DB_PERMS=$(stat -c "%a" "$WEBBADEPLOY_DIR/data/database.sqlite")
    if [ "$DB_PERMS" == "777" ] || [ "$DB_PERMS" == "666" ]; then
        echo -e "${RED}  ✗ CRITICAL: Database file has insecure permissions ($DB_PERMS)${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
        if [ "$DRY_RUN" = false ]; then
            chmod 664 "$WEBBADEPLOY_DIR/data/database.sqlite"
            chown www-data:www-data "$WEBBADEPLOY_DIR/data/database.sqlite"
            FIXES_APPLIED=$((FIXES_APPLIED + 1))
        fi
    else
        echo -e "${GREEN}  ✓ Database permissions OK ($DB_PERMS)${NC}"
    fi
    
    BACKUP_COUNT=$(find "$WEBBADEPLOY_DIR/backups" -name "*.sqlite" 2>/dev/null | wc -l)
    if [ "$BACKUP_COUNT" -gt 0 ]; then
        echo -e "${GREEN}  ✓ Found $BACKUP_COUNT database backup(s)${NC}"
    else
        echo -e "${YELLOW}  ⚠ No database backups found${NC}"
        WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
    fi
fi

# ============================================================================
# SECTION 5: APPLICATION SECURITY
# ============================================================================
echo -e "\n${BLUE}${BOLD}[SECTION 5/8] Application Security${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -e "\n${YELLOW}[5.1] Checking encryption configuration...${NC}"
if docker ps | grep -q webbadeploy_gui; then
    ENCRYPTION_KEY=$(docker exec webbadeploy_gui php -r "
        require_once '/var/www/html/includes/functions.php';
        \$db = initDatabase();
        \$key = getSetting(\$db, 'encryption_key');
        echo !empty(\$key) ? 'EXISTS' : 'MISSING';
    " 2>/dev/null || echo "ERROR")
    
    if [ "$ENCRYPTION_KEY" == "EXISTS" ]; then
        echo -e "${GREEN}  ✓ Encryption key configured${NC}"
    else
        echo -e "${RED}  ✗ CRITICAL: Encryption key not configured${NC}"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    fi
else
    echo -e "${YELLOW}  ⚠ GUI container not running${NC}"
fi

echo -e "\n${YELLOW}[5.2] Checking admin account security...${NC}"
if docker ps | grep -q webbadeploy_gui; then
    TWO_FA_ENABLED=$(docker exec webbadeploy_gui php -r "
        require_once '/var/www/html/includes/functions.php';
        \$db = initDatabase();
        \$stmt = \$db->prepare('SELECT COUNT(*) as count FROM users WHERE two_factor_enabled = 1');
        \$stmt->execute();
        \$result = \$stmt->fetch();
        echo \$result['count'];
    " 2>/dev/null || echo "0")
    
    if [ "$TWO_FA_ENABLED" -gt 0 ]; then
        echo -e "${GREEN}  ✓ 2FA enabled for $TWO_FA_ENABLED user(s)${NC}"
    else
        echo -e "${YELLOW}  ⚠ No users have 2FA enabled (recommended for production)${NC}"
        WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
    fi
fi

# ============================================================================
# SECTION 6: SSL/TLS CONFIGURATION
# ============================================================================
echo -e "\n${BLUE}${BOLD}[SECTION 6/8] SSL/TLS Configuration${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -e "\n${YELLOW}[6.1] Checking SSL certificates...${NC}"
if [ -d "$WEBBADEPLOY_DIR/ssl" ]; then
    CERT_COUNT=$(find "$WEBBADEPLOY_DIR/ssl" -name "*.json" 2>/dev/null | wc -l)
    if [ "$CERT_COUNT" -gt 0 ]; then
        echo -e "${GREEN}  ✓ Found $CERT_COUNT SSL certificate(s)${NC}"
        
        if [ -f "$WEBBADEPLOY_DIR/ssl/acme.json" ]; then
            ACME_PERMS=$(stat -c "%a" "$WEBBADEPLOY_DIR/ssl/acme.json")
            if [ "$ACME_PERMS" == "600" ]; then
                echo -e "${GREEN}  ✓ acme.json permissions OK (600)${NC}"
            else
                echo -e "${RED}  ✗ acme.json has insecure permissions ($ACME_PERMS)${NC}"
                ISSUES_FOUND=$((ISSUES_FOUND + 1))
                [ "$DRY_RUN" = false ] && chmod 600 "$WEBBADEPLOY_DIR/ssl/acme.json" && FIXES_APPLIED=$((FIXES_APPLIED + 1))
            fi
        fi
    else
        echo -e "${YELLOW}  ⚠ No SSL certificates found${NC}"
        WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
    fi
fi

echo -e "\n${YELLOW}[6.2] Checking Traefik configuration...${NC}"
if docker ps | grep -q webbadeploy_traefik; then
    echo -e "${GREEN}  ✓ Traefik is running${NC}"
    if grep -q "api.dashboard=false" docker-compose.yml; then
        echo -e "${GREEN}  ✓ Traefik dashboard is disabled${NC}"
    else
        echo -e "${YELLOW}  ⚠ Traefik dashboard may be exposed${NC}"
        WARNINGS_FOUND=$((WARNINGS_FOUND + 1))
    fi
fi

# ============================================================================
# SECTION 7: EXPOSED SERVICES
# ============================================================================
echo -e "\n${BLUE}${BOLD}[SECTION 7/8] Exposed Services & Ports${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -e "\n${YELLOW}[7.1] Checking publicly exposed ports...${NC}"
EXPOSED_PORTS=$(ss -tlnp 2>/dev/null | grep -E "0.0.0.0:(9000|2222|2223|2224|3306|5432|6379)" || true)
if [ -n "$EXPOSED_PORTS" ]; then
    echo -e "${RED}  ✗ CRITICAL: Sensitive ports exposed to 0.0.0.0${NC}"
    echo "$EXPOSED_PORTS" | awk '{print "    " $4}'
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo -e "${GREEN}  ✓ No sensitive ports publicly exposed${NC}"
fi

# ============================================================================
# SECTION 8: MALWARE & INTEGRITY CHECKS
# ============================================================================
echo -e "\n${BLUE}${BOLD}[SECTION 8/8] Malware & Integrity Checks${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -e "\n${YELLOW}[8.1] Scanning WordPress sites for malware...${NC}"
WP_CONTAINERS=$(docker ps --filter "name=wordpress_" --format "{{.Names}}" 2>/dev/null | grep -v "_db\|_redis\|_sftp" || true)
if [ -n "$WP_CONTAINERS" ]; then
    for container in $WP_CONTAINERS; do
        SUSPICIOUS=$(docker exec $container grep -r "eval(" /var/www/html --include="*.php" 2>/dev/null | wc -l || echo "0")
        if [ "$SUSPICIOUS" -gt 0 ]; then
            echo -e "${RED}  ✗ $container: Found $SUSPICIOUS files with eval()${NC}"
            ISSUES_FOUND=$((ISSUES_FOUND + 1))
        fi
    done
    echo -e "${GREEN}  ✓ Scanned $(echo "$WP_CONTAINERS" | wc -l) WordPress container(s)${NC}"
else
    echo -e "${GREEN}  ✓ No WordPress containers found${NC}"
fi

# ============================================================================
# FINAL SUMMARY
# ============================================================================
echo -e "\n${BLUE}${BOLD}"
echo "╔════════════════════════════════════════════════════╗"
echo "║              Security Audit Summary                ║"
echo "╚════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${CYAN}Critical Issues:${NC}     $ISSUES_FOUND"
echo -e "${CYAN}Warnings:${NC}            $WARNINGS_FOUND"
[ "$DRY_RUN" = false ] && echo -e "${CYAN}Fixes Applied:${NC}       $FIXES_APPLIED"
echo ""

if [ "$ISSUES_FOUND" -eq 0 ] && [ "$WARNINGS_FOUND" -eq 0 ]; then
    echo -e "${GREEN}${BOLD}✓ PRODUCTION READY!${NC}"
    echo -e "${GREEN}Your Webbadeploy installation is secure and ready for production.${NC}"
    exit 0
elif [ "$ISSUES_FOUND" -eq 0 ]; then
    echo -e "${YELLOW}${BOLD}⚠ MOSTLY READY${NC}"
    echo -e "${YELLOW}No critical issues, but $WARNINGS_FOUND warning(s) should be reviewed.${NC}"
    echo ""
    echo -e "${CYAN}Recommendations:${NC}"
    echo "  • Review warnings above"
    echo "  • Enable 2FA for admin accounts"
    echo "  • Configure SSL certificates"
    echo "  • Set up regular backups"
    exit 0
else
    echo -e "${RED}${BOLD}✗ NOT PRODUCTION READY${NC}"
    echo -e "${RED}Found $ISSUES_FOUND critical issue(s) that must be fixed.${NC}"
    echo ""
    echo -e "${CYAN}Required Actions:${NC}"
    echo "  1. Fix all critical issues listed above"
    echo "  2. Run this script again to verify"
    echo "  3. Review SECURITY-CHECKLIST.md"
    echo ""
    if [ "$DRY_RUN" = true ]; then
        echo -e "${YELLOW}Run without --dry-run to auto-fix some issues:${NC}"
        echo "  sudo bash production-readiness-check.sh"
    fi
    exit 1
fi
