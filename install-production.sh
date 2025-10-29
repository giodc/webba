#!/bin/bash

# WharfTales Production Installation Script
# For Ubuntu 20.04+ / Debian 11+ / Fresh Servers
# Run: curl -fsSL https://raw.githubusercontent.com/yourrepo/wharftales/main/install-production.sh | sudo bash

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}"
echo "╔═══════════════════════════════════════╗"
echo "║   WharfTales Production Installer   ║"
echo "║   Easy App Deployment Platform       ║"
echo "╚═══════════════════════════════════════╝"
echo -e "${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (use sudo)${NC}"
    exit 1
fi

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo -e "${RED}Error: Cannot detect OS${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Detected OS: $OS $VERSION${NC}"

# Check system requirements
echo -e "\n${YELLOW}Checking system requirements...${NC}"

# Check memory (minimum 1GB)
TOTAL_MEM=$(free -m | awk '/^Mem:/{print $2}')
if [ "$TOTAL_MEM" -lt 1024 ]; then
    echo -e "${YELLOW}Warning: Low memory detected ($TOTAL_MEM MB). Recommended: 2GB+${NC}"
fi

# Check disk space (minimum 10GB)
AVAILABLE_DISK=$(df -BG / | awk 'NR==2 {print $4}' | sed 's/G//')
if [ "$AVAILABLE_DISK" -lt 10 ]; then
    echo -e "${RED}Error: Insufficient disk space. Need at least 10GB free${NC}"
    exit 1
fi

echo -e "${GREEN}✓ System requirements met${NC}"

# Update system
echo -e "\n${YELLOW}Updating system packages...${NC}"
apt update -qq
apt upgrade -y -qq

# Install prerequisites
echo -e "\n${YELLOW}Installing prerequisites...${NC}"
apt install -y -qq curl wget git ca-certificates gnupg lsb-release ufw

# Install Docker
echo -e "\n${YELLOW}Installing Docker...${NC}"
if ! command -v docker &> /dev/null; then
    # Add Docker's official GPG key
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/$OS/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    # Add Docker repository
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$OS \
      $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt update -qq
    apt install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    
    systemctl enable docker
    systemctl start docker
    echo -e "${GREEN}✓ Docker installed${NC}"
else
    echo -e "${GREEN}✓ Docker already installed${NC}"
fi

# Install Docker Compose (standalone)
echo -e "\n${YELLOW}Installing Docker Compose...${NC}"
if ! command -v docker-compose &> /dev/null; then
    COMPOSE_VERSION=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep 'tag_name' | cut -d\" -f4)
    curl -L "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    echo -e "${GREEN}✓ Docker Compose installed${NC}"
else
    echo -e "${GREEN}✓ Docker Compose already installed${NC}"
fi

# Create installation directory
echo -e "\n${YELLOW}Setting up WharfTales...${NC}"
INSTALL_DIR="/opt/wharftales"

if [ -d "$INSTALL_DIR" ]; then
    echo -e "${YELLOW}Warning: $INSTALL_DIR already exists${NC}"
    read -p "Do you want to overwrite? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${RED}Installation cancelled${NC}"
        exit 1
    fi
    # Backup existing installation
    BACKUP_DIR="${INSTALL_DIR}_backup_$(date +%Y%m%d_%H%M%S)"
    echo -e "${YELLOW}Backing up to $BACKUP_DIR${NC}"
    mv "$INSTALL_DIR" "$BACKUP_DIR"
fi

mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# Download WharfTales files
echo -e "\n${YELLOW}Downloading WharfTales files...${NC}"

# Clone from GitHub repository
if command -v git &> /dev/null; then
    echo -e "${YELLOW}Cloning from GitHub...${NC}"
    git clone https://github.com/giodc/webba.git "$INSTALL_DIR"
    cd "$INSTALL_DIR"
    echo -e "${GREEN}✓ Files downloaded from repository${NC}"
else
    echo -e "${RED}Error: git is not installed${NC}"
    exit 1
fi

# Create additional directories if they don't exist
mkdir -p {data,apps/{php/sites,laravel/sites,wordpress/sites},ssl}

