# WharfTales Auto-Update System

This document describes the auto-update system implementation for WharfTales.

## Overview

WharfTales includes a comprehensive auto-update system that can:
- Check for new versions automatically
- Notify administrators when updates are available
- Perform automatic or manual updates
- Create backups before updating
- Rollback if updates fail

## Components

### 1. Version Management

**versions.json** - Hosted on GitHub or CDN
```json
{
  "wharftales": {
    "latest": "0.0.4",
    "min_supported": "0.0.1",
    "update_url": "https://raw.githubusercontent.com/giodc/wharftales/main/scripts/upgrade.sh",
    "changelog_url": "https://github.com/giodc/wharftales/releases",
    "release_notes": "Bug fixes and improvements",
    "released_at": "2025-10-30"
  }
}
```

### 2. Update Script

**Location:** `/opt/wharftales/scripts/upgrade.sh`

The upgrade script:
1. Creates a timestamped backup
2. Pulls latest code from git
3. Updates Docker containers
4. Validates the update
5. Cleans up old backups (keeps last 5)

**Manual execution:**
```bash
/opt/wharftales/scripts/upgrade.sh
```

### 3. PHP Functions

**Location:** `/opt/wharftales/gui/includes/functions.php`

Key functions:
- `checkForUpdates($forceCheck)` - Checks versions.json for updates
- `triggerUpdate($skipBackup)` - Starts the update process
- `getUpdateStatus()` - Returns current update status
- `getCurrentVersion()` - Gets current WharfTales version

### 4. Settings UI

**Location:** `/opt/wharftales/gui/settings.php`

Administrators can configure:
- Enable/disable update checks
- Enable/disable automatic updates
- Update check frequency (hourly, daily, weekly)
- Custom versions URL

### 5. Navigation Notification

**Location:** `/opt/wharftales/gui/includes/navigation.php`

Shows a prominent badge when updates are available.

### 6. Cron Job (Optional)

**Location:** `/opt/wharftales/scripts/check-updates-cron.php`

For automatic update checking, add to crontab:
```bash
# Check for updates every 6 hours
0 */6 * * * /usr/bin/php /opt/wharftales/scripts/check-updates-cron.php >> /opt/wharftales/logs/update-check.log 2>&1
```

## Update Modes

### 1. Manual Update
1. Go to Settings → System Updates
2. Click "Check Now"
3. If update available, click "Update Now"
4. Confirm and wait for completion

### 2. Semi-Automatic
1. Enable "Update Checks" in settings
2. Disable "Automatic Updates"
3. System checks periodically and shows notification
4. Admin clicks notification to update

### 3. Fully Automatic
1. Enable both "Update Checks" and "Automatic Updates"
2. System checks and updates automatically
3. **Recommended for testing environments only**

## Safety Features

### Backups
- Automatic backup before each update
- Stored in `/opt/wharftales/backups/`
- Keeps last 5 backups
- Can skip backup with `skip_backup` parameter

### Rollback
If update fails, restore from backup:
```bash
cd /opt/wharftales
tar -xzf backups/wharftales-backup-YYYY-MM-DD-HH-MM-SS.tar.gz
docker-compose up -d
```

### Logging
All updates logged to:
- `/opt/wharftales/logs/upgrade-YYYY-MM-DD-HH-MM-SS.log`

### Update Lock
- Prevents concurrent updates
- Automatically releases after 10 minutes
- Manual release: Set `update_in_progress` to `0` in settings table

## API Endpoints

### Trigger Update
```bash
curl -X POST http://localhost:9000/api.php?action=trigger_update \
  -H "Content-Type: application/json" \
  -d '{"skip_backup": false}'
```

### Check Update Status
```bash
curl http://localhost:9000/api.php?action=check_update_status
```

## Database Settings

All update settings stored in `settings` table:

| Key | Default | Description |
|-----|---------|-------------|
| `update_check_enabled` | `1` | Enable update checks |
| `auto_update_enabled` | `0` | Enable automatic updates |
| `update_check_frequency` | `86400` | Check frequency in seconds |
| `versions_url` | GitHub URL | URL to versions.json |
| `update_notification` | `0` | Update available flag |
| `update_in_progress` | `0` | Update lock flag |
| `last_update_check` | `0` | Last check timestamp |
| `cached_update_info` | `null` | Cached update data |

## Troubleshooting

### Update Check Fails
1. Check internet connectivity
2. Verify versions.json URL is accessible
3. Check logs: `/opt/wharftales/logs/`
4. Manually check: `curl https://raw.githubusercontent.com/giodc/wharftales/refs/heads/master/versions.json`

### Port Conflict Error (Address Already in Use)
If you see: `failed to bind host port for 0.0.0.0:80: address already in use`

**Quick Fix:**
```bash
/opt/wharftales/scripts/fix-port-conflict.sh
```

**Manual Fix:**
```bash
# Stop containers
cd /opt/wharftales
docker-compose down

# Wait for ports to be released
sleep 5

# Start containers
docker-compose up -d
```

**If nginx is running on host:**
```bash
sudo systemctl stop nginx
sudo systemctl disable nginx
```

### Update Hangs
1. Check if git repository is clean: `cd /opt/wharftales && git status`
2. Check Docker containers: `docker-compose ps`
3. View update log: `tail -f /opt/wharftales/logs/upgrade-*.log`
4. Reset update lock in database

### Rollback Needed
```bash
# List available backups
ls -lh /opt/wharftales/backups/

# Restore from backup
cd /opt/wharftales
tar -xzf backups/wharftales-backup-YYYY-MM-DD-HH-MM-SS.tar.gz

# Restart containers
docker-compose up -d --force-recreate
```

## Publishing Updates

To publish a new version:

1. Update `/opt/wharftales/VERSION` file
2. Commit and push changes
3. Create GitHub release
4. Update `versions.json` on CDN/GitHub
5. Users will be notified within their check frequency

## Security Considerations

- Only administrators can trigger updates
- Update script runs with server permissions
- Backups exclude sensitive data (database.sqlite)
- HTTPS recommended for versions.json URL
- Consider testing updates on staging first

## Future Enhancements

- [ ] Email notifications for updates
- [ ] Scheduled update windows
- [ ] Update channels (stable, beta, nightly)
- [ ] Webhook notifications
- [ ] Update history/changelog viewer
- [ ] One-click rollback from UI
