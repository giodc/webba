#!/bin/bash
# Monitor for attack patterns in Traefik logs

echo "Monitoring Traefik for attack patterns..."
echo "Press Ctrl+C to stop"
echo ""

# Watch for suspicious requests
docker logs webbadeploy_traefik -f 2>&1 | grep --line-buffered -E '\.env|\.git|config\.json|telescope|info\.php|wp-admin|phpmyadmin|_all_dbs' | while read line; do
    # Extract IP and path
    IP=$(echo "$line" | grep -oP '"ClientAddr":"\K[^:]+')
    PATH=$(echo "$line" | grep -oP '"RequestPath":"\K[^"]+')
    STATUS=$(echo "$line" | grep -oP '"DownstreamStatus":\K\d+')
    
    echo "🚨 ATTACK DETECTED"
    echo "   IP: $IP"
    echo "   Path: $PATH"
    echo "   Status: $STATUS"
    echo "   Time: $(date)"
    echo ""
    
    # Optional: Auto-block after 5 attempts
    # Uncomment to enable:
    # COUNT=$(grep -c "$IP" /tmp/attack_ips.log 2>/dev/null || echo 0)
    # if [ $COUNT -gt 5 ]; then
    #     echo "🔒 Auto-blocking $IP (>5 attempts)"
    #     sudo ufw deny from $IP
    # fi
    # echo "$IP" >> /tmp/attack_ips.log
done
