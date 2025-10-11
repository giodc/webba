# Webbadeploy Security Checklist

## Overview
This document outlines security considerations and fixes for Webbadeploy deployment.

---

## 🔒 Security Issues Found & Fixed

### 1. **GitHub Token Encryption** ✅ FIXED
- **Issue**: Tokens stored in plain text
- **Fix**: AES-256-GCM encryption implemented
- **Location**: `gui/includes/encryption.php`
- **Status**: ✅ Secure

### 2. **Docker Socket Permissions**
- **Production**: `660` with docker group ✅ Secure
- **Local Dev**: `666` for convenience ⚠️ Less secure but acceptable for local
- **Fix Script**: `fix-local-docker.sh`

### 3. **File Permissions**

#### Production (Secure):
```bash
/opt/webbadeploy/data:     755 (www-data:www-data)
/opt/webbadeploy/apps:     755 (www-data:www-data)
/opt/webbadeploy/ssl:      750 (root:www-data)
docker-compose.yml:        640 (root:www-data)
/var/run/docker.sock:      660 (root:docker)
```

#### Local Development (Permissive):
```bash
/opt/webbadeploy/data:     777 (user:user)
/opt/webbadeploy/apps:     777 (user:user)
docker-compose.yml:        664 (user:user)
/var/run/docker.sock:      666 (root:docker)
```

### 4. **Docker GID Mismatch** ✅ FIXED
- **Issue**: Hardcoded DOCKER_GID=988 in docker-compose.yml
- **Problem**: Local Docker GID might be different (e.g., 999, 1000)
- **Fix**: `fix-local-docker.sh` detects and updates correct GID
- **Status**: ✅ Fixed

---

## 🐛 Common Issues & Solutions

### Issue 1: Sites Not Working Locally

**Symptoms:**
- Sites deploy but show errors
- Permission denied errors
- Can't write to directories

**Cause:**
- Docker GID mismatch
- Incorrect file permissions
- Docker socket not accessible

**Solution:**
```bash
sudo ./fix-local-docker.sh
```

This script will:
1. Detect your Docker GID
2. Update docker-compose.yml
3. Rebuild containers
4. Fix all permissions

---

### Issue 2: "Not a git repository" Error

**Symptoms:**
- GitHub "Check for Updates" fails
- Error: "Not a git repository"

**Cause:**
- .git directory not preserved during clone

**Solution:**
- Click "Pull Latest Changes" to re-clone
- Or deploy a new site (already fixed in code)

**Status:** ✅ Fixed in commit 9f8426f

---

### Issue 3: Database Permission Errors

**Symptoms:**
- Can't write to database
- SQLite errors

**Solution:**
```bash
# Inside container
docker exec -u root webbadeploy_gui chown www-data:www-data /app/data/database.sqlite
docker exec -u root webbadeploy_gui chmod 666 /app/data/database.sqlite
```

---

## 🔐 Security Best Practices

### For Production Servers:

1. **Use install-production.sh**
   ```bash
   sudo ./install-production.sh
   ```
   - Sets secure permissions (755/750)
   - Uses docker group (not world-writable)
   - Proper ownership (www-data)

2. **Firewall Configuration**
   ```bash
   ufw allow 80/tcp
   ufw allow 443/tcp
   ufw allow 9000/tcp  # Dashboard (restrict to your IP!)
   ufw enable
   ```

3. **Restrict Dashboard Access**
   - Use reverse proxy with authentication
   - Or restrict to specific IPs
   - Don't expose port 9000 publicly

4. **SSL Certificates**
   - Use Let's Encrypt for production domains
   - Keep acme.json secure (600 permissions)

5. **Database Security**
   - SQLite file: 664 permissions
   - Located in /app/data (not web-accessible)
   - Regular backups

6. **GitHub Tokens**
   - Use fine-grained tokens
   - Minimum permissions (Contents: Read)
   - Set expiration dates
   - Rotate regularly

### For Local Development:

1. **Use fix-local-docker.sh**
   ```bash
   sudo ./fix-local-docker.sh
   ```
   - More permissive settings
   - Easier development
   - Not for production!

2. **Docker Socket**
   - 666 permissions acceptable locally
   - Allows easy container management
   - Don't use in production

3. **File Permissions**
   - 777 on data/apps directories
   - Makes development easier
   - No permission issues

---

## 🛡️ Security Layers

### 1. Application Level
- ✅ Session management with secure cookies
- ✅ CSRF protection
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ XSS prevention (htmlspecialchars)
- ✅ Authentication required for all API calls
- ✅ Role-based access control (RBAC)
- ✅ 2FA support

### 2. Data Level
- ✅ Encrypted GitHub tokens (AES-256-GCM)
- ✅ Hashed passwords (bcrypt)
- ✅ Secure session storage
- ✅ Database outside web root

### 3. Container Level
- ✅ Non-root user (www-data) in containers
- ✅ Read-only mounts where possible
- ✅ Network isolation (bridge network)
- ✅ Resource limits (can be configured)

