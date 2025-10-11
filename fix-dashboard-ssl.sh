#!/bin/bash
# Fix dashboard SSL configuration on remote server

set -e

cd /opt/webbadeploy || exit 1

echo "Fixing dashboard SSL configuration..."
echo ""

# Backup current docker-compose.yml
cp docker-compose.yml docker-compose.yml.backup.$(date +%Y%m%d_%H%M%S)
echo "✅ Backup created"

# Update domain and add SSL labels
sed -i 's/Host(`demo\.test\.local`)/Host(`dashboard.development.giodc.com`)/' docker-compose.yml

# Check if SSL labels already exist
if grep -q "webgui-secure" docker-compose.yml; then
    echo "✅ SSL labels already exist"
else
    echo "Adding SSL labels..."
    
    # Find the line with webgui.loadbalancer and add SSL labels after it
    sed -i '/traefik.http.services.webgui.loadbalancer.server.port=80/a\      - traefik.http.routers.webgui-secure.rule=Host(`dashboard.development.giodc.com`)\n      - traefik.http.routers.webgui-secure.entrypoints=websecure\n      - traefik.http.routers.webgui-secure.tls=true\n      - traefik.http.routers.webgui-secure.tls.certresolver=letsencrypt\n      - traefik.http.middlewares.webgui-redirect.redirectscheme.scheme=https\n      - traefik.http.middlewares.webgui-redirect.redirectscheme.permanent=true\n      - traefik.http.routers.webgui.middlewares=webgui-redirect' docker-compose.yml
    
    echo "✅ SSL labels added"
fi

echo ""
echo "Restarting web-gui container..."
docker-compose up -d web-gui

echo ""
echo "✅ Configuration updated!"
echo ""
echo "Monitor certificate acquisition:"
echo "  docker logs webbadeploy_traefik -f"
echo ""
echo "Dashboard will be available at:"
echo "  https://dashboard.development.giodc.com"
echo ""
echo "Certificate should be issued within 1-2 minutes"
