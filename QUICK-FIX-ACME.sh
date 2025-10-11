#!/bin/bash
# Quick ACME Fix for Remote Server
# Copy and paste this entire script into your remote server terminal

echo "🔧 Quick ACME Fix"
echo "=================="
echo ""

# Navigate to webbadeploy directory
cd /opt/webbadeploy || exit 1

# Create ssl directory if missing
sudo mkdir -p ssl

# Create acme.json with proper structure
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

# Set correct permissions
sudo chmod 600 ssl/acme.json
sudo chown root:root ssl/acme.json

# Verify
echo ""
echo "✅ ACME file created:"
ls -la ssl/acme.json

# Restart Traefik
echo ""
echo "🔄 Restarting Traefik..."
docker-compose restart traefik

echo ""
echo "✅ Done! Monitor logs with:"
echo "   docker logs webbadeploy_traefik -f"
