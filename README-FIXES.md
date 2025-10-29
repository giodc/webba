# WharfTales Fixes - Quick Start

## 🚀 Quick Deploy (On Remote Server)

```bash
cd /opt/wharftales
./DEPLOY-FIXES.sh
```

This will apply all fixes automatically.

---

## 🔑 Reset Forgotten Password

```bash
./reset-admin-password.sh admin YourNewPassword123
```

---

## 📋 What's Fixed

### 1. Password Reset Tool
- **Problem**: Forgot admin password, needed command-line reset
- **Solution**: Created `reset-admin-password.sh` script
- **Usage**: `./reset-admin-password.sh admin NewPassword`

### 2. Session Timeout Error
- **Problem**: "Network error: Unexpected token '<'... is not valid JSON"
- **Root Cause**: Session expires after 24 minutes, API returns HTML instead of JSON
- **Solution**: 
  - Extended session to 24 hours
  - Better error handling in JavaScript
  - User-friendly error messages
  - Auto-redirect to login when session expires

---

## 📁 Files Created

### Scripts
- ✅ `DEPLOY-FIXES.sh` - Deploy all fixes (run this first)
- ✅ `reset-admin-password.sh` - Reset password from CLI
- ✅ `fix-and-rebuild.sh` - Fix session errors only

### Documentation
- ✅ `README-FIXES.md` - This file (quick start)
- ✅ `FIXES-APPLIED.md` - Detailed technical changes
- ✅ `PASSWORD-RESET-GUIDE.md` - Password reset documentation
- ✅ `FIX-SESSION-ERROR.md` - Session error troubleshooting
- ✅ `QUICK-PASSWORD-RESET.txt` - Quick reference card

### Configuration
- ✅ `gui/php-session.ini` - PHP session configuration
- ✅ Modified `gui/includes/auth.php` - Extended session lifetime
- ✅ Modified `gui/js/app.js` - Better error handling
- ✅ Modified `gui/Dockerfile` - Include PHP config

---

## 🎯 Deployment Steps

### On Remote Server

```bash
# 1. Navigate to wharftales directory
cd /opt/wharftales

# 2. Deploy all fixes
./DEPLOY-FIXES.sh

# 3. Clear browser cache and cookies

# 4. Log in to WharfTales

# 5. Test deploying an app
```

### Manual Deployment (Alternative)

```bash
cd /opt/wharftales
docker-compose down
docker-compose build --no-cache web-gui
docker-compose up -d
```

---

## ✅ Verification

### Check Session Configuration
```bash
docker exec wharftales_gui php -i | grep session.gc_maxlifetime
# Should show: 86400 (24 hours)
```

### Check Container Status
```bash
docker ps | grep wharftales_gui
# Should show: wharftales_gui running
```

### Test Password Reset
```bash
./reset-admin-password.sh admin TestPassword123
# Should show: Password reset successfully!
```

---

## 🐛 Troubleshooting

### Still Getting JSON Error?
1. Clear browser cache completely
2. Log out and log in again
3. Check browser console (F12) for errors
4. Verify session config: `docker exec wharftales_gui php -i | grep session`

### Password Reset Not Working?
1. Check container is running: `docker ps`
2. Check logs: `docker logs wharftales_gui`
3. Try direct command: `docker exec wharftales_gui php /var/www/html/reset-password.php admin NewPass`

### Container Won't Start?
```bash
# View logs
docker logs wharftales_gui --tail 50

# Rebuild from scratch
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

---

## 📊 Before vs After

| Feature | Before | After |
|---------|--------|-------|
| Session Lifetime | 24 minutes | 24 hours |
| Error Messages | "Unexpected token '<'" | "Session expired. Redirecting..." |
| Password Reset | Manual DB edit | `./reset-admin-password.sh` |
| Auto-redirect | No | Yes (on session expire) |
| Error Logging | Basic | Enhanced |

---

## 🔗 Quick Links

- **Password Reset**: See `PASSWORD-RESET-GUIDE.md`
- **Session Errors**: See `FIX-SESSION-ERROR.md`
- **Technical Details**: See `FIXES-APPLIED.md`

---

## 📞 Support

If you encounter issues:

1. **Check logs**: `docker logs wharftales_gui --tail 50`
2. **Check browser console**: F12 → Console tab
3. **Verify deployment**: `./DEPLOY-FIXES.sh` (run again)
4. **Read documentation**: See files listed above

---

## 🎉 Summary

**All fixes are ready to deploy!**

Run this command on your remote server:
```bash
cd /opt/wharftales && ./DEPLOY-FIXES.sh
```

Then clear your browser cache and log in again. You're done! 🚀
