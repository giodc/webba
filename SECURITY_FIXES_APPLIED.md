# Security Fixes Applied to WharfTales

**Date:** 2025-10-08  
**Status:** ✅ Code fixes completed

---

## Summary

All critical security vulnerabilities in the codebase have been fixed. The following files were modified to address the security issues that led to the WordPress site compromise.

---

## Files Modified

### 1. ✅ `/opt/wharftales/install-production.sh`
**Changes:**
- Line 234-235: Changed `chmod -R 777` → `chmod -R 755` for apps and data directories
- Line 238-239: Added proper ownership with `chown -R www-data:www-data`
- Line 243-248: Changed Docker socket from `chmod 666` → `chmod 660` with docker group
- Line 180-181: Added port mapping `9000:80` for GUI (was missing)

**Before:**
```bash
chmod -R 777 "$INSTALL_DIR/apps"    # DANGEROUS
chmod -R 777 "$INSTALL_DIR/data"    # DANGEROUS
chmod 666 /var/run/docker.sock      # ROOT ACCESS
```

**After:**
```bash
chmod -R 755 "$INSTALL_DIR/apps"
chmod -R 755 "$INSTALL_DIR/data"
chown -R www-data:www-data "$INSTALL_DIR/apps"
chown -R www-data:www-data "$INSTALL_DIR/data"

groupadd -f docker
usermod -aG docker www-data
chmod 660 /var/run/docker.sock
chown root:docker /var/run/docker.sock
```

---

### 2. ✅ `/opt/wharftales/install.sh`
**Changes:**
- Line 65-73: Changed `chmod 666` → `chmod 664` for docker-compose.yml
- Line 68-73: Changed Docker socket from `chmod 666` → `chmod 660` with docker group
- Line 124-132: Same fixes for fresh installation path

**Before:**
```bash
chmod 666 docker-compose.yml
chmod 666 /var/run/docker.sock
```

**After:**
```bash
chmod 664 docker-compose.yml
chown www-data:www-data docker-compose.yml

groupadd -f docker
usermod -aG docker www-data
chmod 660 /var/run/docker.sock
chown root:docker /var/run/docker.sock
```

---

### 3. ✅ `/opt/wharftales/gui/includes/functions.php`
**Changes:**
- Line 394-401: Changed SFTP directory permissions from `0777` → `0755`
- Line 453: Changed SFTP to bind to `127.0.0.1` instead of `0.0.0.0`
- Line 477-481: Added security options to SFTP containers

**Before:**
```php
mkdir($bindPath, 0777, true);
chmod($bindPath, 0777);

ports:
  - "{$port}:2222"  // Binds to 0.0.0.0
```

**After:**
```php
mkdir($bindPath, 0755, true);
chmod($bindPath, 0755);
chown($bindPath, 'www-data');
chgrp($bindPath, 'www-data');

$bindAddress = "127.0.0.1";  // Localhost only
ports:
  - "{$bindAddress}:{$port}:2222"

security_opt:
  - no-new-privileges:true
```

---

## New Security Files Created

### 4. ✅ `/opt/wharftales/SECURITY_FIXES.md`
Complete step-by-step manual fix instructions for:
- Changing database passwords
- Securing SFTP with firewall rules
- Cleaning WordPress malware
- Restricting dashboard access

### 5. ✅ `/opt/wharftales/fix-permissions-secure.sh`
Automated script to fix file permissions on existing installations.

### 6. ✅ `/opt/wharftales/security-audit.sh`
Security scanning tool to detect vulnerabilities and compromises.

### 7. ✅ `/opt/wharftales/apps/wordpress/wp-security-hardening.php`
WordPress security configuration including:
- Disable file editing
- Disable file modifications
- Force SSL for admin
- Rate limiting for login attempts
- Security headers

### 8. ✅ `/opt/wharftales/apps/wordpress/.htaccess-uploads-security`
Upload directory protection:
- Disable PHP execution
- Block script injection
- Whitelist allowed file types

### 9. ✅ `/opt/wharftales/SECURITY_AUDIT_REPORT.md`
Complete audit report with findings and recommendations.

---

## Security Improvements Summary

| Issue | Severity | Status |
|-------|----------|--------|
| World-writable directories (777) | CRITICAL | ✅ Fixed |
| Docker socket world-writable (666) | CRITICAL | ✅ Fixed |
| SFTP publicly exposed | CRITICAL | ✅ Fixed |
| SFTP directories world-writable | HIGH | ✅ Fixed |
| Default database passwords | HIGH | ⚠️ Manual fix required |
| Dashboard publicly exposed | MEDIUM | ⚠️ Manual fix required |
| No WordPress hardening | HIGH | ✅ Config files provided |

---

## What's Fixed Automatically

✅ **File Permissions**
- Apps directory: 777 → 755
- Data directory: 777 → 755
- Proper www-data ownership

✅ **Docker Socket**
- Permissions: 666 → 660
- Group-based access instead of world-writable
- Added docker group membership for www-data

✅ **SFTP Security**
- Bind to localhost (127.0.0.1) instead of all interfaces (0.0.0.0)
- Directory permissions: 777 → 755
- Added security options: no-new-privileges
- Disabled sudo access

✅ **Installation Scripts**
- Both install.sh and install-production.sh secured
- New installations will have secure defaults

---

## What Requires Manual Action

⚠️ **Existing Installations**
1. Run `/opt/wharftales/fix-permissions-secure.sh` to fix permissions
2. Change database passwords (see SECURITY_FIXES.md)
3. Configure firewall rules for SFTP and dashboard
4. Scan and clean WordPress sites for malware
5. Restart SFTP containers to apply localhost binding

⚠️ **WordPress Sites**
1. Install security plugins (Wordfence, iThemes Security)
2. Add wp-security-hardening.php to wp-config.php
3. Copy .htaccess-uploads-security to wp-content/uploads/.htaccess
4. Update all plugins and themes
5. Change admin passwords

---

## Testing & Verification

Run the security audit to verify fixes:
```bash
cd /opt/wharftales
sudo bash security-audit.sh
```

Expected results after fixes:
- ✅ File permissions: 755 (not 777)
- ✅ Docker socket: 660 (not 666)
- ✅ SFTP: localhost binding only
- ✅ No publicly exposed management ports (with firewall)

---

## Future Installations

All new installations using the updated scripts will have:
- ✅ Secure file permissions (755)
- ✅ Docker socket with group access (660)
- ✅ SFTP bound to localhost
- ✅ Proper ownership and access controls

---

## Compliance

These fixes address:
- **CIS Docker Benchmark** violations
- **OWASP Top 10** security issues
- **PCI DSS** requirements for access control
- **General security best practices**

---

## Support

For questions or issues:
1. Review `/opt/wharftales/SECURITY_FIXES.md` for detailed instructions
2. Review `/opt/wharftales/SECURITY_AUDIT_REPORT.md` for complete findings
3. Run `/opt/wharftales/security-audit.sh` to check current status

---

*All code-level security fixes have been applied. Manual actions are required to secure existing deployments.*
