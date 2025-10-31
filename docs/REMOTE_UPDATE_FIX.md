# Fixing Port Conflict on Remote Server

## Problem
When updating via remote install, you get:
```
Error response from daemon: failed to bind host port for 0.0.0.0:80: address already in use
```

## Root Cause
The old `upgrade.sh` script uses `docker-compose up -d --force-recreate` which tries to bind ports while old containers still hold them.

## Quick Fix (On Remote Server)

### Method 1: Apply the Fix Script

```bash
# SSH into your remote server
ssh user@your-remote-server

# Download and apply the fix
cd /opt/wharftales
curl -fsSL https://raw.githubusercontent.com/giodc/wharftales/main/scripts/fix-upgrade-port-issue.sh -o /tmp/fix.sh
chmod +x /tmp/fix.sh
sudo /tmp/fix.sh
```

### Method 2: Manual Fix

```bash
# SSH into your remote server
ssh user@your-remote-server

# Stop containers to release ports
cd /opt/wharftales
sudo docker-compose down

# Wait for ports to be released
sleep 5

# Pull latest code (includes fixed upgrade.sh)
sudo git pull origin main

# Start containers
sudo docker-compose up -d
```

### Method 3: Replace upgrade.sh Directly

```bash
# SSH into your remote server
ssh user@your-remote-server

# Backup current script
sudo cp /opt/wharftales/scripts/upgrade.sh /opt/wharftales/scripts/upgrade.sh.backup

# Download fixed version
sudo curl -fsSL https://raw.githubusercontent.com/giodc/wharftales/main/scripts/upgrade.sh \
  -o /opt/wharftales/scripts/upgrade.sh

# Make executable
sudo chmod +x /opt/wharftales/scripts/upgrade.sh
```

## Permanent Solution

### 1. Commit Fixed Files to Your Repository

On your **local/development** machine:

```bash
cd /opt/wharftales

# Add all the fixed files
git add scripts/upgrade.sh
git add scripts/fix-upgrade-port-issue.sh
git add scripts/install.sh
git add REMOTE_UPDATE_FIX.md

# Commit
git commit -m "Fix port conflict during updates and add install script"

# Push to GitHub
git push origin main
```

### 2. Update Remote Server

On your **remote server**:

```bash
cd /opt/wharftales

# Stop containers
sudo docker-compose down

# Pull latest changes
sudo git pull origin main

# Start containers
sudo docker-compose up -d
```

## Future Updates

After applying the fix, updates will work smoothly:

```bash
# On remote server
cd /opt/wharftales
sudo /opt/wharftales/scripts/upgrade.sh
```

The fixed script will:
1. ✅ Stop containers first (releases ports)
2. ✅ Wait for ports to be released
3. ✅ Verify port 80 is free
4. ✅ Pull new images
5. ✅ Start containers cleanly

## Verify the Fix is Applied

Check if your upgrade.sh has the fix:

```bash
grep -q "docker-compose down" /opt/wharftales/scripts/upgrade.sh && echo "✓ Fix applied" || echo "✗ Fix not applied"
```

## If You Still Get Port Conflicts

1. **Check what's using port 80:**
   ```bash
   sudo ss -tlnp | grep ':80 '
   ```

2. **Stop nginx if running on host:**
   ```bash
   sudo systemctl stop nginx
   sudo systemctl disable nginx
   ```

3. **Force remove all containers:**
   ```bash
   cd /opt/wharftales
   sudo docker-compose down -v
   sudo docker-compose up -d
   ```

4. **Use the diagnostic script:**
   ```bash
   sudo /opt/wharftales/scripts/fix-port-conflict.sh
   ```

## Installation from Scratch

For new installations, use the install script:

```bash
curl -fsSL https://raw.githubusercontent.com/giodc/wharftales/main/scripts/install.sh | sudo bash
```

This will automatically include all fixes.
