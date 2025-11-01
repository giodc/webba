#\!/bin/bash
set -e

# Run the original WordPress entrypoint to set up WordPress files
if [ -f /usr/local/bin/docker-entrypoint.sh ]; then
    /usr/local/bin/docker-entrypoint.sh php-fpm &
    ENTRYPOINT_PID=$\!
    
    # Wait for WordPress files to be copied
    sleep 5
    
    # Kill the background php-fpm (we'll start it via supervisor)
    kill $ENTRYPOINT_PID 2>/dev/null || true
fi

# Start supervisor to manage nginx and php-fpm
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
