# Install Scripts Comparison

## Overview
Webbadeploy has different installation scripts for different environments.

---

## 📋 Available Scripts

### 1. **install.sh** - Basic Installation
**Use for:** Quick setup, testing, updates

### 2. **install-production.sh** - Production Installation  
**Use for:** Production servers, security-focused deployments

### 3. **fix-local-docker.sh** - Local Development Fix
**Use for:** Fixing Docker permission issues on local machines

---

## 🔍 Detailed Comparison

### install.sh

**Purpose:** Basic installation and updates

**Features:**
- ✅ Installs Docker and Docker Compose
- ✅ Clones/updates repository
- ✅ Runs migrations
- ✅ Fixes basic permissions
- ⚠️ Uses **660** for Docker socket (secure)
- ⚠️ Minimal security hardening

**Permissions Set:**
```bash
Docker socket:     660 (root:docker)
Data directory:    775 (www-data:www-data)
Apps directory:    775 (www-data:www-data)
docker-compose:    664 (www-data:www-data)
```

**When to Use:**
- ✅ Quick testing
- ✅ Updates to existing installation
- ✅ Development servers
- ❌ NOT for production (use install-production.sh)

---

### install-production.sh

**Purpose:** Secure production deployment

**Features:**
- ✅ Full security hardening
- ✅ Strict file permissions (755)
- ✅ Secure Docker socket (660 with group)
- ✅ SSL directory protection (750)
- ✅ Firewall configuration prompts
- ✅ Backup directory setup
- ✅ Comprehensive security checks
- ✅ Production-ready defaults

**Permissions Set:**
```bash
Docker socket:     660 (root:docker)         # Secure
Data directory:    755 (www-data:www-data)   # Restricted
Apps directory:    755 (www-data:www-data)   # Restricted
SSL directory:     750 (root:www-data)       # Very restricted
docker-compose:    640 (root:www-data)       # Read-only for www-data
```

**Security Features:**
- 🔒 No world-writable directories (no 777)
- 🔒 Docker socket with group permissions (not 666)
- 🔒 SSL certificates protected
- 🔒 Root owns sensitive files
- 🔒 www-data has minimal write access
- 🔒 Firewall configuration guidance

**When to Use:**
- ✅ Production servers
- ✅ Public-facing deployments
- ✅ Security-critical environments
- ✅ First-time production setup

---

### fix-local-docker.sh

**Purpose:** Fix Docker permission issues on local machines

**Features:**
- ✅ Auto-detects Docker GID
- ✅ Updates docker-compose.yml
- ✅ Rebuilds containers
- ✅ Permissive permissions for easy development
- ⚠️ Uses **666** for Docker socket (local only!)
- ⚠️ Uses **777** for data/apps (local only!)

**Permissions Set:**
```bash
Docker socket:     666 (root:docker)         # Permissive (local only!)
Data directory:    777 (user:user)           # Very permissive
Apps directory:    777 (user:user)           # Very permissive
docker-compose:    664 (user:user)           # User-writable
```

**When to Use:**
- ✅ Local development machine
- ✅ Docker GID mismatch errors
- ✅ Permission denied errors locally
- ❌ NEVER on production servers!

---

## 📊 Side-by-Side Comparison

| Feature | install.sh | install-production.sh | fix-local-docker.sh |
|---------|-----------|----------------------|---------------------|
| **Target** | Testing/Updates | Production | Local Dev |
| **Docker Socket** | 660 | 660 | 666 ⚠️ |
| **Data Dir** | 775 | 755 | 777 ⚠️ |
| **Apps Dir** | 775 | 755 | 777 ⚠️ |
| **SSL Dir** | - | 750 🔒 | - |
| **docker-compose** | 664 | 640 🔒 | 664 |
| **Owner** | www-data | www-data/root | user |
| **Security Hardening** | Basic | Full 🔒 | None ⚠️ |
| **Firewall Setup** | No | Yes | No |
| **Auto-detect GID** | No | No | Yes ✅ |
| **Rebuild Containers** | Yes | Yes | Yes |

---

## 🎯 Which Script Should I Use?

### Scenario 1: First Production Deployment
```bash
sudo ./install-production.sh
```
**Why:** Full security hardening, proper permissions, production-ready

### Scenario 2: Updating Existing Installation
```bash
sudo ./install.sh
```
**Why:** Quick update, maintains existing setup

