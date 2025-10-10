# Fixes Applied to Webbadeploy

## Issue 1: Forgotten Admin Password ✅

### Problem
Admin password was forgotten and needed to be reset from command line on remote server.

### Solution
Created comprehensive password reset tools:

#### Files Created:
1. **`reset-admin-password.sh`** - Smart shell script for password reset
2. **`PASSWORD-RESET-GUIDE.md`** - Complete documentation
3. **`QUICK-PASSWORD-RESET.txt`** - Quick reference
4. **Fixed `gui/reset-password.php`** - Corrected database connection

#### Usage:
```bash
# Quick reset
./reset-admin-password.sh admin YourNewPassword123

# Or direct Docker command
docker exec webbadeploy_gui php /var/www/html/reset-password.php admin YourNewPassword123
```

---

## Issue 2: "Network error: Unexpected token '<'... is not valid JSON" ✅

### Problem
When deploying new apps, getting JSON parse error. This happens when:
- Session expires (default 24 minutes)
- API returns HTML login page instead of JSON
- Browser tries to parse HTML as JSON → error

### Root Cause
- PHP default session lifetime too short (1440 seconds = 24 minutes)
- No proper error handling for expired sessions
- Confusing error messages

### Solution Applied

#### 1. Extended Session Lifetime (24 hours)
**File: `gui/includes/auth.php`**
- Changed session lifetime from 24 minutes to 24 hours
- Added session security settings
- Better session persistence

#### 2. Enhanced Error Handling
**File: `gui/js/app.js`**
- Added `apiCall()` helper function
- Detects HTML responses (login page)
- Shows user-friendly "Session expired" message
- Auto-redirects to login page

#### 3. PHP Configuration
**File: `gui/php-session.ini`** (NEW)
- System-wide session configuration
- Increased memory limits (256M)
- Better upload limits (100M)
- Proper error logging

#### 4. Updated Docker Build
**File: `gui/Dockerfile`**
- Includes PHP configuration in container
- Ensures settings persist across rebuilds

### How to Apply

```bash
cd /opt/webbadeploy

# Option 1: Use the automated script
./fix-and-rebuild.sh

# Option 2: Manual rebuild
docker-compose down
docker-compose build --no-cache web-gui
docker-compose up -d
```

### After Applying
1. Clear browser cache and cookies
2. Log in again
3. Session will now last 24 hours
4. Better error messages when session expires

---

## Files Modified

### Authentication & Sessions
- ✅ `gui/includes/auth.php` - Extended session lifetime
- ✅ `gui/js/app.js` - Better error handling
- ✅ `gui/Dockerfile` - Added PHP config

### Password Reset
- ✅ `gui/reset-password.php` - Fixed database connection

### New Files Created
- ✅ `reset-admin-password.sh` - Password reset script
- ✅ `PASSWORD-RESET-GUIDE.md` - Password reset documentation
- ✅ `QUICK-PASSWORD-RESET.txt` - Quick reference
- ✅ `gui/php-session.ini` - PHP configuration
- ✅ `fix-and-rebuild.sh` - Automated fix script
- ✅ `FIX-SESSION-ERROR.md` - Session error documentation
- ✅ `FIXES-APPLIED.md` - This file

---

## Testing Checklist

### Password Reset
- [ ] Run `./reset-admin-password.sh admin TestPassword123`
- [ ] Verify password reset success message
- [ ] Log in with new password
- [ ] Confirm login works

### Session Fix
- [ ] Run `./fix-and-rebuild.sh`
- [ ] Clear browser cache
- [ ] Log in to Webbadeploy
- [ ] Try deploying a new app
- [ ] Verify no JSON parse errors
- [ ] Wait 30+ minutes and verify session persists

### Verification Commands
```bash
# Check container is running
docker ps | grep webbadeploy_gui

# Verify session configuration
docker exec webbadeploy_gui php -i | grep session.gc_maxlifetime
# Should show: 86400

# Check PHP config file exists
docker exec webbadeploy_gui cat /usr/local/etc/php/conf.d/php-session.ini

# View logs
docker logs webbadeploy_gui --tail 50
```

---

## Rollback (If Needed)

If something goes wrong:

```bash
cd /opt/webbadeploy

# Restore from git (if tracked)
git checkout gui/includes/auth.php
git checkout gui/js/app.js
git checkout gui/Dockerfile

# Remove new files
rm gui/php-session.ini

# Rebuild
docker-compose down
docker-compose build --no-cache web-gui
docker-compose up -d
```

---

## Support

For issues:
1. Check `FIX-SESSION-ERROR.md` for troubleshooting
2. Check `PASSWORD-RESET-GUIDE.md` for password issues
3. View Docker logs: `docker logs webbadeploy_gui`
4. Check browser console (F12) for JavaScript errors

---

**Status**: ✅ All fixes applied and ready to deploy
**Date**: 2025-10-10
**Version**: Webbadeploy v5.1 (Enhanced Error Handling)
