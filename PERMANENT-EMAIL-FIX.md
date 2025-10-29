# Permanent Fix for ACME Email Issue

## What Was Fixed

The system now **automatically handles Let's Encrypt email changes** properly, preventing the "forbidden domain" error.

## Changes Made

### 1. Enhanced `updateComposeParameter()` Function
**File:** `/opt/wharftales/gui/includes/functions.php`

**What it does now:**
- ✅ Validates email doesn't contain forbidden domains (example.com, example.net, example.org, test.com)
- ✅ Updates docker-compose.yml in database
- ✅ Writes updated docker-compose.yml to file system
- ✅ **Automatically backs up old acme.json**
- ✅ **Creates fresh acme.json with empty template**
- ✅ Sets correct permissions (600) on acme.json

**Code added:**
```php
// Validate email doesn't contain forbidden domains
if (preg_match('/@(example\.(com|net|org)|test\.com)$/i', $paramValue)) {
    throw new Exception("Email domain is forbidden by Let's Encrypt. Please use a real email address.");
}

// Clear acme.json when email changes (it caches the old email)
$acmeFile = '/app/ssl/acme.json';
if (file_exists($acmeFile)) {
    // Backup old acme.json
    $backupFile = $acmeFile . '.backup.' . date('YmdHis');
    @copy($acmeFile, $backupFile);
    
    // Create fresh acme.json with empty template
    $freshAcme = json_encode([...], JSON_PRETTY_PRINT);
    file_put_contents($acmeFile, $freshAcme);
    chmod($acmeFile, 0600);
}
```

### 2. Enhanced Settings Page
**File:** `/opt/wharftales/gui/settings.php`

**What it does now:**
- ✅ Pre-validates email before attempting update
- ✅ Shows clear error message if forbidden domain detected
- ✅ Informs user that acme.json has been reset
- ✅ Prompts user to restart Traefik

**Improved error message:**
```
"Email domain is forbidden by Let's Encrypt (example.com, example.net, example.org, test.com). 
Please use a real email address."
```

**Improved success message:**
```
"Let's Encrypt email updated successfully! The acme.json file has been reset. 
You must restart Traefik now."
```

### 3. Installation Scripts Updated
**Files:** `install.sh`, `install-production.sh`

**What they do now:**
- ✅ Create acme.json during installation
- ✅ Set correct permissions (600)
- ✅ Set correct ownership (root:root)
- ✅ Never use example.com as default

## How It Works Now

### User Changes Email in GUI

1. **User enters new email** in Settings → SSL Configuration
2. **System validates** email doesn't contain forbidden domains
3. **If valid:**
   - Updates database
   - Updates docker-compose.yml file
   - Backs up old acme.json → `acme.json.backup.20251011183000`
   - Creates fresh acme.json with empty template
   - Sets permissions to 600
   - Shows success message with restart button

4. **User clicks "Restart Traefik"**
   - Traefik restarts with new email
   - Reads fresh acme.json
   - Requests new certificates with correct email
   - ✅ No more "forbidden domain" errors!

### Automatic Protections

#### Forbidden Domain Detection
```php
if (preg_match('/@(example\.(com|net|org)|test\.com)$/i', $email)) {
    throw new Exception("Email domain is forbidden...");
}
```

Blocks:
- `test@example.com` ❌
- `admin@example.net` ❌
- `user@example.org` ❌
- `test@test.com` ❌

Allows:
- `admin@yourdomain.com` ✅
- `ssl@company.net` ✅
- `webmaster@mysite.org` ✅

#### Automatic acme.json Reset

When email changes:
1. Old acme.json backed up automatically
2. Fresh template created
3. Permissions set to 600
4. Ready for Traefik to use with new email

## Benefits

### Before This Fix
- ❌ GUI updated database only
- ❌ docker-compose.yml not updated
- ❌ acme.json kept old email cached
- ❌ Traefik used wrong email
- ❌ Let's Encrypt rejected requests
- ❌ Manual intervention required

### After This Fix
- ✅ GUI updates database AND docker-compose.yml
- ✅ acme.json automatically reset
- ✅ Old acme.json backed up
- ✅ Forbidden domains blocked
- ✅ Clear user instructions
- ✅ One-click Traefik restart
- ✅ Works automatically