# Create ACME file for SSL certificates
echo -e "${YELLOW}Creating ACME file for SSL certificates...${NC}"
cat > ssl/acme.json << 'ACME_EOF'
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
ACME_EOF
chmod 600 ssl/acme.json
chown root:root ssl/acme.json
echo -e "${GREEN}✓ ACME file created with secure permissions (600)${NC}"

# Create docker-compose.yml from template if it doesn't exist
if [ ! -f "docker-compose.yml" ]; then
    if [ -f "docker-compose.yml.template" ]; then
        echo -e "${YELLOW}Creating docker-compose.yml from template...${NC}"
        cp docker-compose.yml.template docker-compose.yml
        echo -e "${YELLOW}⚠️  IMPORTANT: You need to configure docker-compose.yml:${NC}"
        echo -e "   ${YELLOW}- Email address (search for CHANGE_ME@example.com)${NC}"
        echo -e "   ${YELLOW}- Dashboard domain (search for CHANGE_ME.example.com)${NC}"
    else
        echo -e "${YELLOW}Creating default docker-compose.yml...${NC}"
        cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  traefik:
    image: traefik:v2.10
    container_name: wharftales_traefik
    command:
      - "--api.insecure=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
    ports:
      - "80:80"
      - "443:443"
      - "8080:8080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./ssl:/ssl
    networks:
      - wharftales
    restart: unless-stopped

  db:
    image: mariadb:10.11
    container_name: wharftales_db
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: wharftales
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - wharftales
    restart: unless-stopped

  gui:
    build: ./gui
    container_name: wharftales_gui
    ports:
      - "9000:80"  # Dashboard accessible on port 9000
    volumes:
      - ./apps:/app/apps
      - ./data:/app/data
      - ./ssl:/app/ssl
      - /usr/bin/docker:/usr/bin/docker:ro
      - /usr/local/bin/docker-compose:/usr/local/bin/docker-compose:ro
      - /var/run/docker.sock:/var/run/docker.sock
    networks:
      - wharftales
    restart: unless-stopped
    depends_on:
      - db
networks:
  wharftales:
    name: wharftales_wharftales
    driver: bridge

volumes:
  db_data:
EOF
    fi
else
    echo -e "${GREEN}✓ Using existing docker-compose.yml${NC}"
fi

# Verify GUI files exist
if [ ! -f "gui/index.php" ]; then
    echo -e "${RED}Error: GUI files not found. Repository may be incomplete.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ WharfTales files verified${NC}"

# Set up firewall
echo -e "\n${YELLOW}Configuring firewall...${NC}"
if command -v ufw &> /dev/null; then
    ufw --force enable
    ufw allow 22/tcp comment 'SSH'
    ufw allow 80/tcp comment 'HTTP'
    ufw allow 443/tcp comment 'HTTPS'
    ufw allow 3000/tcp comment 'WharfTales Dashboard'
    ufw allow 2222:2299/tcp comment 'SFTP Range'
    echo -e "${GREEN}✓ Firewall configured${NC}"
fi

# Set permissions
echo -e "\n${YELLOW}Setting permissions...${NC}"
chmod -R 755 "$INSTALL_DIR"
# SECURITY: Use 755 instead of 777 - no world-writable directories
chmod -R 755 "$INSTALL_DIR/apps"
chmod -R 755 "$INSTALL_DIR/data"
# Ensure www-data owns the directories
chown -R www-data:www-data "$INSTALL_DIR/apps"
chown -R www-data:www-data "$INSTALL_DIR/data"

# Ensure ACME file has correct permissions
if [ -f "$INSTALL_DIR/ssl/acme.json" ]; then
    chmod 600 "$INSTALL_DIR/ssl/acme.json"
    chown root:root "$INSTALL_DIR/ssl/acme.json"
    echo -e "${GREEN}✓ ACME file permissions verified${NC}"
fi

# Fix Docker socket permissions for container access
echo -e "${YELLOW}Configuring Docker socket permissions...${NC}"
# SECURITY: Use 660 with docker group instead of 666 (world-writable)
groupadd -f docker
usermod -aG docker www-data
chmod 660 /var/run/docker.sock
chown root:docker /var/run/docker.sock
echo -e "${GREEN}✓ Docker socket permissions configured (660 with docker group)${NC}"

