# Remote Deployment & Update Guide

## Overview

This guide explains how to deploy fixes and updates to WharfTales installations on multiple remote servers automatically.

---

## Architecture

```
[Your Dev Machine]
       |
       | git push
       v
   [GitHub Repo]
       |
       | git pull (on each server)
       v
[Remote Server 1] [Remote Server 2] [Remote Server 3] ...
```

---

## How Updates Work

### 1. **Code Updates** (via Git)
- All code changes are committed to the GitHub repository
- Remote servers pull the latest code using `git pull`
- Update scripts preserve user configurations (email, domains, etc.)

### 2. **Database Migrations** (Automatic)
- Migration scripts are stored in `/opt/wharftales/gui/migrations/`
- Migrations run automatically during updates
- Each migration is idempotent (safe to run multiple times)
- Migrations check if changes are already applied

### 3. **Configuration Preservation** (Automatic)
- `docker-compose.yml` is backed up before updates
- `database.sqlite` is backed up before updates
- `acme.json` (SSL certificates) is backed up before updates
- All configs are restored after `git pull`

---

## Current Migrations

All migrations are automatically applied during updates:

| Migration | Purpose | Location |
|-----------|---------|----------|
| `migrate-rbac-2fa.php` | Add RBAC and 2FA support | `/var/www/html/` |
| `migrate-php-version.php` | Add PHP version selection | `/var/www/html/` |
| `add_github_fields.php` | Add GitHub deployment fields | `/var/www/html/migrations/` |
| `fix-site-permissions-database.php` | Fix permissions database bug | `/var/www/html/migrations/` |
| `migrate-compose-to-db.php` | Move configs to database | `/var/www/html/` |

---

## Deploying Fixes to Remote Servers

### Method 1: Using safe-update.sh (Recommended)

**On each remote server:**

```bash
cd /opt/wharftales
sudo ./safe-update.sh
```

**What it does:**
1. ✅ Backs up all configurations
2. ✅ Pulls latest code from GitHub
3. ✅ Restores configurations
4. ✅ Rebuilds containers
5. ✅ **Runs all migrations automatically**
6. ✅ Fixes permissions
7. ✅ Verifies everything works

### Method 2: Using install.sh

```bash
cd /opt/wharftales
sudo ./install.sh
```

Detects existing installation and runs in UPDATE_MODE.

### Method 3: Using update.sh

```bash
cd /opt/wharftales
sudo ./update.sh
```

Simpler version with same backup/restore logic.

---

## Deploying to Multiple Servers

### Option A: Manual SSH (Small Scale)

For 2-5 servers, SSH into each and run the update:

```bash
# Server 1
ssh user@server1.example.com
cd /opt/wharftales && sudo ./safe-update.sh
exit

# Server 2
ssh user@server2.example.com
cd /opt/wharftales && sudo ./safe-update.sh
exit

# etc...
```

### Option B: Ansible Playbook (Recommended for 5+ servers)

Create `/opt/wharftales/ansible/update-all-servers.yml`:

```yaml
---
- name: Update WharfTales on all servers
  hosts: wharftales_servers
  become: yes
  tasks:
    - name: Pull latest code
      git:
        repo: 'https://github.com/giodc/wharftales.git'
        dest: /opt/wharftales
        version: master
        force: yes
      
    - name: Run safe update script
      command: /opt/wharftales/safe-update.sh
      args:
        chdir: /opt/wharftales
      register: update_result
      
    - name: Show update results
      debug:
        var: update_result.stdout_lines
```

**Inventory file** (`/opt/wharftales/ansible/hosts.ini`):

```ini
[wharftales_servers]
server1.example.com ansible_user=root
server2.example.com ansible_user=root
server3.example.com ansible_user=root
```

**Run the playbook:**

```bash
ansible-playbook -i ansible/hosts.ini ansible/update-all-servers.yml
```

### Option C: Bash Script for Multiple Servers

Create `/opt/wharftales/scripts/update-all-remote.sh`:

```bash
#!/bin/bash

# List of remote servers
SERVERS=(
    "user@server1.example.com"
    "user@server2.example.com"
    "user@server3.example.com"
)

echo "Updating WharfTales on ${#SERVERS[@]} servers..."

for server in "${SERVERS[@]}"; do
    echo ""
    echo "=========================================="
    echo "Updating: $server"
    echo "=========================================="
    
    ssh "$server" << 'ENDSSH'
        cd /opt/wharftales
        sudo ./safe-update.sh
ENDSSH
    
    if [ $? -eq 0 ]; then
        echo "✓ $server updated successfully"
    else
        echo "✗ $server update failed"
    fi
done

echo ""
echo "Update complete for all servers!"
```

**Usage:**

```bash
chmod +x /opt/wharftales/scripts/update-all-remote.sh
./scripts/update-all-remote.sh
```

---

## Creating New Migrations

When you need to deploy a new fix to all servers:

### 1. Create Migration Script

Create `/opt/wharftales/gui/migrations/your-fix-name.php`:

```php
#!/usr/bin/env php
<?php
/**
 * Migration: Your Fix Description
 * 
 * What this migration does...
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== Your Fix Migration ===\n\n";

try {
    $db = initDatabase();
    
    // Check if migration already applied
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='your_table'");
    if ($stmt->fetch()) {
        echo "✓ Migration already applied\n";
        exit(0);
    }
    
    // Apply your changes
    $db->exec("ALTER TABLE sites ADD COLUMN new_field TEXT");
    
    echo "✓ Migration completed successfully\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
```

