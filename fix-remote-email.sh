#!/bin/bash
# Fix ACME email on remote server by syncing docker-compose.yml with database settings
# Run this on the REMOTE server where email is already set in GUI

set -e

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║        Fix ACME Email from Database to Docker Compose     ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

cd /opt/webbadeploy || exit 1

# Get email from database (GUI settings)
echo "1. Reading email from database (GUI settings)..."
if [ -f "data/database.sqlite" ]; then
    EMAIL_DB=$(sqlite3 data/database.sqlite "SELECT value FROM settings WHERE key='letsencrypt_email';" 2>/dev/null)
    
    if [ -z "$EMAIL_DB" ]; then
        echo "❌ No email found in database settings"
        echo "Please set the email in the GUI first (Settings → SSL Configuration)"
        exit 1
    fi
    
    echo "✅ Found email in database: $EMAIL_DB"
else
    echo "❌ database.sqlite not found"
    exit 1
fi
echo ""

# Check if email is valid
if [[ "$EMAIL_DB" == *"example.com"* ]] || [[ "$EMAIL_DB" == *"example.net"* ]] || [[ "$EMAIL_DB" == *"example.org"* ]]; then
    echo "❌ ERROR: Email contains forbidden domain (example.com/net/org)"
    echo "Please update the email in GUI settings first"
    exit 1
fi

# Update docker-compose.yml
echo "2. Updating docker-compose.yml..."
CURRENT_EMAIL=$(grep "acme.email" docker-compose.yml | sed 's/.*email=//' | sed 's/"//g' | tr -d ' ')
echo "   Current: $CURRENT_EMAIL"
echo "   New:     $EMAIL_DB"

# Backup docker-compose.yml
cp docker-compose.yml docker-compose.yml.backup.$(date +%Y%m%d_%H%M%S)

# Update the email
sed -i "s|acme.email=.*\"|acme.email=$EMAIL_DB\"|" docker-compose.yml

echo "✅ docker-compose.yml updated"
echo ""

# Remove old acme.json (it has the old email cached)
echo "3. Removing old acme.json (contains old email)..."
if [ -f "ssl/acme.json" ]; then
    sudo rm ssl/acme.json
    echo "✅ Old acme.json removed"
else
    echo "⚠️  acme.json not found (will be created)"
fi
echo ""

# Create fresh acme.json
echo "4. Creating fresh acme.json..."
sudo mkdir -p ssl
sudo tee ssl/acme.json > /dev/null << 'EOF'
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
EOF
sudo chmod 600 ssl/acme.json
sudo chown root:root ssl/acme.json
echo "✅ Fresh acme.json created"
echo ""

# Stop and remove Traefik container completely
echo "5. Stopping and removing old Traefik container..."
docker-compose stop traefik
docker-compose rm -f traefik
echo "✅ Old container removed"
echo ""

# Recreate Traefik container with new configuration
echo "6. Creating new Traefik container with updated email..."
docker-compose up -d traefik
echo "✅ New Traefik container created"
echo ""

# Wait for Traefik to start
echo "6. Waiting for Traefik to initialize..."
sleep 5
echo "✅ Ready"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ FIX COMPLETE!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Email synchronized: $EMAIL_DB"
echo ""
echo "Next steps:"
echo "  1. Monitor Traefik logs: docker logs webbadeploy_traefik -f"
echo "  2. Traefik will now request certificates with the correct email"
echo "  3. Check for 'certificate obtained' messages in logs"
echo ""
echo "Verify with:"
echo "  docker inspect webbadeploy_traefik | grep acme.email"
echo ""
