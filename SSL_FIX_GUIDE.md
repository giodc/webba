# SSL Fix Guide - WharfTales

## Problem Summary

Your SSL certificates are not working because of **one critical issue**:

### ❌ Invalid Let's Encrypt Email

**Current email:** `testdd@example.com`

Let's Encrypt **automatically rejects** all certificate requests from domains like:
- `@example.com`
- `@example.net`
- `@example.org`
- `@test.*`

This is why your `acme.json` file is empty and certificates show as "Pending".

---

## Quick Fix (3 Steps)

### Step 1: Update Let's Encrypt Email

1. Open your dashboard in a web browser
2. Go to **Settings → SSL Configuration**
3. Change the email to a **real email address** (e.g., `admin@yourdomain.com`)
4. Click **Save SSL Settings**

### Step 2: Restart Traefik

Run this command on your server:

```bash
cd /opt/wharftales && docker-compose restart traefik
```

Or use the "Restart Traefik" button in the Settings page.

### Step 3: Verify SSL Status

1. Go to **SSL Debug** page in your dashboard
2. Check that:
   - ✅ Let's Encrypt Email shows "Valid"
   - ✅ Ports 80 and 443 are open
   - ✅ Your domain DNS points to this server

---

## Diagnostic Tools

### Run SSL Diagnostic Script

```bash
/opt/wharftales/scripts/ssl-diagnostic.sh
```

This script will check:
- Traefik container status
- Let's Encrypt email validity
- acme.json file status
- Port availability
- Sites with SSL enabled
- Recent Traefik errors

### View SSL Debug Page

Access: `http://your-server-ip:port/ssl-debug.php`

This page shows:
- SSL configuration status
- Sites with SSL enabled
- Certificate status (Issued/Pending)
- Recent SSL errors from Traefik logs
- Troubleshooting guide

---

## Understanding Certificate Issuance

After fixing the email, certificates will be issued **automatically** when:

1. ✅ The Let's Encrypt email is valid (not @example.com or @test.*)
2. ✅ The domain DNS points to your server's public IP
3. ✅ Ports 80 and 443 are accessible from the internet
4. ✅ The site container is running with SSL enabled
5. ✅ Traefik is running and configured correctly

**Note:** Certificate issuance can take 1-5 minutes after all conditions are met.

---

## Checking DNS Configuration

Verify your domain points to the server:

```bash
nslookup yourdomain.com
```

Or:

```bash
dig yourdomain.com +short
```

The output should show your server's public IP address.

---

## Checking Firewall/Ports

### Check if ports are listening:

```bash
sudo netstat -tuln | grep -E ':80|:443'
```

Or:

```bash
sudo ss -tuln | grep -E ':80|:443'
```

### Open ports if needed:

```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

---

## Monitoring Certificate Issuance

### Watch Traefik logs in real-time:

```bash
docker logs wharftales_traefik -f
```

Look for messages like:
- `Obtaining certificate for domain.com`
- `Certificate obtained successfully`

### Check acme.json for certificates:

```bash
sudo cat /opt/wharftales/ssl/acme.json | grep -i certificates
```

If you see `"Certificates": null`, no certificates have been issued yet.

---

## Common Issues After Fix

### Issue: "Certificate still shows Pending"

**Solution:** The database tracks certificate status separately. After certificates are issued:

1. Go to **SSL Debug** page
2. Click **"Mark Issued"** button next to each site
3. Or run the sync script: `/opt/wharftales/gui/sync-ssl-status.php`

### Issue: "Domain not accessible via HTTPS"

**Checklist:**
- ✅ Wait 1-5 minutes after Traefik restart
- ✅ Clear browser cache (Ctrl+Shift+R)
- ✅ Check Traefik logs for errors
- ✅ Verify container is running: `docker ps | grep your-container`

### Issue: "Rate limit exceeded"

Let's Encrypt has rate limits:
- 50 certificates per domain per week
- 5 failed validations per hour

**Solution:** Wait 1 hour before retrying, or use staging environment for testing.

---

## Files Modified

The following files were enhanced to help diagnose and fix SSL issues:

1. **`/opt/wharftales/scripts/ssl-diagnostic.sh`** (NEW)
   - Comprehensive SSL diagnostic script
   - Checks all SSL components
   - Provides actionable recommendations

2. **`/opt/wharftales/gui/ssl-debug.php`** (ENHANCED)
   - Added prominent warning for invalid email
   - Shows if acme.json is empty
   - Better status indicators
   - Clearer error messages

3. **`/opt/wharftales/gui/settings.php`** (EXISTING)
   - Already has email validation
   - Prevents saving invalid emails
   - Automatically resets acme.json on email change

---

## Support

If you continue to have issues after following this guide:

1. Run the diagnostic script and save the output
2. Check Traefik logs for specific errors
3. Verify all prerequisites are met (DNS, firewall, email)
4. Check the SSL Debug page for detailed status

---

## Technical Details

### Why acme.json is empty:

When you change the Let's Encrypt email or when the email is invalid, the `acme.json` file is reset to an empty template:

```json
{
  "letsencrypt": {
    "Account": {
      "Email": "",
      "Registration": null,
      "PrivateKey": null,
      "KeyType": ""
    },
    "Certificates": null
  }
}
```

This is **normal** and expected. Traefik will populate it with certificates once:
1. The email is valid
2. A site with SSL is deployed
3. The domain is accessible from the internet

### Database vs acme.json:

The system uses **database-based certificate tracking** to avoid permission issues:
- `ssl_cert_issued` column tracks if certificate was issued
- `ssl_cert_issued_at` column tracks when it was issued
- Manual "Mark Issued/Removed" buttons for control
- Sync script available to sync from acme.json

This approach solves the problem where the dashboard couldn't read acme.json due to file permissions (owned by root with 600 permissions).
