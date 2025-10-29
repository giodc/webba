# WharfTales Update System

## Overview

WharfTales includes a built-in Git-based update system that allows you to easily update your installation from your Git repository.

## Features

- ✅ **One-Click Updates** - Update directly from the dashboard
- ✅ **Version Tracking** - Automatic version detection and comparison
- ✅ **Automatic Backups** - Creates backups before each update
- ✅ **Change Detection** - Warns about local modifications
- ✅ **Changelog Display** - Shows recent commits before updating
- ✅ **Update Notifications** - Visual indicator when updates are available
- ✅ **Safe Updates** - Stashes local changes automatically
- ✅ **Auto-Update Option** - Can be configured for automatic updates

## How It Works

1. **Version File**: The system uses `/opt/wharftales/VERSION` to track the current version
2. **Git Integration**: Checks the remote repository for a newer VERSION file
3. **Update Process**: Pulls latest changes via `git pull`
4. **Backup**: Creates a backup before updating
5. **Post-Update**: Fixes permissions and clears caches

## Usage

### Manual Update via Dashboard

1. Log in to WharfTales dashboard
2. If an update is available, you'll see an **"Update Available"** link in the navbar
3. Click the link to open the update modal
4. Review the changelog and current/new versions
5. Click **"Install Update"** to proceed
6. The system will:
   - Create a backup
   - Stash any local changes
   - Pull the latest code
   - Fix permissions
   - Reload the page

### Manual Update via Command Line

```bash
cd /opt/wharftales
git pull origin master
```

### Releasing a New Version

1. Update the `VERSION` file with the new version number:
   ```bash
   echo "1.1.0" > VERSION
   ```

2. Commit and push:
   ```bash
   git add VERSION
   git commit -m "Release v1.1.0"
   git push origin master
   ```

3. Users will see the update notification on their dashboard

## Configuration

Edit `/opt/wharftales/gui/includes/update-config.php`:

```php
// Enable/disable update system
define('UPDATE_ENABLED', true);

// Enable automatic updates (not recommended for production)
define('AUTO_UPDATE_ENABLED', false);

// How often to check for updates (in seconds)
define('UPDATE_CHECK_INTERVAL', 3600); // 1 hour

// Git remote and branch
define('GIT_REMOTE', 'origin');
define('GIT_BRANCH', 'master');
```

## Backup Location

Backups are stored in `/app/data/backups/` inside the container.

The system keeps the last 5 backups automatically.

## Update Logs

Update activity is logged to `/app/data/update.log`

View logs via the dashboard or:
```bash
docker exec wharftales_gui cat /app/data/update.log
```

## Troubleshooting

### Update fails with "Not a Git repository"

Make sure `/opt/wharftales` is a Git repository:
```bash
cd /opt/wharftales
git status
```

### Local changes preventing update

The system will automatically stash local changes. To manually handle:
```bash
cd /opt/wharftales
git stash
git pull origin master
```

### Permission errors after update

Run inside the container:
```bash
docker exec wharftales_gui chmod -R 755 /var/www/html/gui/includes /var/www/html/gui/js /var/www/html/gui/css
docker exec wharftales_gui chmod 644 /var/www/html/gui/includes/*.php /var/www/html/gui/*.php
```

## Security Considerations

- Only administrators can trigger updates
- Updates require authentication
- Backups are created before each update
- Local changes are preserved (stashed)
- Update logs track all activity

## API Endpoints

- `GET /api.php?action=check_updates` - Check for available updates
- `GET /api.php?action=get_update_info` - Get detailed update information
- `POST /api.php?action=perform_update` - Perform the update
- `GET /api.php?action=get_update_logs` - Get update logs

## Version Numbering

Use semantic versioning: `MAJOR.MINOR.PATCH`

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes

Example: `1.2.3`
