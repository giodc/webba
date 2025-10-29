# Unified Install Script - Fresh & Existing Installations

## Overview

The `install.sh` script now intelligently handles BOTH fresh installations AND existing installations (updates). It automatically detects which mode to run and applies all necessary fixes.

---

## How It Works

### Auto-Detection

```bash
# Check if this is an update (wharftales already exists)
if [ -d "/opt/wharftales/.git" ]; then
    echo "Existing installation detected. Running update mode..."
    UPDATE_MODE=true
else
    echo "New installation mode..."
    UPDATE_MODE=false
fi
```

The script checks if `/opt/wharftales/.git` exists:
- **EXISTS** → Update Mode (existing installation)
- **DOESN'T EXIST** → Fresh Install Mode

---

## Fresh Installation Mode

### What Happens:

1. ✅ Clones repository from GitHub
2. ✅ Creates all directories with correct permissions
3. ✅ Sets up Docker environment
4. ✅ Creates docker-compose.yml from template
5. ✅ Starts containers
6. ✅ Fixes permissions (data & apps)
7. ✅ Creates database tables
8. ✅ Runs all migrations
9. ✅ Ready to use!

### Permissions Set:

```bash
# Host directories
chown -R www-data:www-data /opt/wharftales/data
chown -R www-data:www-data /opt/wharftales/apps
chmod -R 775 /opt/wharftales/data
chmod -R 775 /opt/wharftales/apps

# Container directories
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/data /app/apps
docker exec -u root wharftales_gui bash -c "find /app/apps -type d -exec chmod 775 {} \;"
docker exec -u root wharftales_gui bash -c "find /app/apps -type f -exec chmod 664 {} \;"
```

### Database Tables Created:

```sql
CREATE TABLE IF NOT EXISTS settings (...);
CREATE TABLE IF NOT EXISTS compose_configs (...);
```

---

## Update Mode (Existing Installation)

### What Happens:

1. ✅ Detects existing installation
2. ✅ Backs up configurations:
   - docker-compose.yml (email, domains)
   - database.sqlite (all data)
   - acme.json (SSL certificates)
3. ✅ Pulls latest code from GitHub
4. ✅ Restores configurations (preserves settings!)
5. ✅ Rebuilds containers
6. ✅ Fixes permissions (data & apps)
7. ✅ Creates missing database tables
8. ✅ Runs all migrations
9. ✅ Ready to use!

### Backup Location:

```
/opt/wharftales/data/backups/update-YYYYMMDD-HHMMSS/
├── docker-compose.yml
├── database.sqlite
└── acme.json
```

### Permissions Fixed:

Same as fresh install - ensures all permissions are correct even if they were wrong before.

### Database Tables:

Creates `settings` and `compose_configs` tables if they don't exist.

---

## Usage

### Fresh Installation:

```bash
# Download and run
curl -fsSL https://raw.githubusercontent.com/giodc/wharftales/master/install.sh | sudo bash
```

OR

```bash
# Clone and run
git clone https://github.com/giodc/wharftales.git /opt/wharftales
cd /opt/wharftales
sudo ./install.sh
```

### Update Existing Installation:

```bash
# Just run install.sh again!
cd /opt/wharftales
sudo ./install.sh
```

The script automatically detects it's an update and:
- Backs up your settings
- Pulls latest code
- Restores your settings
- Applies all fixes

---

## What Gets Fixed in Both Modes

| Fix | Fresh Install | Update Mode |
|-----|---------------|-------------|
| Directory permissions | ✅ | ✅ |
| Apps permissions | ✅ | ✅ |
| Subdirectory permissions | ✅ | ✅ |
| File permissions | ✅ | ✅ |
| Settings table | ✅ | ✅ |
| Compose_configs table | ✅ | ✅ |
| All migrations | ✅ | ✅ |
| Database permissions | ✅ | ✅ |

---

## Comparison with Other Scripts

### install.sh (This Script)

**Use When:**
- Fresh installation
- Updating existing installation
- Want automatic mode detection

**Features:**
- Auto-detects fresh vs update
- Backs up configurations in update mode
- Restores settings after update
- Applies all fixes in both modes

### safe-update.sh

**Use When:**
- Explicitly want update mode
- Want detailed progress output
- Want verification steps

**Features:**
- Always runs in update mode
- More verbose output
- Shows verification at end
- Color-coded messages

### update.sh

**Use When:**
- Quick update without backup/restore
- Development environment

**Features:**
- Simple update
- Rebuilds containers
- Applies fixes
- Less verbose

---

## Verification

After running `install.sh` (either mode):

```bash
# 1. Check containers
docker ps
# Should show: wharftales_traefik, wharftales_gui, wharftales_db

# 2. Check permissions
docker exec wharftales_gui ls -la /app/data /app/apps
# Should show: drwxrwxr-x www-data www-data

# 3. Check database tables
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT name FROM sqlite_master WHERE type='table';"
# Should include: settings, compose_configs

# 4. Access dashboard
# http://your-server:9000
# Should load without errors

# 5. Try creating a site
# Should work without permission errors
```

---

## Troubleshooting

### "Existing installation detected" but I want fresh install

```bash
# Remove the .git directory
sudo rm -rf /opt/wharftales/.git

# Run install.sh again
sudo ./install.sh
```

### Settings not preserved after update

Check backup:
```bash
ls -la /opt/wharftales/data/backups/
# Find latest backup
cat /opt/wharftales/data/backups/update-*/docker-compose.yml
```

Restore manually if needed:
```bash
BACKUP="/opt/wharftales/data/backups/update-YYYYMMDD-HHMMSS"
sudo cp $BACKUP/docker-compose.yml /opt/wharftales/
sudo docker-compose restart
```

### Permission errors after install

Re-run permission fixes:
```bash
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/data /app/apps
docker exec -u root wharftales_gui bash -c "find /app/apps -type d -exec chmod 775 {} \;"
docker exec -u root wharftales_gui bash -c "find /app/apps -type f -exec chmod 664 {} \;"
```

---

## Summary

**One script, two modes, all fixes:**

```bash
# Fresh install OR update - same command!
sudo ./install.sh
```

The script:
- ✅ Auto-detects mode
- ✅ Backs up settings (update mode)
- ✅ Applies all fixes
- ✅ Creates missing tables
- ✅ Fixes permissions
- ✅ Runs migrations
- ✅ Works perfectly in both modes

**Result:** Whether you're installing fresh or updating, everything just works! 🎉
