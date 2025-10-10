#!/bin/bash

set -e

echo "Webbadeploy Installation Script"
echo "==============================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (use sudo)"
    exit 1
fi

# Check if this is an update (webbadeploy already exists)
if [ -d "/opt/webbadeploy/.git" ]; then
    echo "Existing installation detected. Running update mode..."
    UPDATE_MODE=true
else
    echo "New installation mode..."
    UPDATE_MODE=false
fi

# Update system
echo "Updating system packages..."
apt update && apt upgrade -y

# Install git (needed for cloning)
echo "Installing git..."
apt install -y git

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
if [ "$UPDATE_MODE" = true ]; then
    echo "Updating existing installation..."
    cd /opt/webbadeploy
    
    # Stash any local changes
    git stash
    
    # Pull latest version
    echo "Pulling latest version from GitHub..."
    git pull origin master
    
    # Set permissions on docker-compose.yml
    echo "Setting permissions on docker-compose.yml..."
    chmod 664 docker-compose.yml
    chown www-data:www-data docker-compose.yml
    
    # Set Docker socket permissions (use docker group instead of world-writable)
    echo "Setting Docker socket permissions..."
    groupadd -f docker
    usermod -aG docker www-data
    chmod 660 /var/run/docker.sock
    chown root:docker /var/run/docker.sock
    
    # Create backup directory if it doesn't exist
    echo "Ensuring backup directory exists..."
    mkdir -p /opt/webbadeploy/data/backups
    chown -R www-data:www-data /opt/webbadeploy/data/backups
    
    # Rebuild and restart containers
    echo "Rebuilding containers..."
    docker-compose build --no-cache web-gui
    
    echo "Restarting services..."
    docker-compose down
    docker-compose up -d
    
    # Install MySQL extensions manually (in case build fails)
    echo "Installing MySQL extensions..."
    docker exec -u root webbadeploy_gui docker-php-ext-install pdo_mysql mysqli 2>/dev/null || true
    docker exec webbadeploy_gui apache2ctl restart 2>/dev/null || true
    
    # Run database migrations
    echo "Running database migrations..."
    sleep 2
    docker exec webbadeploy_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || echo "Migration completed or already applied"
    
    echo ""
    echo "==============================="
    echo "Update completed successfully!"
    echo "==============================="
    echo "Webbadeploy has been updated to the latest version."
    echo "Access the dashboard at: http://your-server-ip:9000"
    echo ""
    echo "New features in this update:"
    echo "  • User Management with Role-Based Access Control"
    echo "  • Optional Two-Factor Authentication (2FA/TOTP)"
    echo "  • Site Permissions and Ownership"
    echo "  • Redis Support for PHP and Laravel apps"
    echo "  • Audit Logging"
    exit 0
else
    echo "Setting up directories..."
    
    # Check if we're running from /opt/webbadeploy already
    if [ "$PWD" = "/opt/webbadeploy" ]; then
        echo "Already in /opt/webbadeploy, skipping clone..."
    else
        # Clone from GitHub if not already present
        if [ ! -d "/opt/webbadeploy/.git" ]; then
            echo "Cloning Webbadeploy from GitHub..."
            git clone https://github.com/giodc/webba.git /opt/webbadeploy
        fi
    fi
    
    cd /opt/webbadeploy
    chown -R webbadeploy:webbadeploy /opt/webbadeploy
    
    # Create required directories
    mkdir -p /opt/webbadeploy/{data,nginx/sites,ssl,apps,web}
    
    # Set proper permissions for data directory (needs to be writable by www-data in container)
    chown -R www-data:www-data /opt/webbadeploy/data
    chmod -R 775 /opt/webbadeploy/data
    
    chown -R webbadeploy:webbadeploy /opt/webbadeploy
    
    # Set permissions on docker-compose.yml
    if [ -f "/opt/webbadeploy/docker-compose.yml" ]; then
        echo "Setting permissions on docker-compose.yml..."
        chmod 664 /opt/webbadeploy/docker-compose.yml
        chown www-data:www-data /opt/webbadeploy/docker-compose.yml
    else
        echo "Warning: docker-compose.yml not found, will be created on first run"
    fi
    
    # Set Docker socket permissions (use docker group instead of world-writable)
    echo "Setting Docker socket permissions..."
    groupadd -f docker
    usermod -aG docker www-data
    chmod 660 /var/run/docker.sock
    chown root:docker /var/run/docker.sock
    
    # Create backup directory
    echo "Creating backup directory..."
    mkdir -p /opt/webbadeploy/data/backups
    chown -R www-data:www-data /opt/webbadeploy/data/backups
fi

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

echo "Starting services..."
cd /opt/webbadeploy
docker-compose up -d

echo "Installing MySQL extensions..."
sleep 5  # Wait for container to start
docker exec -u root webbadeploy_gui docker-php-ext-install pdo_mysql mysqli 2>/dev/null || true
docker exec webbadeploy_gui apache2ctl restart 2>/dev/null || true

echo "Running database migrations..."
sleep 2
docker exec webbadeploy_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || echo "Migration will run on first access"

echo ""
echo "==============================="
echo "Installation completed!"
echo "==============================="
echo "Access the web GUI at http://your-server-ip:9000"
echo "Default credentials will be created on first access"
echo ""
echo "New features available:"
echo "  • User Management with Role-Based Access Control"
echo "  • Optional Two-Factor Authentication (2FA/TOTP)"
echo "  • Site Permissions and Ownership"
echo "  • Redis Support for PHP and Laravel apps"
echo "  • Audit Logging"