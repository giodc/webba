#!/bin/bash

set -e

echo "WharfTales Installation Script"
echo "==============================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (use sudo)"
    exit 1
fi

# Check if this is an update (wharftales already exists)
if [ -d "/opt/wharftales/.git" ]; then
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

# Create wharftales user
echo "Creating wharftales user..."
if ! id "wharftales" &>/dev/null; then
    useradd -m -s /bin/bash wharftales
    usermod -aG docker wharftales
fi

# Set up directories
if [ "$UPDATE_MODE" = true ]; then
    echo "Updating existing installation..."
    cd /opt/wharftales
    
    # Backup critical files before update
    echo "Backing up configurations..."
    BACKUP_DIR="/opt/wharftales/data/backups/update-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    # Backup docker-compose.yml (contains Let's Encrypt email)
    if [ -f "docker-compose.yml" ]; then
        cp docker-compose.yml "$BACKUP_DIR/docker-compose.yml"
        echo "  ✓ Backed up docker-compose.yml"
    fi
    
    # Backup database
    if [ -f "data/database.sqlite" ]; then
        cp data/database.sqlite "$BACKUP_DIR/database.sqlite"
        echo "  ✓ Backed up database"
    fi
    
    # Backup acme.json (SSL certificates)
    if [ -f "ssl/acme.json" ]; then
        cp ssl/acme.json "$BACKUP_DIR/acme.json"
        echo "  ✓ Backed up acme.json"
    fi
    
    # Stash any local changes
    git stash
    
    # Pull latest version
    echo "Pulling latest version from GitHub..."
    git pull origin master
    
    # Restore docker-compose.yml from backup (preserve user settings)
    if [ -f "$BACKUP_DIR/docker-compose.yml" ]; then
        echo "Restoring docker-compose.yml to preserve your settings..."
        cp "$BACKUP_DIR/docker-compose.yml" docker-compose.yml
    fi
    
    # Restore acme.json from backup (preserve SSL certificates)
    if [ -f "$BACKUP_DIR/acme.json" ]; then
        echo "Restoring acme.json to preserve SSL certificates..."
        cp "$BACKUP_DIR/acme.json" ssl/acme.json
        chmod 600 ssl/acme.json
        chown root:root ssl/acme.json
    fi
    
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
    mkdir -p /opt/wharftales/data/backups
    chown -R www-data:www-data /opt/wharftales/data/backups
    
    # Rebuild and restart containers
    echo "Rebuilding containers..."
    docker-compose build --no-cache web-gui
    
    echo "Restarting services..."
    docker-compose down
    docker-compose up -d
    
    # Install MySQL extensions manually (in case build fails)
    echo "Installing MySQL extensions..."
    docker exec -u root wharftales_gui docker-php-ext-install pdo_mysql mysqli 2>/dev/null || true
    docker exec wharftales_gui apache2ctl restart 2>/dev/null || true
    
    # Fix data directory permissions
    echo "Fixing data directory permissions..."
    docker exec -u root wharftales_gui chown -R www-data:www-data /app/data
    docker exec -u root wharftales_gui chmod -R 775 /app/data
    
    echo "Fixing apps directory permissions..."
    docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
    docker exec -u root wharftales_gui chmod -R 775 /app/apps
    
    # Run database migrations
    echo "Running database migrations..."
    sleep 2
    docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || echo "Migration completed or already applied"
    docker exec wharftales_gui php /var/www/html/migrate-php-version.php 2>/dev/null || echo "PHP version migration completed or already applied"
    docker exec wharftales_gui php /var/www/html/migrations/add_github_fields.php 2>/dev/null || echo "GitHub fields migration completed or already applied"
    docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php 2>/dev/null || echo "Site permissions migration completed or already applied"
    
    echo "Ensuring database file has correct permissions..."
    docker exec -u root wharftales_gui bash -c "if [ -f /app/data/database.sqlite ]; then chown www-data:www-data /app/data/database.sqlite && chmod 664 /app/data/database.sqlite; fi"
    
    echo "Importing docker-compose configurations to database..."
    docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php 2>/dev/null || echo "Compose migration completed or already applied"
    
    echo ""
    echo "==============================="
    echo "Update completed successfully!"
    echo "==============================="
    echo "WharfTales has been updated to the latest version."
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
    
    # Check if we're running from /opt/wharftales already
    if [ "$PWD" = "/opt/wharftales" ]; then
        echo "Already in /opt/wharftales, skipping clone..."
    else
        # Clone from GitHub if not already present
        if [ ! -d "/opt/wharftales/.git" ]; then
            echo "Cloning WharfTales from GitHub..."
            git clone https://github.com/giodc/webba.git /opt/wharftales
        fi
    fi
    
    cd /opt/wharftales
    chown -R wharftales:wharftales /opt/wharftales
    
    # Create required directories
    mkdir -p /opt/wharftales/{data,nginx/sites,ssl,apps,web}
    
    # Create ACME file for SSL certificates
    echo "Creating ACME file for SSL certificates..."
    cat > /opt/wharftales/ssl/acme.json << 'ACME_EOF'
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
    chmod 600 /opt/wharftales/ssl/acme.json
    chown root:root /opt/wharftales/ssl/acme.json
    
    # Set proper permissions for data directory (needs to be writable by www-data in container)
    chown -R www-data:www-data /opt/wharftales/data
    chmod -R 775 /opt/wharftales/data
    
    chown -R wharftales:wharftales /opt/wharftales
    
    # Create docker-compose.yml from template if it doesn't exist
    if [ ! -f "/opt/wharftales/docker-compose.yml" ]; then
        if [ -f "/opt/wharftales/docker-compose.yml.template" ]; then
            echo "Creating docker-compose.yml from template..."
            cp /opt/wharftales/docker-compose.yml.template /opt/wharftales/docker-compose.yml
            echo "⚠️  IMPORTANT: Edit docker-compose.yml to configure:"
            echo "   - Email address (search for CHANGE_ME@example.com)"
            echo "   - Dashboard domain (search for CHANGE_ME.example.com)"
        else
            echo "Warning: docker-compose.yml.template not found"
        fi
    fi
    
    # Set permissions on docker-compose.yml
    if [ -f "/opt/wharftales/docker-compose.yml" ]; then
        echo "Setting permissions on docker-compose.yml..."
        chmod 664 /opt/wharftales/docker-compose.yml
        chown www-data:www-data /opt/wharftales/docker-compose.yml
    fi
    
    # Set Docker socket permissions (use docker group instead of world-writable)
    echo "Setting Docker socket permissions..."
    groupadd -f docker
    usermod -aG docker www-data
    chmod 660 /var/run/docker.sock
    chown root:docker /var/run/docker.sock
    
    # Create backup directory
    echo "Creating backup directory..."
    mkdir -p /opt/wharftales/data/backups
    chown -R www-data:www-data /opt/wharftales/data/backups
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

