#!/bin/bash

# Fix docker-compose.yml mount path from webbadeploy to wharftales

echo "=========================================="
echo "Fixing docker-compose.yml mount path"
echo "=========================================="
echo ""

cd /opt/wharftales

# Check if old path exists in docker-compose.yml
if grep -q "/opt/webbadeploy/docker-compose.yml" docker-compose.yml; then
    echo "✓ Found old path, updating..."
    
    # Update the path
    sed -i 's|/opt/webbadeploy/docker-compose.yml|/opt/wharftales/docker-compose.yml|g' docker-compose.yml
    
    echo "✓ Updated docker-compose.yml mount path"
    
    # Restart containers to apply the change
    echo ""
    echo "Restarting containers..."
    docker-compose down
    docker-compose up -d
    
    echo ""
    echo "Waiting for containers to start..."
    sleep 5
    
    # Import docker-compose.yml into database
    echo ""
    echo "Importing docker-compose.yml into database..."
    ./fix-compose-config.sh
    
    echo ""
    echo "=========================================="
    echo "✅ Fix complete!"
    echo "=========================================="
    echo ""
    echo "You can now save settings in the dashboard."
else
    echo "✓ Path is already correct"
    
    # Still import into database just in case
    echo ""
    echo "Importing docker-compose.yml into database..."
    ./fix-compose-config.sh
fi
