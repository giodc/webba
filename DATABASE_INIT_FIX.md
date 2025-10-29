# Database Initialization Fix

## Problem
After renaming from Webbadeploy to WharfTales, fresh installations and updates were showing:
- "Failed to update dashboard configuration: Main Traefik configuration not found in database"
- "Failed to update Let's Encrypt email: Compose configuration not found"
- Error 500 on dashboard

## Root Cause
The `settings` table wasn't being created during installation/updates, causing the dashboard to fail when trying to read/write configuration.

## Solution Implemented

### Files Updated:

1. **`/opt/wharftales/install.sh`**
   - Added settings table creation after migrations
   - Ensures table exists before first dashboard access

2. **`/opt/wharftales/safe-update.sh`**
   - Added settings table creation in migration step
   - Runs after compose-to-db migration

3. **`/opt/wharftales/update.sh`**
   - Added settings table creation after container start
   - Runs migrations automatically

### What Gets Created:

```sql
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### When It Runs:

- ✅ **Fresh Install**: After containers start, before first access
- ✅ **Update**: After pulling code and restarting containers
- ✅ **Safe Update**: During migration step

## Manual Fix (If Needed)

If you're on an existing installation that's broken:

```bash
# Create settings table
docker exec wharftales_gui sqlite3 /app/data/database.sqlite << 'EOF'
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
EOF

# Run compose migration
docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php

# Restart GUI
docker restart wharftales_gui
```

## Verification

After install/update, verify the table exists:

```bash
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT name FROM sqlite_master WHERE type='table' AND name='settings';"
```

Should output: `settings`

## Result

- ✅ Fresh installations work immediately
- ✅ Updates preserve settings and initialize table
- ✅ Dashboard loads without errors
- ✅ Settings page works correctly
