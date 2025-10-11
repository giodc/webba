# Docker Compose Configuration Guide

## 📋 Overview

The `docker-compose.yml` file is **server-specific** and **NOT tracked in git**. Each server maintains its own configuration.

## 🗂️ File Structure

```
/opt/webbadeploy/
├── docker-compose.yml          # Server-specific (git-ignored)
├── docker-compose.yml.template # Template (tracked in git)
└── .gitignore                  # Ignores docker-compose.yml
```

## 🔧 How It Works

### For New Installations

1. **Clone repository** - Gets `docker-compose.yml.template`
2. **Copy template** - Creates `docker-compose.yml` from template
3. **Configure** - Update email, domain, etc.
4. **Git ignores** - Local changes stay local

### For Existing Servers

1. **Pull updates** - Gets new template if changed
2. **Local config preserved** - Your `docker-compose.yml` is not overwritten
3. **Manual merge** - Compare template changes and update if needed

## 🚀 Setup on New Server

```bash
# 1. Clone or pull repository
cd /opt/webbadeploy
git pull

# 2. Create docker-compose.yml from template (if doesn't exist)
if [ ! -f docker-compose.yml ]; then
    cp docker-compose.yml.template docker-compose.yml
fi

# 3. Configure your server-specific settings
nano docker-compose.yml

# Change:
# - CHANGE_ME@example.com → your-email@domain.com
# - CHANGE_ME.example.com → dashboard.yourdomain.com

# 4. Set permissions
chmod 664 docker-compose.yml
chown www-data:www-data docker-compose.yml

# 5. Start services
docker-compose up -d
```

## 📝 What to Configure

### Required Changes

**1. Email Address (Line ~16)**
```yaml
- "--certificatesresolvers.letsencrypt.acme.email=CHANGE_ME@example.com"
```
Change to:
```yaml
- "--certificatesresolvers.letsencrypt.acme.email=admin@yourdomain.com"
```

**2. Dashboard Domain (Lines ~67, 71, 76)**
```yaml
- traefik.http.routers.webgui.rule=Host(`CHANGE_ME.example.com`)
- traefik.http.routers.webgui-secure.rule=Host(`CHANGE_ME.example.com`)
- traefik.http.routers.webgui-alt.rule=Host(`CHANGE_ME.example.com`)
```
Change to:
```yaml
- traefik.http.routers.webgui.rule=Host(`dashboard.yourdomain.com`)
- traefik.http.routers.webgui-secure.rule=Host(`dashboard.yourdomain.com`)
- traefik.http.routers.webgui-alt.rule=Host(`dashboard.yourdomain.com`)
```

### Optional Changes

**Custom Ports**
- Port 9000: HTTP fallback (default)
- Port 8443: Alternative HTTPS (optional, for extra security)

**Database Passwords**
- Change default MariaDB passwords for production

## 🔄 Updating from Git

When you pull updates:

```bash
cd /opt/webbadeploy
git pull

# Check if template changed
git diff HEAD@{1} docker-compose.yml.template

# If template has important updates, merge manually:
# 1. Review changes in template
# 2. Apply relevant changes to your docker-compose.yml
# 3. Keep your server-specific settings
```

## 🎯 Benefits of This Approach

### ✅ Advantages

1. **Server Independence** - Each server has its own config
2. **No Conflicts** - Git pull won't overwrite your settings
3. **Easy Updates** - Template shows new features
4. **Security** - Sensitive data (emails, domains) not in git
5. **Flexibility** - Different configs for dev/staging/production

### 📊 Comparison

| Aspect | Before | After |
|--------|--------|-------|
| Git tracking | ✅ Tracked | ❌ Ignored |
| Server-specific | ❌ Same for all | ✅ Each unique |
| Pull conflicts | ❌ Frequent | ✅ None |
| Template | ❌ None | ✅ Available |
| Security | ⚠️ Exposed | ✅ Private |

## 🛠️ Common Tasks

### Check Current Configuration

```bash
# View your email
grep "acme.email" docker-compose.yml

# View your domain
grep "Host(" docker-compose.yml | grep webgui
```

### Compare with Template

```bash
# See what's different
diff docker-compose.yml docker-compose.yml.template

# Or use a visual diff
vimdiff docker-compose.yml docker-compose.yml.template
```

### Reset to Template

```bash
# Backup current config
cp docker-compose.yml docker-compose.yml.backup

# Reset to template
cp docker-compose.yml.template docker-compose.yml

# Reconfigure
nano docker-compose.yml
```

### Update Template from Your Config

If you made improvements to share:

```bash
# Copy your config to template (remove sensitive data first!)
cp docker-compose.yml docker-compose.yml.template

# Replace sensitive data with placeholders
sed -i 's/admin@yourdomain.com/CHANGE_ME@example.com/' docker-compose.yml.template
sed -i 's/dashboard.yourdomain.com/CHANGE_ME.example.com/' docker-compose.yml.template

# Commit template changes
git add docker-compose.yml.template
git commit -m "Update docker-compose template"
git push
```

## 🔐 Security Notes

### What's Git-Ignored

- `docker-compose.yml` - Your actual config
- `data/` - Database and user data
- `ssl/` - SSL certificates
- `logs/` - Log files

### What's Tracked

- `docker-compose.yml.template` - Template with placeholders
- All application code
- Installation scripts
- Documentation

### Best Practices

1. **Never commit** `docker-compose.yml` with real credentials
2. **Always use** template for sharing configurations
3. **Review** `.gitignore` before committing
4. **Backup** your `docker-compose.yml` separately

## 📚 Related Files

- `.gitignore` - Defines what's ignored
- `install.sh` - Handles initial setup
- `install-production.sh` - Production installation
- `fix-dashboard-ssl.sh` - SSL configuration helper

## ❓ FAQ

**Q: What happens when I git pull?**
A: Your `docker-compose.yml` is preserved. Only the template updates.

**Q: How do I get new features from template?**
A: Compare template with your config and merge manually.

**Q: Can I track my config in a private repo?**
A: Yes! Create a private repo for your server-specific configs.

**Q: What if I accidentally commit docker-compose.yml?**
A: Remove it from git: `git rm --cached docker-compose.yml`

**Q: Do I need to update all servers when template changes?**
A: No, only if the template has features you want.

---

**Summary:** 
- ✅ `docker-compose.yml` = Server-specific, git-ignored
- ✅ `docker-compose.yml.template` = Shared template, git-tracked
- ✅ Each server maintains its own configuration
- ✅ No conflicts when pulling updates
