# WharfTales Update Guide

## Problem: Settings Reset During Updates

**Issue:** When running the install script to update WharfTales, configurations like the Let's Encrypt email are being reset to default values.

**Root Cause:** 
- `docker-compose.yml` is in `.gitignore` (correct behavior)
- However, `git pull` can sometimes affect tracked files
- The old update process didn't backup and restore user configurations
- This made every update painful as you had to reconfigure settings

---

## Solution: Use the New Safe Update Scripts

We've created improved update scripts that **automatically backup and restore** your configurations.

### Option 1: Safe Update Script (Recommended)

This is the most comprehensive update method with detailed output:

```bash
cd /opt/wharftales
sudo ./safe-update.sh
```

**What it does:**
1. ✅ Backs up `docker-compose.yml` (Let's Encrypt email, domains)
2. ✅ Backs up `database.sqlite` (all sites, users, settings)
3. ✅ Backs up `acme.json` (SSL certificates)
4. ✅ Pulls latest code from GitHub
5. ✅ **Restores all backups** to preserve your settings
6. ✅ Rebuilds containers
7. ✅ Runs database migrations
8. ✅ Fixes permissions
9. ✅ Verifies configuration

**Backup location:** `/opt/wharftales/data/backups/update-YYYYMMDD-HHMMSS/`

---

### Option 2: Simple Update Script

A simpler version with the same backup/restore logic:

```bash
cd /opt/wharftales
sudo ./update.sh
```

**What it does:**
1. ✅ Backs up configurations
2. ✅ Pulls latest code
3. ✅ **Restores configurations**
4. ✅ Rebuilds and restarts containers

---

### Option 3: Install Script (Updated)

The original install script has been improved to preserve settings during updates:

```bash
cd /opt/wharftales
sudo ./install.sh
```

When it detects an existing installation, it now:
1. ✅ Backs up all configurations
2. ✅ Pulls updates
3. ✅ **Restores configurations automatically**
4. ✅ Runs migrations

---

## What Gets Backed Up

Every update now backs up:

| File | Contains | Why Important |
|------|----------|---------------|
| `docker-compose.yml` | Let's Encrypt email, Traefik config, dashboard domain | Your SSL email and routing configuration |
| `database.sqlite` | Sites, users, permissions, settings | All your data |
| `acme.json` | SSL certificates | Your issued certificates (avoid rate limits) |

---

## Backup Locations

Backups are stored in:
```
/opt/wharftales/data/backups/update-YYYYMMDD-HHMMSS/
```

Example:
```
/opt/wharftales/data/backups/update-20241028-233045/
├── docker-compose.yml
├── database.sqlite
└── acme.json
```

---

## Manual Restore (If Needed)

If something goes wrong, you can manually restore from the latest backup:

```bash
# Find latest backup
ls -lt /opt/wharftales/data/backups/

# Restore docker-compose.yml
cd /opt/wharftales
sudo cp data/backups/update-YYYYMMDD-HHMMSS/docker-compose.yml .

# Restore database
sudo cp data/backups/update-YYYYMMDD-HHMMSS/database.sqlite data/

# Restore SSL certificates
sudo cp data/backups/update-YYYYMMDD-HHMMSS/acme.json ssl/
sudo chmod 600 ssl/acme.json
sudo chown root:root ssl/acme.json

# Restart services
sudo docker-compose restart
```

---

## Verifying After Update

After updating, verify your settings are preserved:

### 1. Check Let's Encrypt Email

```bash
grep "acme.email" /opt/wharftales/docker-compose.yml
```

Should show your real email, not `test@example.com` or `admin@example.com`.

### 2. Check SSL Debug Page

Visit: `http://your-server-ip:9000/ssl-debug.php`

- ✅ Let's Encrypt Email should show "Valid"
- ✅ ACME Storage should show certificate data (if you had certificates)

### 3. Check Settings Page

Visit: `http://your-server-ip:9000/settings.php`

- ✅ Let's Encrypt Email should show your real email
- ✅ Custom domains should be preserved

---

## Database-Based Configuration

WharfTales now uses **database-based configuration storage** for better reliability:

### How It Works

1. **First Time:** When you update settings in the GUI, they're saved to both:
   - Database (`compose_configs` table)
   - File (`docker-compose.yml`)

2. **Updates:** The update scripts preserve the file, so your settings remain intact

3. **Sync:** The database and file stay in sync automatically

### Benefits

- ✅ Settings survive updates
- ✅ No permission issues
- ✅ Version control friendly
- ✅ Easy to backup/restore
- ✅ Audit trail (who changed what, when)

---

## Migration Notes

### If You've Already Updated and Lost Settings

1. Check if you have a backup:
   ```bash
   ls -lt /opt/wharftales/data/backups/
   ```

2. If yes, restore from backup (see "Manual Restore" above)

3. If no backup exists, you'll need to reconfigure:
   - Go to Settings → SSL Configuration
   - Update Let's Encrypt email
   - Restart Traefik: `cd /opt/wharftales && sudo docker-compose restart traefik`

### Database Migration

The `migrate-compose-to-db.php` script runs automatically during updates:
- Only runs once (skips if already migrated)
- Imports existing `docker-compose.yml` to database
- Preserves all settings

---

## Best Practices

### Before Updating

1. **Check current email:**
   ```bash
   grep "acme.email" /opt/wharftales/docker-compose.yml
   ```

2. **Note your settings:**
   - Let's Encrypt email
   - Custom domains
   - Any manual changes to docker-compose.yml

### During Update

1. **Use the safe update script:**
   ```bash
   sudo ./safe-update.sh
   ```

2. **Watch for errors** in the output

3. **Note the backup location** shown in the output

### After Update

1. **Verify email is preserved:**
   ```bash
   grep "acme.email" /opt/wharftales/docker-compose.yml
   ```

2. **Check SSL Debug page** for any issues

3. **Test your sites** to ensure they're accessible

4. **Keep the backup** for at least a week

---

## Troubleshooting

### Email Was Reset to Default

**Symptom:** After update, email shows `test@example.com` or `admin@example.com`

**Solution:**
1. Check if backup exists:
   ```bash
   ls -lt /opt/wharftales/data/backups/
   ```

2. Restore from backup:
   ```bash
   LATEST_BACKUP=$(ls -t /opt/wharftales/data/backups/ | head -1)
   sudo cp /opt/wharftales/data/backups/$LATEST_BACKUP/docker-compose.yml /opt/wharftales/
   sudo docker-compose restart traefik
   ```

3. If no backup, update manually in Settings page

### Certificates Lost After Update

**Symptom:** All sites show "SSL: Pending" after update

**Solution:**
1. Restore acme.json from backup:
   ```bash
   LATEST_BACKUP=$(ls -t /opt/wharftales/data/backups/ | head -1)
   sudo cp /opt/wharftales/data/backups/$LATEST_BACKUP/acme.json /opt/wharftales/ssl/
   sudo chmod 600 /opt/wharftales/ssl/acme.json
   sudo chown root:root /opt/wharftales/ssl/acme.json
   sudo docker-compose restart traefik
   ```

### Database Errors After Update

**Symptom:** GUI shows errors about missing tables or columns

**Solution:**
1. Restore database from backup:
   ```bash
   LATEST_BACKUP=$(ls -t /opt/wharftales/data/backups/ | head -1)
   sudo cp /opt/wharftales/data/backups/$LATEST_BACKUP/database.sqlite /opt/wharftales/data/
   sudo docker exec -u root wharftales_gui chown www-data:www-data /app/data/database.sqlite
   sudo docker-compose restart web-gui
   ```

2. Run migrations manually:
   ```bash
   sudo docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php
   sudo docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php
   ```

---

## Files Modified

The following update scripts have been improved:

1. **`/opt/wharftales/safe-update.sh`** (NEW)
   - Comprehensive update with detailed output
   - Automatic backup and restore
   - Configuration verification
   - Recommended for production use

2. **`/opt/wharftales/update.sh`** (IMPROVED)
   - Simple update script
   - Now includes backup/restore logic
   - Good for quick updates

3. **`/opt/wharftales/install.sh`** (IMPROVED)
   - Now preserves settings during updates
   - Automatic backup when UPDATE_MODE detected
   - Restores configurations after git pull

---

## Summary

**Before:** Updates reset your Let's Encrypt email and other settings, making every update painful.

**After:** All update scripts now automatically:
1. Backup your configurations
2. Pull latest code
3. Restore your configurations
4. Preserve SSL certificates

**Result:** Updates are now painless - your settings are preserved automatically! 🎉
