# Install Script - All Fixes Integrated

## Overview

All fixes from the Webbadeploy → WharfTales rename have been integrated into the installation scripts. Fresh installations now work perfectly out of the box.

---

## Fixes Integrated in install.sh

### 1. ✅ Proper Directory Permissions (Lines 209-221)

**Problem:** Apps directory not writable by www-data
**Fix:**
```bash
# Set permissions before setting wharftales ownership
chown -R www-data:www-data /opt/wharftales/data
chmod -R 775 /opt/wharftales/data
chown -R www-data:www-data /opt/wharftales/apps
chmod -R 775 /opt/wharftales/apps

# Set wharftales ownership
chown -R wharftales:wharftales /opt/wharftales

# Re-apply www-data ownership (critical!)
chown -R www-data:www-data /opt/wharftales/data
chown -R www-data:www-data /opt/wharftales/apps
```

### 2. ✅ Container Permissions (Lines 298-306)

**Problem:** mkdir() permission denied when creating sites
**Fix:**
```bash
# Fix data directory
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data
docker exec -u root wharftales_gui chmod -R 775 /app/data

# Fix apps directory and all subdirectories
docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/apps
docker exec -u root wharftales_gui bash -c "find /app/apps -type d -exec chmod 775 {} \;"
docker exec -u root wharftales_gui bash -c "find /app/apps -type f -exec chmod 664 {} \;"
```

### 3. ✅ Database Tables Created (Lines 319-339)

**Problem:** Settings and compose_configs tables missing
**Fix:**
```sql
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS compose_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_type TEXT NOT NULL,
    site_id INTEGER,
    compose_yaml TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER
);
```

### 4. ✅ All Migrations Run (Lines 308-313)

**Problem:** Database schema incomplete
**Fix:**
```bash
docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php
docker exec wharftales_gui php /var/www/html/migrate-php-version.php
docker exec wharftales_gui php /var/www/html/migrations/add_github_fields.php
docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php
```

### 5. ✅ Compose Config Import (Line 317)

**Problem:** Traefik configuration not in database
**Fix:**
```bash
docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php
```

---

## Fixes Integrated in safe-update.sh

### 1. ✅ Backup Preservation (Lines 60-90)

Backs up before update:
- docker-compose.yml (email, domains)
- database.sqlite (all data)
- acme.json (SSL certificates)

### 2. ✅ Permission Fixes (Lines 168-182)

Same as install.sh, ensures permissions after update

### 3. ✅ Database Tables (Lines 152-172)

Creates settings and compose_configs tables if missing

### 4. ✅ All Migrations (Lines 143-150)

Runs all migrations automatically

---

## Fixes Integrated in update.sh

### 1. ✅ Backup/Restore (Lines 27-53)

Backs up and restores:
- docker-compose.yml
- database.sqlite
- acme.json

### 2. ✅ Database Tables (Lines 70-91)

Creates tables if missing

### 3. ✅ Permissions (Lines 96-103)

Fixes all permissions after update

---

## Fixes in GUI Code

### 1. ✅ Auto-Import Compose Config

**File:** `/opt/wharftales/gui/includes/functions.php` (Lines 645-661)

**Problem:** "Compose configuration not found" error
**Fix:** Automatically imports docker-compose.yml on first settings save

```php
if (!$config) {
    // Create initial config from docker-compose.yml
    $composeFile = '/opt/wharftales/docker-compose.yml';
    if (file_exists($composeFile)) {
        $yaml = file_get_contents($composeFile);
        saveComposeConfig($pdo, $yaml, $userId, $siteId);
        $config = getComposeConfig($pdo, $siteId);
    }
}
```

---

## What Works Now

### ✅ Fresh Installation

```bash
curl -fsSL https://raw.githubusercontent.com/giodc/wharftales/master/install.sh | sudo bash
```

Automatically:
1. Creates all directories with correct permissions
2. Starts containers with wharftales_* names
3. Creates database tables
4. Runs all migrations
5. Sets up permissions
6. Ready to use immediately

### ✅ Updates

```bash
cd /opt/wharftales
sudo ./safe-update.sh
```

Automatically:
1. Backs up configurations
2. Pulls latest code
3. Preserves settings
4. Creates missing tables
5. Fixes permissions
6. Runs migrations
7. Restores configurations

### ✅ Settings Page

- Can save Let's Encrypt email
- Can save dashboard domain
- Auto-imports docker-compose.yml on first save
- No "configuration not found" errors

### ✅ Creating Sites

- No permission denied errors
- Docker compose files save correctly
- Sites start properly
- All site types work (WordPress, Laravel, PHP)

---

## Verification Checklist

After fresh install or update:

```bash
# 1. Check containers
docker ps
# Should show: wharftales_traefik, wharftales_gui, wharftales_db

# 2. Check permissions
docker exec wharftales_gui ls -la /app/data /app/apps
# Should show: www-data www-data

# 3. Check database tables
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT name FROM sqlite_master WHERE type='table';"
# Should include: settings, compose_configs, sites, users

# 4. Access dashboard
# http://your-server:9000
# Should load without errors

# 5. Create test site
# Should work without permission errors
```

---

## Summary

All fixes are now integrated into the installation process:

| Issue | Fixed In | Status |
|-------|----------|--------|
| Container names | docker-compose.yml.template | ✅ |
| Network name | docker-compose.yml.template | ✅ |
| Directory permissions | install.sh, safe-update.sh, update.sh | ✅ |
| Apps permissions | install.sh, safe-update.sh, update.sh | ✅ |
| Settings table | install.sh, safe-update.sh, update.sh | ✅ |
| Compose_configs table | install.sh, safe-update.sh, update.sh | ✅ |
| Auto-import config | functions.php | ✅ |
| All migrations | install.sh, safe-update.sh, update.sh | ✅ |

**Result:** Fresh installations and updates work perfectly! 🎉
