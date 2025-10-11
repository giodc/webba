#!/bin/bash
# Add rate limiting to Traefik to prevent abuse

echo "Adding rate limiting to Traefik configuration..."

cd /opt/webbadeploy || exit 1

# Backup current docker-compose.yml
cp docker-compose.yml docker-compose.yml.backup.$(date +%Y%m%d_%H%M%S)

# Check if rate limiting already exists
if grep -q "ratelimit" docker-compose.yml; then
    echo "Rate limiting already configured!"
    exit 0
fi

# Add rate limiting middleware configuration
cat >> docker-compose.yml << 'EOF'

# Rate limiting configuration (added for security)
# Limits: 100 requests per second, burst of 50
# Prevents automated scanning and DDoS attacks
EOF

# Add to Traefik command section
sed -i '/--entrypoints.metrics.address=:9090/a\      - "--middlewares.ratelimit.ratelimit.average=100"\n      - "--middlewares.ratelimit.ratelimit.burst=50"' docker-compose.yml

echo "✅ Rate limiting configuration added"
echo ""
echo "Restart Traefik to apply:"
echo "  docker-compose up -d --force-recreate traefik"
echo ""
echo "This will limit requests to 100/second with burst of 50"
echo "Helps prevent automated scanning and DDoS attacks"
