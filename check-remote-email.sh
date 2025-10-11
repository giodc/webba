#!/bin/bash
# Check ACME email configuration on remote server
# Run this on the REMOTE server to diagnose the issue

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║           ACME Email Configuration Checker                ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

cd /opt/webbadeploy || exit 1

# Check docker-compose.yml
echo "1. Email in docker-compose.yml (Traefik reads this):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ -f "docker-compose.yml" ]; then
    EMAIL_COMPOSE=$(grep "acme.email" docker-compose.yml | sed 's/.*email=//' | sed 's/"//g' | tr -d ' ')
    echo "$EMAIL_COMPOSE"
    
    if [[ "$EMAIL_COMPOSE" == *"example.com"* ]] || [[ "$EMAIL_COMPOSE" == *"example.net"* ]] || [[ "$EMAIL_COMPOSE" == *"example.org"* ]]; then
        echo "❌ PROBLEM: This email is BLOCKED by Let's Encrypt!"
    fi
else
    echo "❌ docker-compose.yml not found"
fi
echo ""

# Check database settings
echo "2. Email in database (from GUI settings):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ -f "data/database.sqlite" ]; then
    EMAIL_DB=$(sqlite3 data/database.sqlite "SELECT value FROM settings WHERE key='letsencrypt_email';" 2>/dev/null || echo "Not found")
    echo "$EMAIL_DB"
else
    echo "❌ database.sqlite not found"
fi
echo ""

# Check running Traefik container
echo "3. Email in RUNNING Traefik container:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if docker ps | grep -q traefik; then
    docker inspect webbadeploy_traefik 2>/dev/null | grep "acme.email" | sed 's/.*email=//' | sed 's/",*//' | sed 's/^[[:space:]]*//'
else
    echo "❌ Traefik container not running"
fi
echo ""

# Check acme.json
echo "4. Email registered with Let's Encrypt (in acme.json):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ -f "ssl/acme.json" ]; then
    if command -v jq &> /dev/null; then
        ACME_EMAIL=$(sudo cat ssl/acme.json | jq -r '.letsencrypt.Account.Email // "Not registered yet"')
    else
        ACME_EMAIL=$(sudo cat ssl/acme.json | grep -o '"Email":"[^"]*"' | cut -d'"' -f4)
        [ -z "$ACME_EMAIL" ] && ACME_EMAIL="Not registered yet"
    fi
    echo "$ACME_EMAIL"
else
    echo "❌ acme.json not found"
fi
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "DIAGNOSIS:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [[ "$EMAIL_COMPOSE" == *"example.com"* ]]; then
    echo "❌ ISSUE FOUND: docker-compose.yml has 'example.com' email"
    echo ""
    echo "SOLUTION: The GUI settings don't update docker-compose.yml automatically."
    echo "You need to manually update docker-compose.yml with the correct email."
    echo ""
    echo "Current email in docker-compose.yml: $EMAIL_COMPOSE"
    [ "$EMAIL_DB" != "Not found" ] && echo "Email in database (GUI):           $EMAIL_DB"
    echo ""
    echo "Run this to fix:"
    echo "  sed -i 's|acme.email=.*\"|acme.email=$EMAIL_DB\"|' docker-compose.yml"
    echo "  docker-compose up -d --force-recreate traefik"
    echo "  sudo rm ssl/acme.json"
    echo "  sudo bash fix-acme.sh"
    echo "  docker-compose restart traefik"
else
    echo "✅ Email in docker-compose.yml looks valid"
fi
echo ""
