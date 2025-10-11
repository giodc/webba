# CRITICAL: Fix Running Traefik Container on Remote

## Problem Identified

Your remote server shows:
```
Email in docker-compose.yml: [correct email]
Email in RUNNING Traefik container: test@example.com
```

**Root Cause:** The Traefik container was created with the old email and is still using it, even though docker-compose.yml has been updated. **Restarting is not enough** - the container must be **recreated**.

## Why This Happens

1. docker-compose.yml updated ✅
2. But running container has old configuration cached ❌
3. `docker restart` doesn't reload docker-compose.yml ❌
4. Container must be **removed and recreated** ✅

## IMMEDIATE FIX FOR REMOTE SERVER

Run this on your remote server:

```bash
cd /opt/webbadeploy

# Method 1: Use the fix script (RECOMMENDED)
bash fix-remote-email.sh

# Method 2: Manual commands
docker-compose stop traefik
docker-compose rm -f traefik
sudo rm ssl/acme.json
sudo bash fix-acme.sh
docker-compose up -d traefik
docker logs webbadeploy_traefik -f
```

## What The Fix Does

1. **Stops Traefik** - Gracefully stops the container
2. **Removes container** - Deletes the old container completely
3. **Clears acme.json** - Removes cached email/certificates
4. **Creates fresh acme.json** - Empty template with correct permissions
5. **Recreates Traefik** - New container reads updated docker-compose.yml
6. **Verifies** - New container now has correct email

## Verification After Fix

```bash
# Check email in running container
docker inspect webbadeploy_traefik | grep "acme.email"

# Should show your correct email, NOT test@example.com

# Monitor certificate acquisition
docker logs webbadeploy_traefik -f

# Should see certificate requests with correct email
# NO MORE "forbidden domain" errors
```

## GUI Changes Made (Permanent Fix)

### 1. Restart Traefik Button Now Recreates Container

**File:** `gui/api.php`

**Before:**
```php
exec("docker restart webbadeploy_traefik");
```

**After:**
```php
exec("docker-compose stop traefik");
exec("docker-compose rm -f traefik");
exec("docker-compose up -d traefik");
```

**Result:** Clicking "Restart Traefik" in GUI now properly recreates the container with new configuration!

### 2. SSL Certificate Status on Dashboard

**File:** `gui/index.php` + `gui/includes/functions.php`

**New Feature:** Dashboard now shows actual certificate status:

- 🟢 **SSL: Active** - Certificate issued and working
- 🟡 **SSL: Pending** - Waiting for certificate (check logs)
- 🟡 **SSL: Not Configured** - Need to recreate site
- ⚫ **SSL: Disabled** - SSL not enabled

**Visual Indicators:**
- ✅ Green shield = Certificate exists
- ⚠️ Yellow shield = Certificate pending
- No shield = No SSL

### 3. Automatic acme.json Reset

**File:** `gui/includes/functions.php`

When email changes in GUI:
- ✅ Backs up old acme.json
- ✅ Creates fresh acme.json
- ✅ Sets permissions to 600
- ✅ Updates docker-compose.yml

## For All Your Sites

After fixing Traefik with correct email:

### Existing Sites with SSL Enabled

If they show "SSL: Pending":

1. **Wait 1-2 minutes** - Traefik requests certificates automatically
2. **Check logs:** `docker logs webbadeploy_traefik -f`
3. **Look for:** "certificate obtained" messages
4. **Refresh dashboard** - Should change to "SSL: Active"

### New Sites

When creating new sites with SSL:
- ✅ Will use correct email automatically
- ✅ Certificate requested immediately
- ✅ Status shows "Pending" then "Active"
- ✅ No more forbidden domain errors

## Commands Reference

### Check Current Status
```bash
cd /opt/webbadeploy

# Check all email configurations
bash check-remote-email.sh

# Check running container email
docker inspect webbadeploy_traefik | grep "acme.email"

# Check certificates
sudo cat ssl/acme.json | jq '.letsencrypt.Certificates'
```

### Fix Commands
```bash
# Full automated fix
bash fix-remote-email.sh

# Or manual step-by-step
docker-compose stop traefik
docker-compose rm -f traefik
sudo rm ssl/acme.json
sudo bash fix-acme.sh
docker-compose up -d traefik
```

### Monitor
```bash
# Watch Traefik logs
docker logs webbadeploy_traefik -f

# Check for errors
docker logs webbadeploy_traefik 2>&1 | grep -i "error\|forbidden"

# Check for success
docker logs webbadeploy_traefik 2>&1 | grep -i "certificate obtained"
```

## Expected Timeline

After running the fix:

**Immediate (0-30 seconds):**
- ✅ Old container removed
- ✅ New container created
- ✅ Traefik starts with correct email

**Within 1-2 minutes:**
- ✅ Traefik discovers SSL-enabled sites
- ✅ Initiates certificate requests
- ✅ HTTP-01 challenges completed

**Within 5 minutes:**
- ✅ Certificates issued
- ✅ acme.json populated
- ✅ HTTPS works
- ✅ Dashboard shows "SSL: Active"

## Troubleshooting

### Still seeing test@example.com in container

**Problem:** Container not recreated properly

**Solution:**
```bash
docker-compose down
docker-compose up -d
docker inspect webbadeploy_traefik | grep "acme.email"
```

### Certificates not being issued

**Check:**
1. Domain DNS points to server
2. Ports 80 and 443 accessible
3. No rate limiting from Let's Encrypt
4. Traefik logs for specific errors

**Debug:**
```bash
# Check domain accessibility
curl -I http://your-domain.com

# Check DNS
nslookup your-domain.com

# Check firewall
sudo ufw status

# Check Traefik logs
docker logs webbadeploy_traefik 2>&1 | tail -100
```

### Dashboard still shows "SSL: Pending"

**Wait:** Certificates can take 1-5 minutes

**Force refresh:**
1. Hard refresh browser (Ctrl+Shift+R)
2. Check acme.json: `sudo cat ssl/acme.json | jq`
3. If certificates exist, it's just a display delay

## Summary

**Immediate Action Required:**
```bash
ssh user@remote-server
cd /opt/webbadeploy
bash fix-remote-email.sh
```

**After Fix:**
- ✅ Traefik uses correct email
- ✅ New sites get certificates automatically
- ✅ Dashboard shows real certificate status
- ✅ GUI restart button works properly
- ✅ No more manual intervention needed

**One-Time Fix:** Yes, after this fix, everything works automatically!

---

**Status:** Ready to deploy
**Action:** Run fix-remote-email.sh on remote server
**Time:** 2-5 minutes total
