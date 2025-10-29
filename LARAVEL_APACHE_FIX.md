# Laravel Apache DocumentRoot Fix

## Problem

Laravel sites were showing "403 Forbidden" because Apache's DocumentRoot was pointing to `/var/www/html` instead of `/var/www/html/public`.

Laravel requires the DocumentRoot to be the `public` directory.

---

## Solution Implemented

### For NEW Laravel Sites

**File:** `/opt/wharftales/gui/api.php` (Lines 814-830)

New Laravel sites now automatically:
1. ✅ Set DocumentRoot to `/var/www/html/public`
2. ✅ Enable `mod_rewrite` for routing
3. ✅ Install PDO MySQL extensions
4. ✅ Configure proper directory permissions

**Docker Compose Generated:**
```yaml
services:
  laravel_site:
    image: php:8.3-apache
    command: >
      bash -c "
      echo '<VirtualHost *:80>
        DocumentRoot /var/www/html/public
        <Directory /var/www/html/public>
          AllowOverride All
          Require all granted
        </Directory>
      </VirtualHost>' > /etc/apache2/sites-available/000-default.conf &&
      a2enmod rewrite &&
      docker-php-ext-install pdo_mysql mysqli &&
      apache2-foreground
      "
```

---

## For EXISTING Laravel Sites

### Quick Fix Script

```bash
cd /opt/wharftales
chmod +x fix-existing-laravel-sites.sh
./fix-existing-laravel-sites.sh
```

This script automatically:
- Finds all Laravel containers
- Sets DocumentRoot to `/public`
- Enables mod_rewrite
- Fixes storage permissions
- Restarts Apache

### Manual Fix (Single Site)

```bash
CONTAINER="laravel_yoursite_123"

# 1. Set DocumentRoot
docker exec $CONTAINER bash -c "cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF"

# 2. Enable mod_rewrite
docker exec $CONTAINER a2enmod rewrite

# 3. Fix storage permissions
docker exec $CONTAINER chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec $CONTAINER chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 4. Restart Apache
docker exec $CONTAINER apache2ctl restart
```

---

## Verification

### Check DocumentRoot

```bash
docker exec laravel_yoursite_123 cat /etc/apache2/sites-enabled/000-default.conf | grep DocumentRoot
```

Should show:
```
DocumentRoot /var/www/html/public
```

### Check mod_rewrite

```bash
docker exec laravel_yoursite_123 apache2ctl -M | grep rewrite
```

Should show:
```
rewrite_module (shared)
```

### Check Permissions

```bash
docker exec laravel_yoursite_123 ls -la /var/www/html/storage
```

Should show:
```
drwxrwxr-x www-data www-data storage
```

### Test Site

```bash
curl -I http://your-laravel-site.com
```

Should return `200 OK` instead of `403 Forbidden`.

---

## What Gets Fixed

| Issue | Before | After |
|-------|--------|-------|
| DocumentRoot | `/var/www/html` ❌ | `/var/www/html/public` ✅ |
| mod_rewrite | Disabled ❌ | Enabled ✅ |
| Storage permissions | 755 ❌ | 775 ✅ |
| .htaccess | Ignored ❌ | Processed ✅ |
| Laravel routing | Broken ❌ | Working ✅ |

---

## Common Laravel Errors Fixed

### 403 Forbidden
**Cause:** DocumentRoot not pointing to `public`
**Fixed:** ✅ DocumentRoot now `/var/www/html/public`

### 404 Not Found (all routes)
**Cause:** mod_rewrite disabled or AllowOverride not set
**Fixed:** ✅ mod_rewrite enabled, AllowOverride All

### 500 Internal Server Error
**Cause:** Storage directory not writable
**Fixed:** ✅ Storage permissions set to 775

### Routes not working
**Cause:** .htaccess not being processed
**Fixed:** ✅ AllowOverride All in Directory config

---

## Summary

**New Laravel sites:** ✅ Work perfectly from creation
**Existing Laravel sites:** ✅ Fixed with one script

All Laravel sites now have:
- ✅ Correct DocumentRoot (`/public`)
- ✅ mod_rewrite enabled
- ✅ Proper permissions
- ✅ Working routing

**No more 403 Forbidden errors!** 🎉
