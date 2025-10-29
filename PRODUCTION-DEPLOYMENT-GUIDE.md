# Production Deployment Guide

## Overview

This guide helps you prepare your WharfTales installation for production deployment with proper security hardening.

---

## 🚀 Quick Start

### Step 1: Run Production Readiness Check

```bash
# Dry-run mode (check only, no changes)
sudo bash /opt/wharftales/production-readiness-check.sh --dry-run

# Apply fixes automatically
sudo bash /opt/wharftales/production-readiness-check.sh
```

### Step 2: Review Results

The script will check 8 critical security areas:
1. **System Security** - Firewall, updates, fail2ban
2. **File Permissions** - Proper ownership and permissions
3. **Docker Security** - Socket permissions, GID configuration
4. **Database Security** - Default passwords, file permissions
5. **Application Security** - Encryption, 2FA, sessions
6. **SSL/TLS** - Certificates and configuration
7. **Exposed Services** - Public ports and services
8. **Malware Checks** - WordPress site scanning

### Step 3: Fix Critical Issues

The script will:
- ✅ **Auto-fix** many issues (when run without `--dry-run`)
- ⚠️ **Flag** issues that need manual intervention
- 📋 **Provide** specific recommendations

---

## 🔒 Pre-Production Checklist

### Essential Security Steps

- [ ] **Run production readiness check**
  ```bash
  sudo bash production-readiness-check.sh
  ```

- [ ] **Change default database password**
  - Edit `docker-compose.yml`
  - Change `MYSQL_ROOT_PASSWORD` and `MYSQL_PASSWORD`
  - Restart containers: `docker-compose restart db`

- [ ] **Enable firewall**
  ```bash
  sudo ufw allow 22/tcp   # SSH
  sudo ufw allow 80/tcp   # HTTP
  sudo ufw allow 443/tcp  # HTTPS
  sudo ufw enable
  ```

- [ ] **Restrict dashboard access (port 9000)**
  ```bash
  # Option 1: Restrict to specific IP
  sudo ufw allow from YOUR_IP to any port 9000
  
  # Option 2: Use SSH tunnel
  ssh -L 9000:localhost:9000 user@your-server
  # Then access: http://localhost:9000
  ```

- [ ] **Configure SSL certificates**
  - Use Let's Encrypt for production domains
  - Update Traefik configuration in `docker-compose.yml`
  - Set proper email for certificate notifications

- [ ] **Enable 2FA for admin accounts**
  - Login to dashboard
  - Go to Users → Edit admin user
  - Enable Two-Factor Authentication
  - Scan QR code with authenticator app

- [ ] **Set strong passwords**
  - All user accounts should have strong passwords
  - Use password manager
  - Minimum 12 characters, mixed case, numbers, symbols

- [ ] **Configure automatic backups**
  ```bash
  # Add to crontab
  0 2 * * * /opt/wharftales/scripts/backup.sh
  ```

- [ ] **Set up monitoring**
  - Configure log rotation
  - Set up alerts for critical issues
  - Monitor disk space and memory

- [ ] **Review GitHub token permissions**
  - Use fine-grained tokens
  - Minimum required permissions (Contents: Read)
  - Set expiration dates
  - Rotate regularly

---

## 🛡️ Security Hardening Steps

### 1. System Level

```bash
# Install security updates automatically
sudo apt install unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades

# Install fail2ban for SSH protection
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# Disable root SSH login
sudo sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sudo systemctl restart sshd
```

### 2. Docker Security

```bash
# Fix Docker socket permissions (production)
sudo chmod 660 /var/run/docker.sock

# Verify Docker GID matches
DOCKER_GID=$(getent group docker | cut -d: -f3)
echo "Docker GID: $DOCKER_GID"

# Update docker-compose.yml if needed
sudo sed -i "s/DOCKER_GID: .*/DOCKER_GID: $DOCKER_GID/" docker-compose.yml
```

### 3. File Permissions

```bash
# Set production permissions
sudo chown -R www-data:www-data /opt/wharftales/data
sudo chown -R www-data:www-data /opt/wharftales/apps
sudo chown -R root:www-data /opt/wharftales/ssl

sudo chmod 755 /opt/wharftales/data
sudo chmod 755 /opt/wharftales/apps
sudo chmod 750 /opt/wharftales/ssl
sudo chmod 640 /opt/wharftales/docker-compose.yml

# Database file
sudo chmod 664 /opt/wharftales/data/database.sqlite
sudo chown www-data:www-data /opt/wharftales/data/database.sqlite
```

### 4. SSL/TLS Configuration

```bash
# Ensure acme.json has correct permissions
sudo chmod 600 /opt/wharftales/ssl/acme.json

# Test SSL configuration
curl -I https://your-domain.com
```

### 5. Application Security

```bash
# Verify encryption key exists
docker exec wharftales_gui php -r "
require_once '/var/www/html/includes/functions.php';
\$db = initDatabase();
\$key = getSetting(\$db, 'encryption_key');
echo 'Encryption key: ' . (!empty(\$key) ? 'EXISTS' : 'MISSING') . PHP_EOL;
"

# Check session configuration
docker exec wharftales_gui cat /usr/local/etc/php/conf.d/php-session.ini
```

---

## 🔍 Verification Commands

### Check Firewall Status
```bash
sudo ufw status verbose
```

### Check Running Containers
```bash
docker ps
docker-compose ps
```

### Check Container Logs
```bash
docker logs wharftales_gui --tail=50
docker logs wharftales_traefik --tail=50
docker logs wharftales_db --tail=50
```