# Verify ACME file exists
if [ ! -f "/opt/wharftales/ssl/acme.json" ]; then
    echo "Creating ACME file for SSL certificates..."
    cat > /opt/wharftales/ssl/acme.json << 'ACME_EOF'
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
    chmod 600 /opt/wharftales/ssl/acme.json
    chown root:root /opt/wharftales/ssl/acme.json
fi

echo "Starting services..."
cd /opt/wharftales
docker-compose up -d

echo "Installing MySQL extensions..."
sleep 5  # Wait for container to start
docker exec -u root wharftales_gui docker-php-ext-install pdo_mysql mysqli 2>/dev/null || true
docker exec wharftales_gui apache2ctl restart 2>/dev/null || true

echo "Fixing data directory permissions..."
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data
docker exec -u root wharftales_gui chmod -R 775 /app/data

echo "Fixing apps directory permissions..."
docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/apps

echo "Running database migrations..."
sleep 2
docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || echo "Migration will run on first access"
docker exec wharftales_gui php /var/www/html/migrate-php-version.php 2>/dev/null || echo "PHP version migration will run on first access"
docker exec wharftales_gui php /var/www/html/migrations/add_github_fields.php 2>/dev/null || echo "GitHub fields migration will run on first access"
docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php 2>/dev/null || echo "Site permissions migration will run on first access"

echo "Ensuring database file has correct permissions..."
docker exec -u root wharftales_gui bash -c "if [ -f /app/data/database.sqlite ]; then chown www-data:www-data /app/data/database.sqlite && chmod 664 /app/data/database.sqlite; fi"

echo "Importing docker-compose configurations to database..."
docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php 2>/dev/null || echo "Compose migration will run on first settings update"

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