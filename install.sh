#!/bin/bash

set -e

echo "Webbadeploy Installation Script"
echo "==============================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (use sudo)"
    exit 1
fi

# Update system
echo "Updating system packages..."
apt update && apt upgrade -y

# Install Docker
echo "Installing Docker..."
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
    systemctl enable docker
    systemctl start docker
fi

# Install Docker Compose
echo "Installing Docker Compose..."
if ! command -v docker-compose &> /dev/null; then
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
fi

# Create webbadeploy user
echo "Creating webbadeploy user..."
if ! id "webbadeploy" &>/dev/null; then
    useradd -m -s /bin/bash webbadeploy
    usermod -aG docker webbadeploy
fi

# Set up directories
echo "Setting up directories..."
mkdir -p /opt/webbadeploy
cp -r * /opt/webbadeploy/
chown -R webbadeploy:webbadeploy /opt/webbadeploy

# Create required directories
mkdir -p /opt/webbadeploy/{data,nginx/sites,ssl,apps,web}
chown -R webbadeploy:webbadeploy /opt/webbadeploy

# Install certbot for SSL
echo "Installing Certbot for SSL certificates..."
apt install -y certbot python3-certbot-nginx

# Set up firewall
echo "Configuring firewall..."
if command -v ufw &> /dev/null; then
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
fi

echo "Installation completed!"
echo "Navigate to /opt/webbadeploy and run 'docker-compose up -d' to start the services"
echo "Access the web GUI at http://your-server-ip"