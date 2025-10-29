# Fresh Install Guide - All Fixes Included ✅

## For New Installations

All fixes are now included in the repository! Fresh installs will automatically have:

✅ **Session fixes** - 24-hour session lifetime  
✅ **Error handling** - Proper JSON responses with session detection  
✅ **Laravel template** - No parse errors  
✅ **PHP template** - No parse errors  
✅ **Password reset tool** - Command-line password reset  
✅ **Documentation** - Complete guides and troubleshooting  

---

## Installation Steps

### 1. Clone Repository
```bash
git clone https://github.com/giodc/wharftales.git wharftales
cd wharftales
```

### 2. Configure Environment
```bash
cp .env.example .env
nano .env  # Edit your settings
```

### 3. Deploy
```bash
docker-compose up -d
```

### 4. Access
Open browser: `http://your-server-ip:9000`

---

## What's Included (Latest Version)

### Session Management
- **24-hour session lifetime** (not 24 minutes)
- **Automatic session detection** in all API calls
- **User-friendly error messages** ("Session expired" instead of "Unexpected token")
- **Auto-redirect to login** when session expires

### Templates Fixed
- **PHP template** - No parse errors in heredoc
- **Laravel template** - No parse errors in heredoc
- **Proper escaping** - `\$_SERVER[\"...\"]` syntax works correctly

### Tools Included
- `reset-admin-password.sh` - Reset password from command line
- `fix-and-rebuild.sh` - Rebuild with session fixes
- `DEPLOY-FIXES.sh` - Comprehensive deployment script
- `fix-laravel-index.sh` - Fix existing Laravel containers

### Documentation
- `ALL-FIXES-COMPLETE.md` - Complete technical documentation
- `README-FIXES.md` - Quick start guide
- `PASSWORD-RESET-GUIDE.md` - Password reset instructions
- `FIX-SESSION-ERROR.md` - Session troubleshooting
- `TESTING-SUMMARY.md` - Verification steps

---

## Verification After Install

### 1. Check Session Configuration
```bash
docker exec wharftales_gui php -r "require_once '/var/www/html/includes/auth.php'; echo 'Session: ' . ini_get('session.gc_maxlifetime') . ' seconds';"
```
**Expected:** `Session: 86400 seconds` ✅

### 2. Check API Syntax
```bash
docker exec wharftales_gui php -l /var/www/html/api.php
```
**Expected:** `No syntax errors detected` ✅

### 3. Check JavaScript Version
```bash
docker exec wharftales_gui grep "console.log" /var/www/html/js/app.js | head -1
```
**Expected:** `WharfTales JS v5.2 loaded` ✅

### 4. Test API Response
```bash
curl -s http://localhost:9000/api.php?action=create_site -X POST -H "Content-Type: application/json" -d '{"name":"test"}'
```
**Expected:** `{"success":false,"error":"Unauthorized"}` (JSON, not HTML) ✅

---

## First Steps After Install

### 1. Log In
- Default username: `admin`
- Default password: Check your `.env` file or installation output

### 2. Change Password
- Click your username → Change Password
- Or use CLI: `./reset-admin-password.sh admin YourNewPassword`

### 3. Create Your First Site
- Click "Deploy New Application"
- Choose PHP or Laravel
- Fill in the details
- Click "Deploy"

**Expected:** ✅ Site deploys successfully without errors!

---

## Git Commits Included

### Commit ea18721 (Session Fixes)
```
fix: improve session security and add session expiration detection in API calls

- Extended session lifetime to 24 hours
- Added session expiration detection in JavaScript
- Enhanced error handling for all API calls
- Added PHP session configuration
- Updated Dockerfile to include PHP config
- Created helper scripts and documentation
```

### Commit 5529e71 (Template Fixes)
```
Fix: Laravel and PHP template heredoc escaping

- Fixed parse error 'unexpected token \' on line 43 in Laravel sites
- Fixed heredoc escaping in both PHP and Laravel templates
- Changed from single quotes to escaped double quotes in heredoc
- Proper escaping: \$_SERVER[\"...\"] produces valid PHP code
- Fixes 'Network error: Unexpected token <' during site creation
```

---

## Upgrading Existing Installation

If you already have WharfTales installed:

```bash
cd /opt/wharftales

# Pull latest changes
git pull origin master

# Rebuild containers
docker-compose down
docker-compose build --no-cache web-gui
docker-compose up -d

# Clear browser cache
# Then log in and test
```

---

## Common Issues (Should Not Occur in Fresh Install)

### ❌ "Unexpected token '<'" Error
**Should not happen** - Fixed in latest version  
**If it happens:** Clear browser cache and hard refresh

### ❌ Laravel Parse Error Line 43
**Should not happen** - Fixed in latest version  
**If it happens:** Pull latest code and rebuild

### ❌ Session Expires Too Quickly
**Should not happen** - Session is 24 hours  
**If it happens:** Check session config with verification commands above

---

## Support

### Documentation
- See `ALL-FIXES-COMPLETE.md` for complete technical details
- See `README-FIXES.md` for quick reference
- See `PASSWORD-RESET-GUIDE.md` for password issues

### Verification Commands
```bash
# Check all fixes are applied
cd /opt/wharftales
./DEPLOY-FIXES.sh  # Runs verification checks
```

### Logs
```bash
# View container logs
docker logs wharftales_gui --tail 50 -f

# Check for errors
docker logs wharftales_gui 2>&1 | grep -i error
```

---

## Repository

**GitHub:** https://github.com/giodc/wharftales.git  
**Latest Commit:** 5529e71 (Laravel and PHP template heredoc escaping)  
**Status:** ✅ All fixes included

---

## Summary

✅ **Fresh installs work out of the box**  
✅ **No manual fixes required**  
✅ **All templates work correctly**  
✅ **Session management optimized**  
✅ **Complete documentation included**  

**Just clone, configure, and deploy!** 🚀

---

**Last Updated:** 2025-10-10  
**Version:** WharfTales with all fixes (v5.2)  
**Ready for Production:** YES ✅
