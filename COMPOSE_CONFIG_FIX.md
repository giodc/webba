# Compose Configuration Fix

## Problem
Users were getting "Failed to update Let's Encrypt email: Compose configuration not found" when trying to save settings.

## Root Cause
The `compose_configs` table didn't exist in the database, and the migration script to populate it wasn't running or didn't exist.

## Solution Implemented

### 1. Auto-Create Table on Install/Update

Updated all installation scripts to create the `compose_configs` table:

**Files Updated:**
- `/opt/wharftales/install.sh` - Lines 329-338
- `/opt/wharftales/safe-update.sh` - Lines 162-171  
- `/opt/wharftales/update.sh` - Lines 81-90

**Table Schema:**
```sql
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

### 2. Auto-Initialize from docker-compose.yml

Updated `/opt/wharftales/gui/includes/functions.php` to automatically create the initial config if it doesn't exist:

**Function:** `updateComposeParameter()` (Lines 645-661)

**Logic:**
1. Check if compose config exists in database
2. If not, read `/opt/wharftales/docker-compose.yml`
3. Save it to database as initial config
4. Proceed with update

This means the first time you save a setting, it will automatically import your docker-compose.yml into the database.

## Manual Fix (If Needed)

If you're on an existing installation:

```bash
# Create the table
docker exec wharftales_gui sqlite3 /app/data/database.sqlite << 'EOF'
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
EOF

# Restart GUI
docker restart wharftales_gui
```

Then when you save a setting, it will auto-import the docker-compose.yml.

## Verification

Check if table exists:

```bash
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT name FROM sqlite_master WHERE type='table' AND name='compose_configs';"
```

Should output: `compose_configs`

Check if config is loaded:

```bash
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT id, config_type FROM compose_configs;"
```

After saving a setting, should show:
```
1|main
```

## Result

- ✅ Table created automatically on install/update
- ✅ First settings save auto-imports docker-compose.yml
- ✅ Let's Encrypt email can be saved
- ✅ Dashboard domain can be saved
- ✅ All settings work correctly