### Scenario 3: Local Development (Sites Not Working)
```bash
sudo ./fix-local-docker.sh
```
**Why:** Fixes Docker GID mismatch, permissive permissions for dev

### Scenario 4: Testing on Local Machine (First Time)
```bash
sudo ./install.sh
# If sites don't work:
sudo ./fix-local-docker.sh
```

### Scenario 5: Production Server (Already Installed with install.sh)
```bash
# Option A: Quick update (keeps current permissions)
sudo ./install.sh

# Option B: Upgrade to production security (hardens permissions)
sudo ./install-production.sh
# ⚠️ Will ask to overwrite and create backup
# ⚠️ Will set stricter permissions (755 instead of 775)
```

**Important:** install-production.sh CAN upgrade an existing installation:
- ✅ Creates automatic backup before overwriting
- ✅ Asks for confirmation
- ✅ Updates all files from GitHub
- ✅ Applies production security settings
- ✅ Runs all migrations
- ⚠️ Changes permissions from 775 → 755 (more restrictive)

---

## 🔄 Upgrading from install.sh to install-production.sh

### Yes, You Can Upgrade! ✅

If your remote server was installed with `install.sh`, you **can** run `install-production.sh` to upgrade to production security.

### What Happens:

1. **Backup Created Automatically**
   ```bash
   /opt/webbadeploy → /opt/webbadeploy_backup_20251011_010000
   ```

2. **Confirmation Prompt**
   ```
   Warning: /opt/webbadeploy already exists
   Do you want to overwrite? (y/N):
   ```

3. **Fresh Clone from GitHub**
   - Downloads latest code
   - Preserves your data/apps directories
   - Updates all system files

4. **Security Hardening Applied**
   - Permissions: 775 → 755
   - Docker socket: stays 660 (already secure)
   - SSL directory: 750 (new protection)
   - docker-compose.yml: 640 (more restrictive)

5. **Migrations Run**
   - Database updates
   - New features enabled
   - Existing sites preserved

### What Gets Preserved:

✅ **Your Data:**
- `/opt/webbadeploy/data/database.sqlite` - All sites, users, settings
- `/opt/webbadeploy/apps/` - All deployed sites and files
- `/opt/webbadeploy/ssl/` - SSL certificates

✅ **Your Sites:**
- All WordPress sites keep running
- All PHP sites keep running
- All Laravel sites keep running
- Database connections maintained

### What Changes:

⚠️ **File Permissions:**
```bash
Before (install.sh):        After (install-production.sh):
data: 775                   data: 755
apps: 775                   apps: 755
docker-compose: 664         docker-compose: 640
```

⚠️ **Code Files:**
- GUI updated to latest version
- New features added
- Bug fixes applied

### Safe to Run? YES! ✅

**Reasons:**
1. Automatic backup created
2. Data directories preserved
3. Running containers not affected during upgrade
4. Can rollback if needed

### How to Upgrade:

```bash
# On your remote server
cd /opt/webbadeploy
sudo ./install-production.sh

# When prompted:
# "Do you want to overwrite? (y/N):" → Type: y

# Wait for completion (~5-10 minutes)
# Done! Your server is now production-hardened
```

### Rollback if Needed:

```bash
# If something goes wrong:
sudo rm -rf /opt/webbadeploy
sudo mv /opt/webbadeploy_backup_* /opt/webbadeploy
docker-compose restart
```

### Recommended Upgrade Path:

```bash
# 1. Test on local/staging first (optional but recommended)
# 2. Backup manually (extra safety)
sudo tar -czf ~/webbadeploy-manual-backup.tar.gz /opt/webbadeploy

# 3. Run production installer
cd /opt/webbadeploy
sudo ./install-production.sh

# 4. Verify everything works
# 5. Delete old backup after confirming
sudo rm -rf /opt/webbadeploy_backup_*
```

---

## 🔒 Security Implications

### install.sh
- ✅ Reasonably secure
- ⚠️ Not hardened for production
- ⚠️ Some directories more permissive than needed

### install-production.sh
- ✅ Fully hardened
- ✅ Minimal permissions
- ✅ Production-ready
- ✅ Follows security best practices

### fix-local-docker.sh
- ❌ NOT secure (by design)
- ⚠️ World-writable directories (777)
- ⚠️ World-writable Docker socket (666)
- ✅ Perfect for local development
- ❌ NEVER use in production!

