#!/bin/bash

# WebBadeploy Production Installation Script
# For Ubuntu 20.04+ / Debian 11+ / Fresh Servers
# Run: curl -fsSL https://raw.githubusercontent.com/yourrepo/webbadeploy/main/install-production.sh | sudo bash

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}"
echo "╔═══════════════════════════════════════╗"
echo "║   WebBadeploy Production Installer   ║"
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
echo -e "\n${YELLOW}Setting up WebBadeploy...${NC}"
INSTALL_DIR="/opt/webbadeploy"

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

# Download WebBadeploy files
echo -e "\n${YELLOW}Downloading WebBadeploy files...${NC}"

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

# Create docker-compose.yml if it doesn't exist
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${YELLOW}Creating docker-compose.yml...${NC}"
    cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  traefik:
    image: traefik:v2.10
    container_name: webbadeploy_traefik
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
      - webbadeploy
    restart: unless-stopped

  db:
    image: mariadb:10.11
    container_name: webbadeploy_db
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: webbadeploy
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - webbadeploy
    restart: unless-stopped

  gui:
    build: ./gui
    container_name: webbadeploy_gui
    volumes:
      - ./apps:/app/apps
      - ./data:/app/data
      - ./ssl:/app/ssl
      - /usr/bin/docker:/usr/bin/docker:ro
      - /usr/local/bin/docker-compose:/usr/local/bin/docker-compose:ro
      - /var/run/docker.sock:/var/run/docker.sock
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.gui.rule=PathPrefix(`/`)"
      - "traefik.http.routers.gui.entrypoints=web"
      - "traefik.http.services.gui.loadbalancer.server.port=80"
    networks:
      - webbadeploy
    restart: unless-stopped
    depends_on:
      - db

networks:
  webbadeploy:
    name: webbadeploy_webbadeploy
    driver: bridge

volumes:
  db_data:
EOF
else
    echo -e "${GREEN}✓ Using existing docker-compose.yml${NC}"
fi

# Verify GUI files exist
if [ ! -f "gui/index.php" ]; then
    echo -e "${RED}Error: GUI files not found. Repository may be incomplete.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ WebBadeploy files verified${NC}"

# Set up firewall
echo -e "\n${YELLOW}Configuring firewall...${NC}"
if command -v ufw &> /dev/null; then
    ufw --force enable
    ufw allow 22/tcp comment 'SSH'
    ufw allow 80/tcp comment 'HTTP'
    ufw allow 443/tcp comment 'HTTPS'
    ufw allow 8080/tcp comment 'Traefik Dashboard'
    ufw allow 2222:2299/tcp comment 'SFTP Range'
    echo -e "${GREEN}✓ Firewall configured${NC}"
fi

# Set permissions
echo -e "\n${YELLOW}Setting permissions...${NC}"
chmod -R 755 "$INSTALL_DIR"
chmod -R 777 "$INSTALL_DIR/apps"
chmod -R 777 "$INSTALL_DIR/data"

# Get server IP
SERVER_IP=$(curl -s ifconfig.me || hostname -I | awk '{print $1}')

# Start services
echo -e "\n${YELLOW}Starting WebBadeploy services...${NC}"
cd "$INSTALL_DIR"
docker-compose up -d

# Wait for services to start
echo -e "${YELLOW}Waiting for services to initialize...${NC}"
sleep 10

# Check if services are running
if docker ps | grep -q webbadeploy_gui; then
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
echo "║  ✓ WebBadeploy Installation Complete!                    ║"
echo "║                                                           ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${GREEN}Access your WebBadeploy dashboard:${NC}"
echo -e "  Main Dashboard:    http://$SERVER_IP"
echo -e "  Traefik Dashboard: http://$SERVER_IP:8080"
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
echo -e "${GREEN}Next Steps:${NC}"
echo "  1. Visit http://$SERVER_IP to access the dashboard"
echo "  2. Deploy your first application"
echo "  3. Configure DNS for custom domains"
echo "  4. Enable SSL for production sites"
echo ""
echo -e "${YELLOW}For support: https://github.com/yourrepo/webbadeploy${NC}"
echo ""
