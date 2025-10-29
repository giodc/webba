# Session Error Fix - Complete ✅

## Problem Solved
**"Network error: Unexpected token '<'... is not valid JSON"** 
- Occurred when creating sites
- Occurred when changing password
- Occurred on any API call when session expired

## Root Causes Fixed

### 1. Parse Error in api.php ✅
**Issue**: PHP short tags `<?=` in heredoc strings caused syntax errors
**Fix**: Replaced with `{$siteName}` and `<?php echo ... ?>`
**Impact**: API now returns proper JSON instead of HTML error pages

### 2. Session Expiring Too Quickly ✅
**Issue**: Default 24-minute session timeout
**Fix**: Extended to 24 hours in `auth.php`
**Impact**: Users stay logged in much longer

### 3. Poor Error Handling in JavaScript ✅
**Issue**: Generic "Unexpected token" errors when session expired
**Fix**: Created `apiCall()` helper function that:
- Detects HTML responses (login page)
- Shows user-friendly "Session expired" message
- Auto-redirects to login page
- Applied to ALL API calls

## Files Modified

### Backend (PHP)
- ✅ `gui/api.php` - Fixed parse errors in heredoc strings
- ✅ `gui/includes/auth.php` - Extended session lifetime to 24 hours

### Frontend (JavaScript)
- ✅ `gui/js/app.js` - Updated ALL API calls to use `apiCall()` helper:
  - `createSite()` - Create new applications
  - `editSite()` - Load site data for editing
  - `updateSite()` - Update site settings
  - `deleteSite()` - Delete applications
  - `changePassword()` - Change user password
  - All now have proper session expiry detection

## What Changed in JavaScript

### Before
```javascript
const response = await fetch("api.php?action=change_password", {...});
const result = await response.json(); // ❌ Fails with "Unexpected token '<'"
```

### After
```javascript
const result = await apiCall("api.php?action=change_password", {...});
// ✅ Automatically detects session expiry
// ✅ Shows "Session expired. Redirecting to login..."
// ✅ Auto-redirects to login page
```

## Testing

### Verify the Fix
1. **Clear browser cache** (Ctrl+Shift+Delete)
2. **Refresh the page** (Ctrl+F5)
3. **Check console**: Should see "WharfTales JS v5.2 loaded"
4. **Try these actions**:
   - Create a new site
   - Change your password
   - Edit a site
   - Delete a site

### Expected Behavior
- ✅ No more "Unexpected token '<'" errors
- ✅ If session expires, you see: "Session expired. Redirecting to login..."
- ✅ Automatic redirect to login page
- ✅ Session lasts 24 hours instead of 24 minutes

## Session Lifetime Verification

```bash
# Check session configuration
docker exec wharftales_gui php -r "require_once '/var/www/html/includes/auth.php'; echo 'Session lifetime: ' . ini_get('session.gc_maxlifetime') . ' seconds';"

# Expected output: Session lifetime: 86400 seconds (24 hours)
```

## API Response Verification

```bash
# Test API returns JSON (not HTML)
curl -s http://localhost:9000/api.php?action=create_site -X POST -H "Content-Type: application/json" -d '{"name":"test"}'

# Expected: {"success":false,"error":"Unauthorized"}
# NOT: <html>... (HTML error page)
```

## Browser Console Check

Open browser console (F12) and you should see:
```
WharfTales JS v5.2 loaded - All API calls use session detection!
```

If you see an older version (v5.0, v5.1), **clear your browser cache**.

## Troubleshooting

### Still Getting "Unexpected token" Error?

1. **Hard refresh**: Ctrl+F5 or Cmd+Shift+R
2. **Clear cache**: Browser settings → Clear browsing data
3. **Check console**: Verify JS version is v5.2
4. **Check if logged in**: Visit http://localhost:9000/login.php

### Session Still Expiring?

1. **Check session config**:
   ```bash
   docker exec wharftales_gui php -i | grep session.gc_maxlifetime
   ```
   Should show: `86400`

2. **Check browser cookies**: 
   - Open DevTools → Application → Cookies
   - Find `PHPSESSID` cookie
   - Check `Max-Age` should be `86400`

### Password Change Still Failing?

1. **Verify you're logged in**: Check for `PHPSESSID` cookie
2. **Check current password**: Make sure it's correct
3. **Check browser console**: Look for error messages
4. **Try logging out and back in**

## Summary

✅ **Parse error fixed** - API returns JSON
✅ **Session extended** - 24 hours instead of 24 minutes  
✅ **All API calls updated** - Proper error handling
✅ **User-friendly errors** - No more cryptic messages
✅ **Auto-redirect** - Takes you to login when needed

**Status**: All session-related errors should now be resolved!

---

**Version**: WharfTales JS v5.2
**Date**: 2025-10-10
**Ready**: YES - Test now!