---

## 🐛 Your Current Issue

**Error:** `Temporary failure resolving 'deb.debian.org'`

**Cause:** Network/DNS issue, not related to the install script

**Solutions:**

### Option 1: Fix Network (Recommended)
```bash
# Check DNS
ping deb.debian.org

# If fails, check network:
ping 8.8.8.8

# Fix DNS (add to /etc/resolv.conf):
nameserver 8.8.8.8
nameserver 1.1.1.1
```

### Option 2: Use Existing Containers
If your containers are already built, skip the rebuild:
```bash
# Just fix permissions without rebuilding:
cd /opt/webbadeploy

# Get Docker GID
DOCKER_GID=$(getent group docker | cut -d: -f3)
echo "Docker GID: $DOCKER_GID"

# Update docker-compose.yml
sudo sed -i "s/DOCKER_GID: [0-9]*/DOCKER_GID: $DOCKER_GID/" docker-compose.yml

# Fix permissions
sudo chmod 777 /opt/webbadeploy/data
sudo chmod 777 /opt/webbadeploy/apps
sudo chmod 666 /var/run/docker.sock

# Restart containers (don't rebuild)
docker-compose restart
```

### Option 3: Wait and Retry
Network issues are often temporary:
```bash
# Wait a few minutes, then:
sudo ./fix-local-docker.sh
```

---

## 📝 Key Differences Summary

### install.sh
- **Purpose:** Quick setup and updates
- **Security:** Basic (660 socket, 775 dirs)
- **Target:** Development/testing
- **Time:** ~5 minutes

### install-production.sh
- **Purpose:** Secure production deployment
- **Security:** Full hardening (660 socket, 755 dirs, 750 SSL)
- **Target:** Production servers
- **Time:** ~10 minutes
- **Extras:** Firewall setup, SSL protection, security audit

### fix-local-docker.sh
- **Purpose:** Fix local Docker issues
- **Security:** Permissive (666 socket, 777 dirs)
- **Target:** Local development only
- **Time:** ~3 minutes
- **Extras:** Auto-detects GID, rebuilds containers

---

## 🚀 Recommended Workflow

### For Production:
1. Fresh server → `install-production.sh`
2. Updates → `install.sh`
3. Security audit → Check SECURITY-CHECKLIST.md

### For Local Development:
1. First time → `install.sh`
2. If issues → `fix-local-docker.sh`
3. Updates → `git pull && docker-compose restart`

---

## ⚠️ Important Notes

1. **Never use fix-local-docker.sh on production!**
   - Sets 777 permissions (world-writable)
   - Sets 666 Docker socket (world-writable)
   - Major security risk!

2. **install-production.sh is one-way**
   - Once hardened, don't run fix-local-docker.sh
   - Would undo security hardening

3. **Network issues affect all scripts**
   - Can't build containers without internet
   - Fix DNS/network first

4. **Docker GID matters**
   - Must match between host and container
   - fix-local-docker.sh auto-detects this
   - Hardcoded 988 in docker-compose.yml by default

---

## 🔧 Manual Permission Fix (No Rebuild)

If you can't rebuild due to network issues:

```bash
# 1. Get Docker GID
DOCKER_GID=$(getent group docker | cut -d: -f3)

# 2. Update docker-compose.yml
sudo sed -i "s/DOCKER_GID: [0-9]*/DOCKER_GID: $DOCKER_GID/" docker-compose.yml

# 3. Fix host permissions (local dev)
sudo chmod 777 /opt/webbadeploy/data
sudo chmod 777 /opt/webbadeploy/apps
sudo chmod 666 /var/run/docker.sock

# 4. Fix container permissions (if running)
docker exec -u root webbadeploy_gui chown -R www-data:www-data /app/data
docker exec -u root webbadeploy_gui chmod -R 777 /app/data
docker exec -u root webbadeploy_gui chown -R www-data:www-data /app/apps
docker exec -u root webbadeploy_gui chmod -R 777 /app/apps

# 5. Restart (don't rebuild)
docker-compose restart
```

---

## 📚 Related Documentation

- **SECURITY-CHECKLIST.md** - Complete security guide
- **README.md** - General installation instructions
- **SECURITY-GITHUB-TOKENS.md** - Token encryption details

---

**Last Updated:** 2025-10-11
