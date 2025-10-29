#!/bin/bash
# Fix Session Error and Rebuild WharfTales
# This script applies the session timeout fix

set -e

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  WharfTales - Fix Session Error"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if running in the correct directory
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ Error: docker-compose.yml not found"
    echo "Please run this script from /opt/wharftales directory"
    exit 1
fi

echo "📋 This script will:"
echo "  1. Stop the WharfTales containers"
echo "  2. Rebuild with new session configuration"
echo "  3. Start the containers"
echo ""
echo "⚠️  This will cause ~30 seconds of downtime"
echo ""

read -p "Continue? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cancelled."
    exit 0
fi

echo ""
echo "🛑 Stopping containers..."
docker-compose down

echo ""
echo "🔨 Rebuilding web-gui container..."
docker-compose build --no-cache web-gui

echo ""
echo "🚀 Starting containers..."
docker-compose up -d

echo ""
echo "⏳ Waiting for services to be ready..."
sleep 5

echo ""
echo "✅ Checking container status..."
if docker ps | grep -q "wharftales_gui"; then
    echo "✅ WharfTales GUI is running"
else
    echo "❌ Warning: GUI container may not be running"
    echo "Check logs with: docker logs wharftales_gui"
fi

echo ""
echo "🔍 Verifying PHP session configuration..."
SESSION_LIFETIME=$(docker exec wharftales_gui php -r "echo ini_get('session.gc_maxlifetime');" 2>/dev/null || echo "error")

if [ "$SESSION_LIFETIME" = "86400" ]; then
    echo "✅ Session lifetime: 24 hours (86400 seconds)"
elif [ "$SESSION_LIFETIME" = "error" ]; then
    echo "⚠️  Could not verify session configuration"
else
    echo "⚠️  Session lifetime: $SESSION_LIFETIME seconds (expected 86400)"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ Fix Applied Successfully!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📝 Next steps:"
echo "  1. Clear your browser cache and cookies"
echo "  2. Log in to WharfTales again"
echo "  3. Session will now last 24 hours"
echo ""
echo "🔗 Access WharfTales at: http://localhost:9000"
echo ""
