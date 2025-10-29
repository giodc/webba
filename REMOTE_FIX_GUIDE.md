# Remote Server Fix Guide

## Overview

This guide explains how to automatically fix all your remote WharfTales installations after the rename from Webbadeploy to WharfTales.

---

## What Gets Fixed

The fix script automatically:

1. ✅ Stops all containers
2. ✅ Removes old `webbadeploy_*` containers
3. ✅ Removes old networks (`webbadeploy`, `wharftales_webbadeploy`)
4. ✅ Pulls latest code from GitHub
5. ✅ Restarts Docker daemon
6. ✅ Starts new `wharftales_*` containers
7. ✅ Creates `settings` and `compose_configs` tables
8. ✅ Fixes `/app/data` and `/app/apps` permissions
9. ✅ Runs all database migrations
10. ✅ Restarts GUI container

---

## Method 1: Single Server (Manual)

### Step 1: Commit and Push Changes

On your local machine:

```bash
cd /opt/wharftales
git add .
git commit -m "Add remote fix scripts and all rename fixes"
git push origin master
```

### Step 2: SSH to Remote Server

```bash
ssh root@your-server.com
```

### Step 3: Run Fix Script

```bash
cd /opt/wharftales
git pull origin master
chmod +x fix-remote-installation.sh
./fix-remote-installation.sh
```

### Step 4: Verify

```bash
# Check containers
docker ps

# Should see:
# wharftales_traefik
# wharftales_gui
# wharftales_db

# Access dashboard
# http://your-server:9000
```

---

## Method 2: Multiple Servers (Automated)

### Step 1: Create servers.txt

On your local machine:

```bash
cd /opt/wharftales

# Create servers list
cat > servers.txt << 'EOF'
# WharfTales Remote Servers
# One server per line, lines starting with # are ignored

root@server1.example.com
root@server2.example.com
root@server3.example.com
EOF
```

### Step 2: Commit and Push

```bash
git add .
git commit -m "Add remote fix scripts"
git push origin master
```

### Step 3: Run Deployment Script

```bash
chmod +x deploy-fixes-to-remotes.sh
./deploy-fixes-to-remotes.sh
```

The script will:
- Read servers from `servers.txt`
- SSH to each server
- Pull latest code
- Run the fix script
- Show success/failure for each server

---

## Method 3: Ansible (For Many Servers)

### Create Playbook

```yaml
# fix-wharftales-remotes.yml
---
- name: Fix WharfTales installations
  hosts: wharftales_servers
  become: yes
  tasks:
    - name: Pull latest code
      git:
        repo: 'https://github.com/giodc/wharftales.git'
        dest: /opt/wharftales
        version: master
        force: yes
    
    - name: Make fix script executable
      file:
        path: /opt/wharftales/fix-remote-installation.sh
        mode: '0755'
    
    - name: Run fix script
      command: /opt/wharftales/fix-remote-installation.sh
      args:
        chdir: /opt/wharftales
      register: fix_result
    
    - name: Show results
      debug:
        var: fix_result.stdout_lines
```

### Run Playbook

```bash
ansible-playbook -i hosts.ini fix-wharftales-remotes.yml
```

---

## What the Fix Script Does

### 1. Container Cleanup

```bash
# Stops all containers
docker stop $(docker ps -aq)

# Removes old containers
docker rm webbadeploy_traefik webbadeploy_gui webbadeploy_db
```

### 2. Network Cleanup

```bash
# Removes old networks
docker network rm webbadeploy wharftales_webbadeploy

# Prunes unused networks
docker network prune -f
```

### 3. Code Update

```bash
cd /opt/wharftales
git pull origin master
```

### 4. Database Initialization

```bash
# Creates settings table
CREATE TABLE IF NOT EXISTS settings (...)

# Creates compose_configs table
CREATE TABLE IF NOT EXISTS compose_configs (...)
```

### 5. Permission Fixes

```bash
# Fix data directory
chown -R www-data:www-data /app/data
chmod -R 775 /app/data

# Fix apps directory
chown -R www-data:www-data /app/apps
chmod -R 775 /app/apps
find /app/apps -type d -exec chmod 775 {} \;
find /app/apps -type f -exec chmod 664 {} \;
```

### 6. Migrations

```bash
# Runs all migrations
php migrate-rbac-2fa.php
php migrate-php-version.php
php migrations/add_github_fields.php
php migrations/fix-site-permissions-database.php
php migrate-compose-to-db.php
```

---

## Verification

After running the fix script, verify:

### 1. Containers Running

```bash
docker ps --format "table {{.Names}}\t{{.Status}}"
```

Should show:
```
NAMES                STATUS
wharftales_traefik   Up X minutes
wharftales_gui       Up X minutes
wharftales_db        Up X minutes
```

### 2. Networks

```bash
docker network ls | grep wharftales
```

Should show:
```
wharftales_wharftales
```

### 3. Database Tables

```bash
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;"
```

Should include:
```
compose_configs
settings
sites
users
...
```

### 4. Dashboard Access

Visit: `http://your-server:9000`

Should load without errors.

### 5. Create Test Site

Try creating a new site - should work without permission errors.

---

## Troubleshooting

### Port 80 Already in Use

```bash
# Find what's using port 80
sudo netstat -tulpn | grep :80

# Kill it
sudo kill -9 <PID>

# Or stop Apache/Nginx
sudo systemctl stop apache2 nginx

# Restart WharfTales
docker-compose restart
```

### Permission Denied Errors

```bash
# Re-run permission fixes
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/data /app/apps
```

### Database Errors

```bash
# Re-create tables
docker exec wharftales_gui sqlite3 /app/data/database.sqlite < /path/to/schema.sql

# Or run fix script again
./fix-remote-installation.sh
```

### Network Conflicts

```bash
# Remove all networks and start fresh
docker stop $(docker ps -q)
docker network prune -f
docker-compose up -d
```

---

## Rollback (If Needed)

If something goes wrong:

```bash
# Find latest backup
ls -lt /opt/wharftales/data/backups/

# Restore
BACKUP="/opt/wharftales/data/backups/update-YYYYMMDD-HHMMSS"
cp $BACKUP/docker-compose.yml /opt/wharftales/
cp $BACKUP/database.sqlite /opt/wharftales/data/
docker-compose restart
```

---

## Summary

**Quick Fix for One Server:**
```bash
ssh root@server.com
cd /opt/wharftales && git pull && chmod +x fix-remote-installation.sh && ./fix-remote-installation.sh
```

**Quick Fix for Multiple Servers:**
```bash
# On local machine
./deploy-fixes-to-remotes.sh
```

That's it! All your remote servers will be fixed automatically. 🚀