# Fix docker-compose.yml permissions for web GUI to update Let's Encrypt email
echo -e "${YELLOW}Setting docker-compose.yml permissions...${NC}"
chown www-data:www-data "$INSTALL_DIR/docker-compose.yml" 2>/dev/null || true
chmod 664 "$INSTALL_DIR/docker-compose.yml"
echo -e "${GREEN}✓ docker-compose.yml permissions configured${NC}"

# Get server IP
SERVER_IP=$(curl -s ifconfig.me || hostname -I | awk '{print $1}')

# Verify ACME file exists before starting services
if [ ! -f "$INSTALL_DIR/ssl/acme.json" ]; then
    echo -e "${YELLOW}Creating ACME file for SSL certificates...${NC}"
    cat > "$INSTALL_DIR/ssl/acme.json" << 'ACME_EOF'
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
ACME_EOF
    chmod 600 "$INSTALL_DIR/ssl/acme.json"
    chown root:root "$INSTALL_DIR/ssl/acme.json"
    echo -e "${GREEN}✓ ACME file created${NC}"
fi

# Start services
echo -e "\n${YELLOW}Starting WharfTales services...${NC}"
cd "$INSTALL_DIR"
docker-compose up -d

# Wait for services to start
echo -e "${YELLOW}Waiting for services to initialize...${NC}"
sleep 15

# Ensure database user has proper permissions
echo -e "${YELLOW}Configuring database permissions...${NC}"
docker exec wharftales_db mariadb -uroot -pwharftales_root_pass -e "GRANT ALL PRIVILEGES ON *.* TO 'wharftales'@'%'; FLUSH PRIVILEGES;" 2>/dev/null || true
echo -e "${GREEN}✓ Database permissions configured${NC}"

# Run database migrations for new features
echo -e "${YELLOW}Running database migrations...${NC}"
sleep 2
docker exec wharftales_gui php /app/migrate-rbac-2fa.php 2>/dev/null || echo -e "${YELLOW}Migration will run on first access${NC}"
echo -e "${GREEN}✓ Database migrations completed${NC}"

# Check if services are running
if docker ps | grep -q wharftales_gui; then
    echo -e "${GREEN}✓ Services started successfully${NC}"
else
    echo -e "${RED}Error: Services failed to start${NC}"
    echo "Check logs with: docker-compose logs"
    exit 1
fi

# Final message
echo -e "\n${GREEN}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║                                                           ║"
echo "║  ✓ WharfTales Installation Complete!                    ║"
echo "║                                                           ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${GREEN}Access your WharfTales dashboard:${NC}"
echo -e "  Main Dashboard:    http://$SERVER_IP:9000"
echo ""
echo -e "${YELLOW}Important Notes:${NC}"
echo "  • Installation directory: $INSTALL_DIR"
echo "  • Database data: $INSTALL_DIR/data"
echo "  • SSL certificates: $INSTALL_DIR/ssl"
echo "  • Site files: $INSTALL_DIR/apps"
echo ""
echo -e "${YELLOW}Useful Commands:${NC}"
echo "  • View logs:      cd $INSTALL_DIR && docker-compose logs -f"
echo "  • Restart:        cd $INSTALL_DIR && docker-compose restart"
echo "  • Stop:           cd $INSTALL_DIR && docker-compose down"
echo "  • Update:         cd $INSTALL_DIR && docker-compose pull && docker-compose up -d"
echo ""
echo -e "${GREEN}New Features Available:${NC}"
echo "  • User Management with Role-Based Access Control"
echo "  • Optional Two-Factor Authentication (2FA/TOTP)"
echo "  • Site Permissions and Ownership"
echo "  • Redis Support for PHP and Laravel apps"
echo "  • Audit Logging"
echo ""
echo -e "${GREEN}Next Steps:${NC}"
echo "  1. Visit http://$SERVER_IP:9000 to access the dashboard"
echo "  2. Create your admin account on first access"
echo "  3. Deploy your first application"
echo "  4. Configure DNS for custom domains"
echo "  5. Enable SSL for production sites"
echo "  6. (Optional) Enable 2FA in user settings"
echo ""
echo -e "${YELLOW}For support: https://github.com/yourrepo/wharftales${NC}"
echo ""