### 2. Make it Executable

```bash
chmod +x /opt/wharftales/gui/migrations/your-fix-name.php
```

### 3. Add to Update Scripts

Edit `/opt/wharftales/install.sh` and `/opt/wharftales/safe-update.sh`:

```bash
# Add this line with other migrations:
docker exec wharftales_gui php /var/www/html/migrations/your-fix-name.php 2>/dev/null || echo "Migration already applied"
```

### 4. Commit and Push

```bash
git add gui/migrations/your-fix-name.php
git add install.sh safe-update.sh
git commit -m "Add migration for your fix"
git push origin master
```

### 5. Deploy to Remote Servers

Use one of the methods above to update all servers.

---

## Testing Migrations

### Test Locally First

```bash
# Test the migration directly
docker exec wharftales_gui php /var/www/html/migrations/your-fix-name.php

# Test full update process
cd /opt/wharftales
sudo ./safe-update.sh
```

### Test on Staging Server

Before deploying to production:

1. Set up a staging server with production data
2. Run the update script
3. Verify everything works
4. Then deploy to production servers

---

## Rollback Strategy

If an update fails:

### 1. Restore from Backup

Each update creates a backup in `/opt/wharftales/data/backups/update-YYYYMMDD-HHMMSS/`

```bash
# Find latest backup
ls -lt /opt/wharftales/data/backups/

# Restore files
BACKUP_DIR="/opt/wharftales/data/backups/update-20241028-233045"
cd /opt/wharftales

sudo cp $BACKUP_DIR/docker-compose.yml .
sudo cp $BACKUP_DIR/database.sqlite data/
sudo cp $BACKUP_DIR/acme.json ssl/
sudo chmod 600 ssl/acme.json

# Restart services
sudo docker-compose restart
```

### 2. Revert Git Changes

```bash
cd /opt/wharftales
git log --oneline -5  # Find commit to revert to
git reset --hard <commit-hash>
sudo docker-compose restart
```

---

## Monitoring Updates

### Check Update Status

```bash
# Check if services are running
docker ps | grep wharftales

# Check recent logs
docker logs wharftales_gui --tail 50
docker logs wharftales_traefik --tail 50

# Check database migrations
docker exec wharftales_gui ls -la /var/www/html/migrations/
```

### Verify Migrations Applied

```bash
# Check database structure
docker exec wharftales_gui sqlite3 /app/data/database.sqlite ".schema sites"

# Check specific table
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT * FROM site_permissions LIMIT 5"
```

---

## Best Practices

### 1. **Always Test First**
- Test migrations on a staging server
- Test with production-like data
- Verify rollback works

### 2. **Backup Before Updates**
- Update scripts do this automatically
- Keep backups for at least 7 days
- Test restore process regularly

### 3. **Update During Low Traffic**
- Schedule updates during maintenance windows
- Notify users in advance
- Have rollback plan ready

### 4. **Monitor After Updates**
- Check logs for errors
- Verify user access works
- Test critical features

### 5. **Document Changes**
- Keep changelog updated
- Document breaking changes
- Update this guide as needed

---

## Current Fix Deployment

### Site Permissions Database Fix

**What was fixed:**
- Regular users getting "Access denied" errors
- Database mismatch bug in permission system

**Files changed:**
- `/opt/wharftales/gui/includes/auth.php` (code fix)
- `/opt/wharftales/gui/migrations/fix-site-permissions-database.php` (migration)
- `/opt/wharftales/install.sh` (runs migration)
- `/opt/wharftales/safe-update.sh` (runs migration)

**To deploy to remote servers:**

```bash
# On your dev machine
git add .
git commit -m "Fix site permissions database mismatch bug"
git push origin master

# On each remote server (or use automation)
cd /opt/wharftales
sudo ./safe-update.sh
```

**The migration will:**
1. Check if site_permissions table exists in main database
2. Migrate any orphaned permissions from auth database
3. Fix column names (permission → permission_level)
4. Verify all permissions are working

**Safe to run multiple times:** Yes, the migration checks if changes are already applied.

---

## Troubleshooting

### Migration Fails

```bash
# Check error details
docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php

# Check database
docker exec wharftales_gui sqlite3 /app/data/database.sqlite ".tables"
```

### Permissions Still Not Working

```bash
# Verify migration ran
docker logs wharftales_gui | grep "Site Permissions"

# Check database structure
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "PRAGMA table_info(site_permissions)"

# Manually run migration
docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php
```

### Update Script Hangs

```bash
# Check container status
docker ps -a | grep wharftales

# Check logs
docker logs wharftales_gui --tail 100

# Restart containers
cd /opt/wharftales
sudo docker-compose restart
```

---

## Summary

**For deploying fixes to remote servers:**

1. ✅ Create migration script in `gui/migrations/`
2. ✅ Add migration to update scripts
3. ✅ Test locally
4. ✅ Commit and push to GitHub
5. ✅ Run `safe-update.sh` on each remote server
6. ✅ Migrations apply automatically
7. ✅ Configurations are preserved
8. ✅ Backups are created automatically

**The system is designed for:**
- Zero-downtime updates
- Automatic migrations
- Configuration preservation
- Easy rollback
- Multi-server deployment
