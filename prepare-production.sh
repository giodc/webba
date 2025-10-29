#!/bin/bash

# Quick Production Preparation Script
# This is a wrapper that guides you through the production setup

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

clear

echo -e "${BLUE}"
cat << "EOF"
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║        WharfTales Production Preparation Wizard          ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

echo -e "${GREEN}This wizard will help you prepare WharfTales for production.${NC}\n"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (use sudo)${NC}"
    exit 1
fi

# Step 1: Documentation
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Step 1: Documentation Review${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo "Available documentation:"
echo "  1. PRODUCTION-README.md           - Start here (overview)"
echo "  2. PRODUCTION-DEPLOYMENT-GUIDE.md - Complete guide"
echo "  3. PRODUCTION-QUICK-REFERENCE.md  - Quick commands"
echo "  4. SECURITY-CHECKLIST.md          - Security details"
echo ""

read -p "Have you reviewed the documentation? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Please review PRODUCTION-README.md first:${NC}"
    echo "  cat /opt/wharftales/PRODUCTION-README.md | less"
    exit 0
fi

# Step 2: Backup
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Step 2: Backup Current State${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

read -p "Create backup before proceeding? (recommended) (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    BACKUP_DIR="/opt/wharftales/backups/pre-production-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    echo "Creating backup..."
    cp -r /opt/wharftales/data "$BACKUP_DIR/" 2>/dev/null || true
    cp /opt/wharftales/docker-compose.yml "$BACKUP_DIR/" 2>/dev/null || true
    
    echo -e "${GREEN}✓ Backup created: $BACKUP_DIR${NC}"
fi

# Step 3: Security Check (Dry Run)
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Step 3: Security Check (Dry Run)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo "Running security check without making changes..."
echo ""

bash /opt/wharftales/production-readiness-check.sh --dry-run
CHECK_RESULT=$?

echo ""

if [ $CHECK_RESULT -eq 0 ]; then
    echo -e "${GREEN}✓ No issues found! Ready to proceed.${NC}"
else
    echo -e "${YELLOW}⚠ Issues found. Review the output above.${NC}"
    echo ""
    read -p "Do you want to apply automatic fixes? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo ""
        echo -e "${YELLOW}Applying fixes...${NC}"
        echo ""
        bash /opt/wharftales/production-readiness-check.sh
        echo ""
        echo -e "${GREEN}✓ Fixes applied. Running check again...${NC}"
        echo ""
        bash /opt/wharftales/production-readiness-check.sh --dry-run
    fi
fi

# Step 4: Manual Configuration
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Step 4: Manual Configuration${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo "Some items require manual configuration:"
echo ""
echo "  [ ] Change default database password"
echo "  [ ] Restrict dashboard access (port 9000)"
echo "  [ ] Enable 2FA for admin accounts"
echo "  [ ] Configure SSL certificates"
echo "  [ ] Set up monitoring"
echo "  [ ] Schedule backups"
echo ""
echo "See PRODUCTION-DEPLOYMENT-GUIDE.md for detailed instructions."
echo ""

read -p "Have you completed the manual steps? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo -e "${YELLOW}Please complete manual steps before deploying to production.${NC}"
    echo "Guide: /opt/wharftales/PRODUCTION-DEPLOYMENT-GUIDE.md"
    exit 0
fi

# Step 5: Final Verification
echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Step 5: Final Verification${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo "Running final security check..."
echo ""

bash /opt/wharftales/production-readiness-check.sh --dry-run
FINAL_RESULT=$?

echo ""

if [ $FINAL_RESULT -eq 0 ]; then
    echo -e "${GREEN}"
    cat << "EOF"
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║                  ✓ PRODUCTION READY!                      ║
║                                                            ║
║     Your WharfTales installation is secure and ready     ║
║              for production deployment.                    ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
    
    echo "Next steps:"
    echo "  1. Start services: docker-compose up -d"
    echo "  2. Enable firewall: sudo ufw enable"
    echo "  3. Monitor logs: docker-compose logs -f"
    echo "  4. Test access: http://YOUR_SERVER:9000"
    echo ""
    echo "Maintenance:"
    echo "  • Weekly: sudo bash security-audit.sh"
    echo "  • Monthly: sudo bash production-readiness-check.sh"
    echo ""
else
    echo -e "${RED}"
    cat << "EOF"
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║                ✗ NOT PRODUCTION READY                     ║
║                                                            ║
║         Please fix the issues listed above before         ║
║              deploying to production.                      ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
    
    echo "What to do:"
    echo "  1. Review the issues above"
    echo "  2. Fix critical issues manually"
    echo "  3. Run this wizard again"
    echo ""
    echo "For help:"
    echo "  • Guide: PRODUCTION-DEPLOYMENT-GUIDE.md"
    echo "  • Quick ref: PRODUCTION-QUICK-REFERENCE.md"
    echo ""
fi

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}Production preparation wizard complete!${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
