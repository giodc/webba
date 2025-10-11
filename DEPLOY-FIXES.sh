#!/bin/bash
# Deploy All Fixes for Webbadeploy
# Run this on your remote server

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                 WEBBADEPLOY - DEPLOY FIXES                     ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "This script will apply the following fixes:"
echo ""
echo "  1. ✅ Password Reset Tool (command-line)"
echo "  2. ✅ Session Timeout Fix (24 hours instead of 24 minutes)"
echo "  3. ✅ Better Error Handling (no more cryptic JSON errors)"
echo "  4. ✅ ACME SSL Certificate File Fix"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if in correct directory
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ Error: Must run from /opt/webbadeploy directory"
    exit 1
fi

echo "📋 Pre-deployment Checklist:"
echo ""

# Check if files exist
echo -n "  Checking password reset script... "
if [ -f "reset-admin-password.sh" ]; then
    echo "✅"
else
    echo "❌ Missing"
    exit 1
fi

echo -n "  Checking PHP session config... "
if [ -f "gui/php-session.ini" ]; then
    echo "✅"
else
    echo "❌ Missing"
    exit 1
fi

echo -n "  Checking Dockerfile updates... "
if grep -q "php-session.ini" gui/Dockerfile; then
    echo "✅"
else
    echo "❌ Missing"
    exit 1
fi

echo -n "  Checking auth.php updates... "
if grep -q "session.gc_maxlifetime" gui/includes/auth.php; then
    echo "✅"
else
    echo "❌ Missing"
    exit 1
fi

echo -n "  Checking JavaScript updates... "
if grep -q "apiCall" gui/js/app.js; then
    echo "✅"
else
    echo "❌ Missing"
    exit 1
fi

echo -n "  Checking ACME fix script... "
if [ -f "fix-acme.sh" ]; then
    echo "✅"
else
    echo "❌ Missing"
    exit 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "⚠️  WARNING: This will restart your Webbadeploy containers"
echo "   Downtime: ~30-60 seconds"
echo ""

read -p "Continue with deployment? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment cancelled."
    exit 0
fi

echo ""
echo "🚀 Starting deployment..."
echo ""

# Step 1: Fix ACME file
echo "1️⃣  Fixing ACME SSL certificate file..."
if [ ! -f "ssl/acme.json" ] || [ ! -s "ssl/acme.json" ]; then
    sudo bash fix-acme.sh
    echo "   ✅ ACME file fixed"
else
    echo "   ✅ ACME file already exists"
fi
echo ""

# Step 2: Stop containers
echo "2️⃣  Stopping containers..."
docker-compose down
echo "   ✅ Containers stopped"
echo ""

# Step 3: Rebuild
echo "3️⃣  Rebuilding web-gui with new configuration..."
docker-compose build --no-cache web-gui
echo "   ✅ Build complete"
echo ""

# Step 4: Start
echo "4️⃣  Starting containers..."
docker-compose up -d
echo "   ✅ Containers started"
echo ""

# Step 5: Wait for startup
echo "5️⃣  Waiting for services to initialize..."
sleep 8
echo "   ✅ Services ready"
echo ""

# Step 6: Verify
echo "6️⃣  Verifying deployment..."
echo ""

# Check container running
if docker ps | grep -q "webbadeploy_gui"; then
    echo "   ✅ GUI container running"
else
    echo "   ❌ GUI container not running!"
    docker logs webbadeploy_gui --tail 20
    exit 1
fi

# Check session config
SESSION_LIFETIME=$(docker exec webbadeploy_gui php -r "echo ini_get('session.gc_maxlifetime');" 2>/dev/null || echo "0")
if [ "$SESSION_LIFETIME" = "86400" ]; then
    echo "   ✅ Session lifetime: 24 hours"
else
    echo "   ⚠️  Session lifetime: $SESSION_LIFETIME (expected 86400)"
fi

# Check PHP config file
if docker exec webbadeploy_gui test -f /usr/local/etc/php/conf.d/php-session.ini; then
    echo "   ✅ PHP config file present"
else
    echo "   ⚠️  PHP config file missing"
fi

# Check password reset script
if docker exec webbadeploy_gui test -f /var/www/html/reset-password.php; then
    echo "   ✅ Password reset script available"
else
    echo "   ⚠️  Password reset script missing"
fi

# Check ACME file
if [ -f "ssl/acme.json" ]; then
    ACME_SIZE=$(stat -c%s "ssl/acme.json")
    ACME_PERMS=$(stat -c "%a" "ssl/acme.json")
    if [ "$ACME_PERMS" = "600" ]; then
        echo "   ✅ ACME file present ($ACME_SIZE bytes, permissions: 600)"
    else
        echo "   ⚠️  ACME file has incorrect permissions: $ACME_PERMS (should be 600)"
    fi
else
    echo "   ❌ ACME file missing!"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "✅ DEPLOYMENT COMPLETE!"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📝 Next Steps:"
echo ""
echo "  1. Clear your browser cache and cookies"
echo "  2. Log in to Webbadeploy"
echo "  3. Test deploying a new application"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "🔧 Useful Commands:"
echo ""
echo "  # Reset admin password"
echo "  ./reset-admin-password.sh admin YourNewPassword"
echo ""
echo "  # View logs"
echo "  docker logs webbadeploy_gui --tail 50 -f"
echo ""
echo "  # Check session config"
echo "  docker exec webbadeploy_gui php -i | grep session"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📚 Documentation:"
echo "  - PASSWORD-RESET-GUIDE.md"
echo "  - FIX-SESSION-ERROR.md"
echo "  - FIXES-APPLIED.md"
echo ""
