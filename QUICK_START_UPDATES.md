# Quick Start: Using the Update System

## For End Users (Dashboard)

### Check for Updates
1. Open WharfTales dashboard
2. Look for **"Update Available"** notification in the navbar (appears automatically)
3. Click the notification to see update details

### Install Updates
1. Click **"Update Available"** in navbar
2. Review the version and changelog
3. Click **"Install Update"** button
4. Wait for the update to complete (page will reload automatically)

## For Developers (Releasing Updates)

### Step 1: Make Your Changes
```bash
cd /opt/wharftales
# Make your code changes
git add .
git commit -m "Your changes description"
```

### Step 2: Update Version Number
```bash
# Edit VERSION file
echo "1.0.1" > VERSION
git add VERSION
git commit -m "Bump version to 1.0.1"
```

### Step 3: Push to Repository
```bash
git push origin main
```

### Step 4: Users Get Notified
- Users will see "Update Available" notification within 1 hour
- They can click to install the update instantly

## Example Workflow

### Scenario: You fixed a bug and want to release it

```bash
# 1. Fix the bug
cd /opt/wharftales
vim gui/api.php  # Make your fix

# 2. Commit the fix
git add gui/api.php
git commit -m "Fix: Resolved issue with site deletion"

# 3. Bump version
echo "1.0.1" > VERSION
git add VERSION
git commit -m "Release v1.0.1 - Bug fixes"

# 4. Push
git push origin main

# Done! Users will be notified automatically
```

## Testing Updates Locally

```bash
# Simulate a new version
echo "1.0.1" > VERSION
git add VERSION
git commit -m "Test version bump"

# Check if update is detected
# Open dashboard and click "Update Available"
```

## Rollback if Needed

If an update causes issues, restore from backup:

```bash
# List backups
docker exec wharftales_gui ls -lh /app/data/backups/

# Extract a backup (example)
docker exec wharftales_gui tar -xzf /app/data/backups/backup_v1.0.0_2025-10-02_19-30-00.tar.gz -C /tmp/
```

## Configuration

Edit `gui/includes/update-config.php` to customize:
- Update check interval
- Auto-update behavior
- Git branch to track
- Backup retention

## That's It!

The update system is fully automated. Just:
1. **Developers**: Push code + update VERSION file
2. **Users**: Click "Install Update" when notified

No manual server access needed! 🚀
