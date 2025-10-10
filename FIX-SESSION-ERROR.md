# Fix: "Network error: Unexpected token '<'... is not valid JSON"

## Problem
This error occurs when the API returns HTML (usually the login page) instead of JSON. This happens when your session expires.

## Root Cause
- **Session timeout**: PHP's default session lifetime is too short (1440 seconds = 24 minutes)
- **Session not persisting**: Sessions may not be saved properly between requests
- **Authentication expired**: You need to log in again

## Quick Fix (Immediate)

### Step 1: Log in again
Simply refresh the page and log in again. Your session has expired.

### Step 2: Check if you're logged in
Open browser console (F12) and check for any authentication errors.

## Permanent Fix (Recommended)

I've updated the code to:
1. **Increase session lifetime to 24 hours** (from 24 minutes)
2. **Better error handling** - Now shows "Session expired" message instead of cryptic JSON error
3. **Auto-redirect to login** when session expires

### Apply the Fix

```bash
cd /opt/webbadeploy

# Rebuild the container with new configuration
docker-compose down
docker-compose build --no-cache web-gui
docker-compose up -d

# Check if it's running
docker ps | grep webbadeploy_gui
```

### Verify the Fix

```bash
# Check PHP session configuration
docker exec webbadeploy_gui php -i | grep session

# Should show:
# session.gc_maxlifetime => 86400
# session.cookie_lifetime => 86400
```

## What Changed

### 1. Enhanced JavaScript Error Handling (`/opt/webbadeploy/gui/js/app.js`)
- Added `apiCall()` helper function that detects HTML responses
- Shows user-friendly "Session expired" message
- Auto-redirects to login page

### 2. Extended Session Lifetime (`/opt/webbadeploy/gui/includes/auth.php`)
- Session lifetime: 24 hours (was ~24 minutes)
- Better session security settings

### 3. PHP Configuration (`/opt/webbadeploy/gui/php-session.ini`)
- System-wide session configuration
- Increased memory limits
- Better error logging

### 4. Dockerfile Updated
- Includes PHP configuration in container build

## Troubleshooting

### Still getting the error after rebuild?

1. **Clear browser cache and cookies**
   ```
   - Chrome: Ctrl+Shift+Delete
   - Firefox: Ctrl+Shift+Delete
   - Clear cookies for your Webbadeploy domain
   ```

2. **Check if container rebuilt properly**
   ```bash
   docker exec webbadeploy_gui cat /usr/local/etc/php/conf.d/php-session.ini
   ```
   
   Should show the session configuration.

3. **Check logs**
   ```bash
   docker logs webbadeploy_gui --tail 50
   ```

4. **Force complete rebuild**
   ```bash
   docker-compose down -v
   docker system prune -f
   docker-compose build --no-cache
   docker-compose up -d
   ```

### Session still expiring quickly?

Check if your browser is blocking cookies:
- Allow cookies for your Webbadeploy domain
- Disable "Clear cookies on exit" for this site
- Check browser privacy settings

### Getting different errors?

Check the browser console (F12 → Console tab) for detailed error messages.

## Prevention

1. **Keep browser tab active**: Some browsers pause inactive tabs
2. **Don't use incognito/private mode**: Sessions may not persist
3. **Check browser extensions**: Some privacy extensions block session cookies

## Technical Details

### Before Fix
- Session lifetime: 1440 seconds (24 minutes)
- No session persistence configuration
- Generic error messages
- No auto-redirect on auth failure

### After Fix
- Session lifetime: 86400 seconds (24 hours)
- Proper session configuration
- User-friendly error messages
- Auto-redirect to login when session expires

## Need More Help?

If you're still experiencing issues:
1. Check browser console for errors
2. Check Docker logs: `docker logs webbadeploy_gui`
3. Verify you're logged in: Visit `/login.php`
4. Try a different browser to rule out browser-specific issues
