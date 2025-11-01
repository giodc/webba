#\!/bin/bash
# This script is called by the WordPress entrypoint after setting up files
# Start supervisor to manage nginx and php-fpm
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
