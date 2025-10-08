# Security Fixes for Webbadeploy

## IMMEDIATE ACTIONS (Do these NOW)

### 1. Fix File Permissions (CRITICAL)
```bash
# Fix directory permissions
cd /opt/webbadeploy
chmod -R 755 /opt/webbadeploy/apps
chmod -R 755 /opt/webbadeploy/data

# Fix Docker socket (more secure approach)
# Option A: Use Docker group instead of 666
sudo groupadd -f docker
sudo usermod -aG docker www-data
sudo chmod 660 /var/run/docker.sock
sudo chown root:docker /var/run/docker.sock

# Restart the GUI container
docker restart webbadeploy_gui
```

### 2. Secure SFTP Access (CRITICAL)
```bash
# Disable SFTP for all sites immediately
# Then re-enable with proper security settings

# Add firewall rules to restrict SFTP access
sudo ufw deny 2222:2299/tcp
# Only allow from your IP (replace X.X.X.X with your IP)
sudo ufw allow from X.X.X.X to any port 2222:2299 proto tcp
sudo ufw reload
```

### 3. Change Database Passwords (HIGH PRIORITY)
```bash
# Generate strong passwords
NEW_ROOT_PASS=$(openssl rand -base64 32)
NEW_USER_PASS=$(openssl rand -base64 32)

# Update database passwords
docker exec webbadeploy_db mariadb -uroot -pwebbadeploy_root_pass -e "ALTER USER 'root'@'%' IDENTIFIED BY '$NEW_ROOT_PASS'; ALTER USER 'webbadeploy'@'%' IDENTIFIED BY '$NEW_USER_PASS'; FLUSH PRIVILEGES;"

# Update docker-compose.yml with new passwords
# IMPORTANT: Save these passwords securely!
echo "Root Password: $NEW_ROOT_PASS" > /opt/webbadeploy/.db_credentials
echo "User Password: $NEW_USER_PASS" >> /opt/webbadeploy/.db_credentials
chmod 600 /opt/webbadeploy/.db_credentials
```

### 4. Restrict Dashboard Access (MEDIUM PRIORITY)
```bash
# Option A: Use firewall to restrict access
sudo ufw deny 9000/tcp
sudo ufw allow from X.X.X.X to any port 9000 proto tcp  # Replace X.X.X.X with your IP
sudo ufw reload

# Option B: Use SSH tunnel instead
# Remove the port mapping from docker-compose.yml and access via:
# ssh -L 9000:localhost:9000 user@server
```

### 5. Clean Compromised WordPress Site
```bash
# Backup first
docker exec wordpress_wordpress_1759785396 tar czf /tmp/wp-backup.tar.gz /var/www/html
docker cp wordpress_wordpress_1759785396:/tmp/wp-backup.tar.gz ./wp-backup-$(date +%Y%m%d).tar.gz

# Scan for malware
docker exec wordpress_wordpress_1759785396 find /var/www/html -name "*.php" -type f -mtime -7 -ls

# Look for suspicious files
docker exec wordpress_wordpress_1759785396 find /var/www/html -name "google*.html" -o -name "*.suspected" -o -name "*.bak.php"

# Remove google verification files (if not yours)
docker exec wordpress_wordpress_1759785396 find /var/www/html -name "google*.html" -delete

# Check for modified core files
docker exec wordpress_wordpress_1759785396 wp core verify-checksums --path=/var/www/html --allow-root

# Update WordPress and plugins
docker exec wordpress_wordpress_1759785396 wp core update --path=/var/www/html --allow-root
docker exec wordpress_wordpress_1759785396 wp plugin update --all --path=/var/www/html --allow-root
docker exec wordpress_wordpress_1759785396 wp theme update --all --path=/var/www/html --allow-root
```

### 6. Install WordPress Security Plugins
```bash
# Install Wordfence Security
docker exec wordpress_wordpress_1759785396 wp plugin install wordfence --activate --path=/var/www/html --allow-root

# Install iThemes Security
docker exec wordpress_wordpress_1759785396 wp plugin install better-wp-security --activate --path=/var/www/html --allow-root

# Change WordPress admin password
docker exec wordpress_wordpress_1759785396 wp user update admin --user_pass="$(openssl rand -base64 24)" --path=/var/www/html --allow-root
```

## LONG-TERM FIXES (Implement ASAP)

### 7. Harden WordPress Deployment
Create `/opt/webbadeploy/apps/wordpress/.htaccess-security`:
```apache
# Disable PHP execution in uploads
<FilesMatch "\\.php$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

### 8. Enable WordPress File Integrity Monitoring
Add to WordPress wp-config.php:
```php
define('DISALLOW_FILE_EDIT', true);  // Disable theme/plugin editor
define('DISALLOW_FILE_MODS', true);  // Disable plugin/theme installation from dashboard
define('FORCE_SSL_ADMIN', true);     // Force SSL for admin
```

### 9. Implement Rate Limiting
Add to Traefik configuration:
```yaml
- "--entrypoints.web.http.ratelimit.average=100"
- "--entrypoints.web.http.ratelimit.burst=50"
```

### 10. Enable Audit Logging
```bash
# Enable Docker container logging
docker update --log-driver=json-file --log-opt max-size=10m --log-opt max-file=3 wordpress_wordpress_1759785396

# Monitor for suspicious activity
docker logs -f wordpress_wordpress_1759785396 | grep -E "POST|eval|base64"
```

## MONITORING & DETECTION

### Check for Active Compromise
```bash
# Check for suspicious processes
docker exec wordpress_wordpress_1759785396 ps aux

# Check for suspicious network connections
docker exec wordpress_wordpress_1759785396 netstat -tupln

# Check recent file modifications
docker exec wordpress_wordpress_1759785396 find /var/www/html -type f -mtime -1 -ls

# Check for backdoors
docker exec wordpress_wordpress_1759785396 grep -r "eval(" /var/www/html --include="*.php"
docker exec wordpress_wordpress_1759785396 grep -r "base64_decode" /var/www/html --include="*.php"
docker exec wordpress_wordpress_1759785396 grep -r "system(" /var/www/html --include="*.php"
docker exec wordpress_wordpress_1759785396 grep -r "exec(" /var/www/html --include="*.php"
```

### Set Up Alerts
```bash
# Install fail2ban for SSH/SFTP protection
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

## PREVENTION CHECKLIST

- [ ] Fixed file permissions (755 instead of 777)
- [ ] Secured Docker socket (660 instead of 666)
- [ ] Restricted SFTP access with firewall rules
- [ ] Changed all default database passwords
- [ ] Restricted dashboard access (port 9000)
- [ ] Cleaned compromised WordPress files
- [ ] Installed WordPress security plugins
- [ ] Disabled PHP execution in uploads directory
- [ ] Enabled WordPress file integrity protection
- [ ] Implemented rate limiting
- [ ] Set up monitoring and alerts
- [ ] Enabled fail2ban for brute force protection
- [ ] Regular security audits scheduled

## NOTES

1. **Backup everything before making changes**
2. **Test in staging environment if possible**
3. **Document all password changes**
4. **Monitor logs for 48 hours after fixes**
5. **Consider migrating to a clean server if heavily compromised**
