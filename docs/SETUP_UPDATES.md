# Setting Up the Update System

## Quick Start Guide

The update system needs `versions.json` to be accessible. You have two options:

### Option 1: Use GitHub (Recommended for Production)

1. **Commit and push versions.json to your repository:**
   ```bash
   cd /opt/wharftales
   git add versions.json
   git commit -m "Add versions.json for update system"
   git push origin main
   ```

2. **Verify it's accessible:**
   ```bash
   curl https://raw.githubusercontent.com/giodc/wharftales/main/versions.json
   ```

3. **The system will automatically use:**
   ```
   https://raw.githubusercontent.com/giodc/wharftales/main/versions.json
   ```

### Option 2: Use Local File (For Testing)

1. **Update the versions URL in settings:**
   - Go to Settings → System Updates
   - Change "Versions URL" to: `/opt/wharftales/versions.json`
   - Click "Save Update Settings"

2. **Or via database:**
   ```bash
   sqlite3 /opt/wharftales/data/database.sqlite "INSERT OR REPLACE INTO settings (key, value) VALUES ('versions_url', '/opt/wharftales/versions.json');"
   ```

3. **Test the update check:**
   - Go to Settings → System Updates
   - Click "Check Now"

### Option 3: Use a CDN or Custom URL

If you want to host `versions.json` on a CDN or custom server:

1. **Upload versions.json to your server/CDN**

2. **Update the URL in settings:**
   - Go to Settings → System Updates
   - Change "Versions URL" to your custom URL
   - Click "Save Update Settings"

## Updating the Version

When you release a new version:

1. **Update the VERSION file:**
   ```bash
   echo "0.0.5 alpha" > /opt/wharftales/VERSION
   ```

2. **Update versions.json:**
   ```json
   {
     "wharftales": {
       "latest": "0.0.5",
       "min_supported": "0.0.1",
       "update_url": "https://raw.githubusercontent.com/giodc/wharftales/main/scripts/upgrade.sh",
       "changelog_url": "https://github.com/giodc/wharftales/releases/tag/v0.0.5",
       "release_notes": "New features: X, Y, Z. Bug fixes: A, B, C.",
       "released_at": "2025-10-31"
     }
   }
   ```

3. **Commit and push:**
   ```bash
   git add VERSION versions.json
   git commit -m "Release v0.0.5"
   git tag v0.0.5
   git push origin main --tags
   ```

4. **Create a GitHub release** (optional but recommended):
   - Go to your repository on GitHub
   - Click "Releases" → "Create a new release"
   - Select tag `v0.0.5`
   - Add release notes
   - Publish release

## Testing the Update System

### Test Update Check (Local)

```bash
# Use local versions.json for testing
sqlite3 /opt/wharftales/data/database.sqlite "INSERT OR REPLACE INTO settings (key, value) VALUES ('versions_url', '/opt/wharftales/versions.json');"

# Modify versions.json to show a newer version
cat > /opt/wharftales/versions.json << 'EOF'
{
  "wharftales": {
    "latest": "0.0.99",
    "min_supported": "0.0.1",
    "update_url": "https://raw.githubusercontent.com/giodc/wharftales/main/scripts/upgrade.sh",
    "changelog_url": "https://github.com/giodc/wharftales/releases",
    "release_notes": "Test update notification",
    "released_at": "2025-10-30"
  }
}
EOF

# Check for updates via command line
php /opt/wharftales/scripts/check-updates-cron.php

# Or check via web UI
# Go to Settings → System Updates → Click "Check Now"
```

### Test Update Process (Dry Run)

```bash
# Don't actually run this unless you want to test the full update
# It will pull from git and restart containers
/opt/wharftales/scripts/upgrade.sh
```

## Troubleshooting

### "Failed to fetch versions.json"

**Error:** `Update check failed: Failed to fetch versions.json`

**Cause:** The file doesn't exist at the specified URL

**Solutions:**
1. Check if file exists on GitHub:
   ```bash
   curl -I https://raw.githubusercontent.com/giodc/wharftales/main/versions.json
   ```

2. If 404, commit and push the file:
   ```bash
   git add versions.json
   git commit -m "Add versions.json"
   git push origin main
   ```

3. Or use local file for testing:
   ```bash
   sqlite3 /opt/wharftales/data/database.sqlite "INSERT OR REPLACE INTO settings (key, value) VALUES ('versions_url', '/opt/wharftales/versions.json');"
   ```

### "versions.json not found (404)"

The file hasn't been pushed to GitHub yet. Either:
- Push it to GitHub (recommended)
- Use local file path in settings

### Update notification not showing

1. Check if update check is enabled in Settings
2. Manually trigger check: Settings → System Updates → "Check Now"
3. Check database:
   ```bash
   sqlite3 /opt/wharftales/data/database.sqlite "SELECT * FROM settings WHERE key LIKE 'update%';"
   ```

## Production Checklist

Before going live with the update system:

- [ ] `versions.json` committed and pushed to GitHub
- [ ] File accessible via curl
- [ ] Update check enabled in settings
- [ ] Test manual update check works
- [ ] Verify notification appears when update available
- [ ] Test update process on staging environment
- [ ] Backup system tested and working
- [ ] Rollback procedure documented and tested

## Security Notes

- Always use HTTPS URLs for versions.json
- Verify SSL certificates (enabled by default)
- Only administrators can trigger updates
- Backups are created automatically before updates
- Update logs stored in `/opt/wharftales/logs/`
