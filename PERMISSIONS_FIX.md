# Permissions Fix for Apps Directory

## Problem
Users were getting `mkdir(): Permission denied in /var/www/html/api.php on line 520` when creating new sites.

## Root Cause
The `/app/apps` directory (mounted from `/opt/wharftales/apps`) had incorrect ownership. During installation, ownership was set to `wharftales:wharftales`, preventing the web server (www-data) from creating subdirectories for new sites.

## Solution Implemented

### Files Updated:

1. **`/opt/wharftales/install.sh`**
   - Sets www-data ownership on `/opt/wharftales/apps` before and after setting wharftales ownership
   - Lines 213-221

2. **`/opt/wharftales/safe-update.sh`**
   - Ensures all subdirectories and files in `/app/apps` have correct permissions
   - Lines 179-181

3. **`/opt/wharftales/update.sh`**
   - Fixes permissions on `/app/apps` after update
   - Lines 85-92

### Permissions Set:

```bash
# Ownership
chown -R www-data:www-data /opt/wharftales/apps

# Directory permissions
chmod 775 /opt/wharftales/apps
find /app/apps -type d -exec chmod 775 {} \;

# File permissions
find /app/apps -type f -exec chmod 664 {} \;
```

### Why This Works:

- **775 on directories**: www-data can read, write, and execute (create subdirectories)
- **664 on files**: www-data can read and write files
- **www-data ownership**: Matches the Apache/PHP-FPM user inside the container

## Manual Fix (If Needed)

If you're on an existing installation:

```bash
# Fix from host
sudo chown -R www-data:www-data /opt/wharftales/apps
sudo chmod -R 775 /opt/wharftales/apps

# Or fix from container
docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/apps
docker exec -u root wharftales_gui bash -c "find /app/apps -type d -exec chmod 775 {} \\;"
docker exec -u root wharftales_gui bash -c "find /app/apps -type f -exec chmod 664 {} \\;"
```

## Verification

Check permissions:

```bash
# From host
ls -la /opt/wharftales/apps

# From container
docker exec wharftales_gui ls -la /app/apps
```

Should show:
```
drwxrwxr-x  www-data www-data  apps/
```

## Result

- ✅ New sites can be created without permission errors
- ✅ Docker compose files can be written to `/app/apps/`
- ✅ Site configurations are properly stored
- ✅ All install/update scripts fix permissions automatically
