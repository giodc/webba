# Quick Deploy Reference

## 🚀 Deploy Fix to All Remote Servers (3 Steps)

### Step 1: Commit Your Changes
```bash
cd /opt/webbadeploy
git add .
git commit -m "Fix site permissions database bug"
git push origin master
```

### Step 2: Update Remote Servers

**Option A: Automated (Recommended)**
```bash
# Edit servers.txt with your server list
nano scripts/servers.txt

# Run deployment script
./scripts/deploy-to-remote-servers.sh
```

**Option B: Manual**
```bash
# SSH into each server and run:
cd /opt/webbadeploy
sudo ./safe-update.sh
```

### Step 3: Verify
```bash
# Check one server to confirm
ssh user@server1.example.com
docker ps | grep webbadeploy
docker logs webbadeploy_gui --tail 20
```

---

## 📋 What Happens Automatically

When you run `safe-update.sh` on a remote server:

1. ✅ **Backs up** docker-compose.yml, database.sqlite, acme.json
2. ✅ **Pulls** latest code from GitHub
3. ✅ **Restores** all user configurations
4. ✅ **Runs migrations** (including your new fix)
5. ✅ **Rebuilds** containers
6. ✅ **Verifies** everything works

**Migrations that run automatically:**
- `migrate-rbac-2fa.php`
- `migrate-php-version.php`
- `add_github_fields.php`
- `fix-site-permissions-database.php` ← **Your new fix**
- `migrate-compose-to-db.php`

---

## 🔧 Current Fix Details

**File:** `/opt/webbadeploy/gui/migrations/fix-site-permissions-database.php`

**What it fixes:**
- Regular users getting "Access denied" errors
- Database mismatch in permission system

**Safe to run multiple times:** Yes

**Automatic:** Yes (runs during updates)

---

## 📝 servers.txt Format

Create `/opt/webbadeploy/scripts/servers.txt`:

```txt
# Production servers
root@prod1.example.com
root@prod2.example.com

# Staging servers  
deploy@staging.example.com

# Using SSH config aliases
prod-server-3
prod-server-4
```

---

## 🆘 Quick Troubleshooting

### Migration didn't run?
```bash
# Run manually
docker exec webbadeploy_gui php /var/www/html/migrations/fix-site-permissions-database.php
```

### Users still can't access sites?
```bash
# Check database
docker exec webbadeploy_gui sqlite3 /app/data/database.sqlite "SELECT * FROM site_permissions"

# Check permissions
docker exec webbadeploy_gui php -r "require '/var/www/html/includes/auth.php'; var_dump(canAccessSite(2, 1, 'view'));"
```

### Need to rollback?
```bash
# Find backup
ls -lt /opt/webbadeploy/data/backups/

# Restore
BACKUP="/opt/webbadeploy/data/backups/update-20241028-233045"
sudo cp $BACKUP/docker-compose.yml /opt/webbadeploy/
sudo cp $BACKUP/database.sqlite /opt/webbadeploy/data/
sudo docker-compose restart
```

---

## 📊 Deployment Checklist

- [ ] Code changes committed and pushed to GitHub
- [ ] Migration script created (if needed)
- [ ] Migration added to install.sh and safe-update.sh
- [ ] Tested locally
- [ ] servers.txt configured
- [ ] Run deployment script
- [ ] Verify on one server
- [ ] Monitor for errors
- [ ] Update documentation

---

## 🎯 One-Liner Deploy

```bash
git add . && git commit -m "Fix permissions" && git push && ./scripts/deploy-to-remote-servers.sh
```

---

## 📚 Full Documentation

- **Complete Guide:** `/opt/webbadeploy/REMOTE_DEPLOYMENT_GUIDE.md`
- **Update Guide:** `/opt/webbadeploy/UPDATE_GUIDE.md`
- **SSL Fix Guide:** `/opt/webbadeploy/SSL_FIX_GUIDE.md`
