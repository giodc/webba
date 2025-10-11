# Docker Compose Configuration Changes

## 🎯 What Changed

The `docker-compose.yml` file is now **server-specific** and **git-ignored** to prevent configuration conflicts between different servers.

## 📝 Changes Made

### 1. Git Configuration
- ✅ Added `docker-compose.yml` to `.gitignore`
- ✅ Created `docker-compose.yml.template` (tracked in git)

### 2. Installation Scripts Updated
- ✅ `install.sh` - Copies from template on new install
- ✅ `install-production.sh` - Copies from template on new install

### 3. New Features in Template
- ✅ Port 8443 for alternative HTTPS (extra security)
- ✅ Placeholders for email and domain (CHANGE_ME)
- ✅ Comments explaining configuration

## 🚀 For New Installations

The install scripts now:
1. Check if `docker-compose.yml` exists
2. If not, copy from `docker-compose.yml.template`
3. Show warning to configure email and domain
4. Set correct permissions

**You must edit after installation:**
```bash
nano /opt/webbadeploy/docker-compose.yml
# Change CHANGE_ME@example.com to your email
# Change CHANGE_ME.example.com to your domain
```

## 🔄 For Existing Installations

**Your current `docker-compose.yml` is preserved!**

When you `git pull`:
- ✅ Your `docker-compose.yml` is NOT overwritten
- ✅ Template updates are available in `docker-compose.yml.template`
- ✅ You can manually merge new features if desired

## 📋 Migration Steps

### If You Want the New Features (Port 8443, etc.)

```bash
cd /opt/webbadeploy

# Backup your current config
cp docker-compose.yml docker-compose.yml.backup

# Compare with template
diff docker-compose.yml docker-compose.yml.template

# Manually add new features you want
nano docker-compose.yml

# Add port 8443 configuration (see template)
# Restart services
docker-compose up -d
```

### If You're Happy with Current Setup

**Do nothing!** Your configuration continues to work as-is.

## 🎁 New Features Available

### Alternative HTTPS Port (8443)

Access dashboard on non-standard port for extra security:
```
https://dashboard.yourdomain.com:8443
```

**Benefits:**
- Security through obscurity
- Bots don't scan non-standard ports
- Same SSL certificate as port 443

**To enable:**
1. Add port 8443 configuration from template
2. Open firewall: `sudo ufw allow 8443/tcp`
3. Restart: `docker-compose up -d`

## 📚 Documentation

- `DOCKER-COMPOSE-SETUP.md` - Complete guide
- `docker-compose.yml.template` - Template with placeholders
- `.gitignore` - Updated to ignore docker-compose.yml

## ⚠️ Important Notes

### What's Git-Ignored Now
- `docker-compose.yml` - Your server-specific config
- `data/` - Database
- `ssl/` - Certificates
- `logs/` - Log files

### What's Still Tracked
- `docker-compose.yml.template` - Shared template
- All application code
- Installation scripts
- Documentation

### Best Practices

1. **Never commit** `docker-compose.yml` with real credentials
2. **Always backup** your `docker-compose.yml` before major changes
3. **Review template** after git pull for new features
4. **Test changes** in development before production

## 🔧 Troubleshooting

### "docker-compose.yml not found" after git pull

```bash
# Copy from template
cp docker-compose.yml.template docker-compose.yml

# Configure your settings
nano docker-compose.yml
```

### Want to share your config improvements

```bash
# Edit template (use placeholders!)
nano docker-compose.yml.template

# Replace real values with CHANGE_ME
# Commit and push
git add docker-compose.yml.template
git commit -m "Improve docker-compose template"
git push
```

### Accidentally committed docker-compose.yml

```bash
# Remove from git (keeps local file)
git rm --cached docker-compose.yml

# Commit the removal
git commit -m "Remove docker-compose.yml from git"
git push
```

## ✅ Summary

**Before:**
- ❌ Same config for all servers
- ❌ Git conflicts on pull
- ❌ Sensitive data in git

**After:**
- ✅ Each server has unique config
- ✅ No conflicts on pull
- ✅ Sensitive data stays local
- ✅ Template shows new features

---

**Version:** 0.0.2 alpha
**Date:** October 11, 2025
**Impact:** All new installations, optional for existing