## For Existing Servers

### One-Time Fix Required

For servers already running with wrong email:

**Option 1: Use GUI (Recommended)**
1. Log into WharfTales GUI
2. Go to Settings → SSL Configuration
3. Enter correct email (not example.com)
4. Click "Save SSL Settings"
5. Click "Restart Traefik" button
6. ✅ Done! System will handle everything automatically

**Option 2: Use Script**
```bash
cd /opt/wharftales
bash fix-remote-email.sh
```

### After One-Time Fix

All future email changes will work automatically through the GUI!

## For New Installations

New installations automatically:
- ✅ Create acme.json with correct structure
- ✅ Set proper permissions
- ✅ Never use example.com
- ✅ Prompt for real email during setup

## Testing the Fix

### Test Forbidden Domain Blocking

1. Go to Settings → SSL Configuration
2. Try to enter: `test@example.com`
3. Click Save
4. **Expected:** Error message: "Email domain is forbidden by Let's Encrypt..."
5. ✅ System prevents invalid email

### Test Valid Email Update

1. Enter valid email: `admin@yourdomain.com`
2. Click Save
3. **Expected:** 
   - Success message
   - "Restart Traefik" button appears
   - acme.json backed up
   - Fresh acme.json created
4. Click "Restart Traefik"
5. Monitor logs: `docker logs wharftales_traefik -f`
6. **Expected:** No "forbidden domain" errors
7. ✅ Certificates requested with correct email

## File Locations

### Modified Files
- `/opt/wharftales/gui/includes/functions.php` - Core logic
- `/opt/wharftales/gui/settings.php` - UI and validation
- `/opt/wharftales/install.sh` - Standard installation
- `/opt/wharftales/install-production.sh` - Production installation

### Created Files
- `/opt/wharftales/check-remote-email.sh` - Diagnostic tool
- `/opt/wharftales/fix-remote-email.sh` - One-time fix script
- `/opt/wharftales/FIX-EMAIL-REMOTE.md` - Remote fix guide
- `/opt/wharftales/PERMANENT-EMAIL-FIX.md` - This document

### Automatic Backups
When email changes, old acme.json saved to:
- `/opt/wharftales/ssl/acme.json.backup.YYYYMMDDHHMMSS`

## Verification

### Check if Fix is Applied

```bash
# Check if functions.php has the fix
grep -A 5 "forbidden by Let's Encrypt" /opt/wharftales/gui/includes/functions.php

# Should show the validation code
```

### Check Current Email Configuration

```bash
cd /opt/wharftales
bash check-remote-email.sh
```

Shows:
- Email in docker-compose.yml
- Email in database
- Email in running container
- Email in acme.json

## Troubleshooting

### Issue: Email changes but Traefik still uses old email

**Solution:** Restart Traefik
```bash
docker-compose restart traefik
```

### Issue: acme.json not being reset

**Check permissions:**
```bash
ls -la /opt/wharftales/ssl/acme.json
```

**Should be:** `-rw------- 1 root root`

**Fix if needed:**
```bash
sudo chmod 600 /opt/wharftales/ssl/acme.json
sudo chown root:root /opt/wharftales/ssl/acme.json
```

### Issue: Still getting "forbidden domain" error

**Check what email Traefik is actually using:**
```bash
docker inspect wharftales_traefik | grep "acme.email"
```

**If wrong, force recreate:**
```bash
docker-compose up -d --force-recreate traefik
```

## Summary

This permanent fix ensures that:

1. **Email validation** prevents forbidden domains
2. **Automatic sync** keeps docker-compose.yml updated
3. **Automatic reset** clears cached email in acme.json
4. **Automatic backup** preserves old certificates
5. **Clear feedback** guides users through the process
6. **One-click restart** applies changes immediately

**Result:** Users can change Let's Encrypt email through the GUI without manual intervention or SSH access!

---

**Status:** ✅ Permanent fix implemented
**Applies to:** All future installations and email changes
**One-time action needed:** Fix existing servers using GUI or script