### 4. Host Level
- ✅ Docker socket with group permissions
- ✅ Proper file ownership
- ✅ Restricted directory permissions
- ✅ Firewall rules (user configured)

---

## 🔍 Security Audit Commands

### Check File Permissions:
```bash
ls -la /opt/webbadeploy/data
ls -la /opt/webbadeploy/apps
ls -la /opt/webbadeploy/docker-compose.yml
ls -la /var/run/docker.sock
```

### Check Docker GID:
```bash
getent group docker | cut -d: -f3
grep "DOCKER_GID" /opt/webbadeploy/docker-compose.yml
```

### Check Container Permissions:
```bash
docker exec webbadeploy_gui ls -la /app/data
docker exec webbadeploy_gui ls -la /app/apps
docker exec webbadeploy_gui id www-data
```

### Check Database:
```bash
docker exec webbadeploy_gui ls -la /app/data/database.sqlite
docker exec webbadeploy_gui sqlite3 /app/data/database.sqlite ".tables"
```

### Check Encryption Key:
```bash
docker exec webbadeploy_gui php -r "
require_once '/var/www/html/includes/functions.php';
\$db = initDatabase();
\$key = getSetting(\$db, 'encryption_key');
echo 'Encryption key exists: ' . (!empty(\$key) ? 'YES' : 'NO') . PHP_EOL;
"
```

---

## ⚠️ Known Limitations

### 1. Docker Socket Access
- **Risk**: Container has access to Docker socket
- **Mitigation**: Only www-data user, not root
- **Why Needed**: To manage site containers
- **Alternative**: Use Docker API with TLS (future enhancement)

### 2. Shared Database Container
- **Risk**: All sites share one MariaDB container
- **Mitigation**: Separate databases per site
- **Why**: Resource efficiency
- **Alternative**: Dedicated DB per site (configurable)

### 3. Local File Storage
- **Risk**: Files stored on host filesystem
- **Mitigation**: Proper permissions, backups
- **Why**: Simplicity, performance
- **Alternative**: Network storage (future enhancement)

---

## 🚀 Quick Fixes

### For Production Issues:
```bash
sudo ./fix-permissions-secure.sh
```

### For Local Development Issues:
```bash
sudo ./fix-local-docker.sh
```

### For Docker Socket Issues:
```bash
sudo ./fix-docker-permissions.sh
```

### For Database Issues:
```bash
docker exec -u root webbadeploy_gui chown -R www-data:www-data /app/data
docker exec -u root webbadeploy_gui chmod -R 775 /app/data
```

---

## 📋 Pre-Deployment Checklist

### Before Going to Production:

- [ ] Run `install-production.sh` (not install.sh)
- [ ] Set secure file permissions (755/750)
- [ ] Configure firewall
- [ ] Restrict dashboard access
- [ ] Set up SSL certificates
- [ ] Change default passwords
- [ ] Enable 2FA for admin accounts
- [ ] Set up regular backups
- [ ] Configure monitoring
- [ ] Review GitHub token permissions
- [ ] Test disaster recovery

### For Local Development:

- [ ] Run `fix-local-docker.sh`
- [ ] Verify Docker GID matches
- [ ] Check Docker socket permissions
- [ ] Test site deployment
- [ ] Verify database access
- [ ] Test GitHub integration

---

## 🆘 Troubleshooting

### Permission Denied Errors:
1. Check Docker GID: `getent group docker | cut -d: -f3`
2. Run: `sudo ./fix-local-docker.sh`
3. Restart containers: `docker-compose restart`

### Sites Not Starting:
1. Check logs: `docker logs <container_name>`
2. Check permissions: `docker exec <container> ls -la /var/www/html`
3. Fix permissions: `docker exec -u root <container> chown -R www-data:www-data /var/www/html`

### Database Errors:
1. Check file exists: `docker exec webbadeploy_gui ls -la /app/data/database.sqlite`
2. Fix permissions: `docker exec -u root webbadeploy_gui chmod 666 /app/data/database.sqlite`
3. Run migrations: `docker exec webbadeploy_gui php /var/www/html/migrate-*.php`

---

## 📚 Additional Resources

- **Docker Security**: https://docs.docker.com/engine/security/
- **PHP Security**: https://www.php.net/manual/en/security.php
- **Let's Encrypt**: https://letsencrypt.org/docs/
- **GitHub Tokens**: https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token

---

## 🔄 Regular Maintenance

### Weekly:
- Check for updates: `git pull origin master`
- Review access logs
- Check disk space

### Monthly:
- Rotate GitHub tokens
- Review user permissions
- Update containers: `docker-compose pull && docker-compose up -d`
- Test backups

### Quarterly:
- Security audit
- Update dependencies
- Review firewall rules
- Disaster recovery test

---

**Last Updated**: 2025-10-11
**Version**: 1.0