### Check File Permissions
```bash
ls -la /opt/wharftales/data
ls -la /opt/wharftales/apps
ls -la /opt/wharftales/ssl
ls -la /var/run/docker.sock
```

### Check Database
```bash
docker exec wharftales_gui sqlite3 /app/data/database.sqlite ".tables"
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT username, role, two_factor_enabled FROM users;"
```

### Check SSL Certificates
```bash
ls -la /opt/wharftales/ssl/
docker exec wharftales_traefik cat /letsencrypt/acme.json | jq '.Certificates[].domain'
```

---

## 🚨 Common Issues & Solutions

### Issue: Port 9000 publicly accessible

**Solution:**
```bash
# Restrict to specific IP
sudo ufw delete allow 9000
sudo ufw allow from YOUR_IP to any port 9000

# Or use SSH tunnel
ssh -L 9000:localhost:9000 user@server
```

### Issue: Default database password

**Solution:**
```bash
# 1. Stop containers
docker-compose down

# 2. Edit docker-compose.yml
nano docker-compose.yml
# Change MYSQL_ROOT_PASSWORD and MYSQL_PASSWORD

# 3. Remove old database volume
docker volume rm wharftales_db_data

# 4. Start containers
docker-compose up -d
```

### Issue: Docker GID mismatch

**Solution:**
```bash
# Get correct GID
DOCKER_GID=$(getent group docker | cut -d: -f3)

# Update docker-compose.yml
sed -i "s/DOCKER_GID: .*/DOCKER_GID: $DOCKER_GID/" docker-compose.yml

# Rebuild containers
docker-compose up -d --build web-gui
```

### Issue: Permission denied errors

**Solution:**
```bash
# Run production permissions script
sudo bash /opt/wharftales/fix-permissions-secure.sh

# Or manually fix
sudo chown -R www-data:www-data /opt/wharftales/data
sudo chown -R www-data:www-data /opt/wharftales/apps
sudo chmod 755 /opt/wharftales/data
sudo chmod 755 /opt/wharftales/apps
```

---

## 📊 Security Audit Schedule

### Daily
- Check container status: `docker ps`
- Review access logs
- Monitor disk space: `df -h`

### Weekly
- Run security audit: `sudo bash security-audit.sh`
- Check for updates: `git pull origin master`
- Review user activity logs

### Monthly
- Run production readiness check: `sudo bash production-readiness-check.sh`
- Rotate GitHub tokens
- Update all containers: `docker-compose pull && docker-compose up -d`
- Test database backups
- Review firewall rules: `sudo ufw status`

### Quarterly
- Full security audit
- Update system packages: `sudo apt update && sudo apt upgrade`
- Review and update SSL certificates
- Disaster recovery test
- Review user permissions and roles

---

## 🔐 Best Practices

### Passwords
- Minimum 12 characters
- Use password manager
- Enable 2FA for all admin accounts
- Rotate passwords every 90 days

### Access Control
- Principle of least privilege
- Regular user access reviews
- Disable unused accounts
- Use SSH keys instead of passwords

### Monitoring
- Set up log aggregation
- Configure alerts for:
  - Failed login attempts
  - High resource usage
  - Container crashes
  - SSL certificate expiration

### Backups
- Automated daily backups
- Test restore procedures monthly
- Store backups off-site
- Encrypt backup files

### Updates
- Enable automatic security updates
- Test updates in staging first
- Keep Docker images updated
- Monitor security advisories

---

## 📞 Emergency Procedures

### Container Not Starting
```bash
# Check logs
docker logs wharftales_gui

# Restart container
docker-compose restart web-gui

# Rebuild if needed
docker-compose up -d --build web-gui
```

### Database Corruption
```bash
# Restore from backup
docker-compose down
cp /opt/wharftales/backups/database.sqlite.YYYYMMDD /opt/wharftales/data/database.sqlite
docker-compose up -d
```

### Locked Out of Dashboard
```bash
# Reset admin password
sudo bash /opt/wharftales/reset-admin-password.sh
```

### SSL Certificate Issues
```bash
# Remove old certificates
sudo rm /opt/wharftales/ssl/acme.json

# Restart Traefik to get new certificates
docker-compose restart traefik

# Check logs
docker logs wharftales_traefik
```

---

## 📚 Additional Resources

- **Security Checklist**: `SECURITY-CHECKLIST.md`
- **Security Audit Script**: `security-audit.sh`
- **Installation Guide**: `README.md`
- **Troubleshooting**: `GITHUB-TROUBLESHOOTING.md`

---

## ✅ Production Deployment Workflow

1. **Prepare**
   ```bash
   # Run readiness check
   sudo bash production-readiness-check.sh --dry-run
   ```

2. **Fix Issues**
   ```bash
   # Apply automatic fixes
   sudo bash production-readiness-check.sh
   
   # Fix remaining issues manually
   ```

3. **Verify**
   ```bash
   # Run check again
   sudo bash production-readiness-check.sh --dry-run
   
   # Should show: "✓ PRODUCTION READY!"
   ```

4. **Deploy**
   ```bash
   # Start services
   docker-compose up -d
   
   # Verify all containers running
   docker ps
   ```

5. **Monitor**
   ```bash
   # Check logs
   docker-compose logs -f
   
   # Test dashboard access
   curl -I http://localhost:9000
   ```

6. **Secure**
   ```bash
   # Enable firewall
   sudo ufw enable
   
   # Restrict dashboard access
   sudo ufw allow from YOUR_IP to any port 9000
   ```

---

**Last Updated**: 2025-10-11  
**Version**: 1.0  
**Status**: Production Ready
