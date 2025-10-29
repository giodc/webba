# ACME SSL Certificate Fix - Complete Solution

## Overview

This fix ensures the `acme.json` file is properly created and configured for both **new installations** and **existing deployments**.

## What Was Fixed

### 1. Existing Installations (Remote Server Fix)
- ✅ Created `fix-acme.sh` - Automated fix script
- ✅ Created `QUICK-FIX-ACME.sh` - One-liner fix script
- ✅ Updated `DEPLOY-FIXES.sh` - Includes ACME fix in deployment
- ✅ Created comprehensive documentation

### 2. New Installations (Install Scripts)
- ✅ Updated `install.sh` - Creates ACME file during installation
- ✅ Updated `install-production.sh` - Creates ACME file during production setup
- ✅ Added verification checks before starting services
- ✅ Ensures correct permissions (600) and ownership (root:root)

## Files Created/Modified

| File | Purpose | Status |
|------|---------|--------|
| `fix-acme.sh` | Main fix script for existing installations | ✅ Created |
| `QUICK-FIX-ACME.sh` | Quick one-liner fix | ✅ Created |
| `REMOTE-ACME-FIX.md` | Detailed troubleshooting guide | ✅ Created |
| `ACME-FIX-SUMMARY.md` | Summary and deployment options | ✅ Created |
| `DEPLOY-TO-REMOTE.md` | Step-by-step deployment guide | ✅ Created |
| `DEPLOY-FIXES.sh` | Deployment script | ✅ Updated |
| `install.sh` | Standard installation script | ✅ Updated |
| `install-production.sh` | Production installation script | ✅ Updated |
| `ACME-COMPLETE-FIX.md` | This comprehensive guide | ✅ Created |

## For Existing Installations (Your Remote Server)

### Quick Fix (30 seconds)

SSH into your remote server and run:

```bash
cd /opt/wharftales
sudo mkdir -p ssl
sudo tee ssl/acme.json > /dev/null << 'EOF'
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
EOF
sudo chmod 600 ssl/acme.json
sudo chown root:root ssl/acme.json
docker-compose restart traefik
```

### Using Fix Script (Recommended)

From your local machine:

```bash
# Copy the fix script
scp /opt/wharftales/fix-acme.sh user@remote-server:/opt/wharftales/

# SSH and run
ssh user@remote-server
cd /opt/wharftales
sudo bash fix-acme.sh
docker-compose restart traefik
```

## For New Installations

### Fresh Installation

The installation scripts now automatically create the ACME file:

```bash
# Standard installation
sudo bash install.sh

# Production installation
sudo bash install-production.sh
```

Both scripts will:
1. Create `/opt/wharftales/ssl/acme.json`
2. Set permissions to 600 (rw-------)
3. Set ownership to root:root
4. Verify file exists before starting Traefik

### What Gets Created

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

**Initial size:** 169 bytes
**Permissions:** 600 (rw-------)
**Owner:** root:root

## Verification

After applying the fix (existing or new installation):

```bash
# 1. Check file exists
ls -la /opt/wharftales/ssl/acme.json
# Expected: -rw------- 1 root root 169 Oct 11 14:43 acme.json

# 2. Verify content
sudo cat /opt/wharftales/ssl/acme.json | jq

# 3. Check Traefik is running
docker ps | grep traefik

# 4. Monitor certificate acquisition
docker logs wharftales_traefik -f
```

## How It Works

### During Installation
1. **Directory creation:** `mkdir -p ssl`
2. **File creation:** ACME template written to `ssl/acme.json`
3. **Permissions set:** `chmod 600` and `chown root:root`
4. **Verification:** Script checks file exists before starting services
5. **Traefik starts:** Reads acme.json and begins certificate management

### During Runtime
1. **Traefik monitors** configured domains
2. **HTTP-01 challenge** initiated for new domains
3. **Certificate obtained** from Let's Encrypt
4. **acme.json updated** with certificate data (file grows to several KB)
5. **Auto-renewal** happens 30 days before expiration

## Important Notes

### Security
- **Permissions MUST be 600** - Traefik refuses to use world-readable files
- **Owner MUST be root** - Prevents unauthorized access
- **Never commit to git** - Contains private keys after certificates are issued

### Requirements for SSL
- ✅ Valid domain name (not IP address)
- ✅ Domain DNS points to your server
- ✅ Port 80 accessible (HTTP-01 challenge)
- ✅ Port 443 accessible (HTTPS traffic)
- ✅ Valid email in docker-compose.yml

### File Growth
- **Initial:** 169 bytes (empty template)
- **After 1 cert:** ~2-3 KB
- **Multiple certs:** Grows with each domain

## Troubleshooting

### Issue: "ACME file not found"
**Solution:** Run `fix-acme.sh` or use the quick fix above

### Issue: "Permission denied" on acme.json
**Solution:** 
```bash
sudo chmod 600 /opt/wharftales/ssl/acme.json
sudo chown root:root /opt/wharftales/ssl/acme.json
```

### Issue: Certificates not being issued
**Check:**
1. Domain DNS configured correctly
2. Ports 80 and 443 open
3. Valid email in docker-compose.yml
4. Not rate-limited by Let's Encrypt

**Debug:**
```bash
# Check Traefik logs
docker logs wharftales_traefik 2>&1 | grep -i "certificate\|error"

# Test domain accessibility
curl -I http://your-domain.com

# Check DNS
nslookup your-domain.com
```

### Issue: File exists but still getting errors
**Solution:** Restart all services
```bash
cd /opt/wharftales
docker-compose down
docker-compose up -d
docker logs wharftales_traefik -f
```

## Testing the Fix

### Local Testing (Already Done)
```bash
# Verify local fix
ls -la /opt/wharftales/ssl/acme.json
sudo cat /opt/wharftales/ssl/acme.json

# Expected output:
# -rw------- 1 root root 169 Oct 11 14:43 acme.json
# {JSON content}
```

### Remote Testing (After Deployment)
```bash
# SSH to remote
ssh user@remote-server

# Check file
ls -la /opt/wharftales/ssl/acme.json

# Restart Traefik
cd /opt/wharftales
docker-compose restart traefik

# Monitor logs
docker logs wharftales_traefik -f
```

## Production Checklist

Before deploying to production:

- [ ] ACME file exists with correct permissions
- [ ] Valid email configured in docker-compose.yml
- [ ] Domain DNS points to server
- [ ] Firewall allows ports 80 and 443
- [ ] Traefik container running
- [ ] No rate limit issues with Let's Encrypt
- [ ] Backup strategy in place for acme.json

## Deployment Timeline

### Immediate (Local)
- ✅ ACME file created locally
- ✅ Permissions set to 600
- ✅ Installation scripts updated

### Next Step (Remote)
- ⏳ Deploy fix to remote server (choose one method above)
- ⏳ Restart Traefik
- ⏳ Verify certificates are acquired

### Future (New Installations)
- ✅ Automatic ACME file creation
- ✅ No manual intervention needed
- ✅ Secure by default

## Related Commands

```bash
# View ACME file
sudo cat /opt/wharftales/ssl/acme.json | jq

# Check certificates
sudo cat /opt/wharftales/ssl/acme.json | jq '.letsencrypt.Certificates'

# Restart Traefik
docker-compose restart traefik

# View logs
docker logs wharftales_traefik -f

# Force new certificates
sudo rm /opt/wharftales/ssl/acme.json
sudo bash fix-acme.sh
docker-compose restart traefik

# Run security audit
sudo bash production-check.sh
```

## Support Resources

- **Quick Fix:** `QUICK-FIX-ACME.sh`
- **Detailed Guide:** `REMOTE-ACME-FIX.md`
- **Deployment Guide:** `DEPLOY-TO-REMOTE.md`
- **Summary:** `ACME-FIX-SUMMARY.md`
- **Security Audit:** `production-check.sh`

## Success Indicators

You'll know it's working when:

1. ✅ No "ACME file not found" errors
2. ✅ File has correct permissions (600)
3. ✅ Traefik logs show certificate attempts
4. ✅ acme.json grows beyond 169 bytes
5. ✅ HTTPS works on your domains
6. ✅ Browser shows valid SSL certificate

## Summary

This comprehensive fix ensures:
- **Existing installations** can be fixed quickly with provided scripts
- **New installations** automatically create the ACME file correctly
- **Security** is maintained with proper permissions
- **Documentation** is complete for troubleshooting

All installation paths now handle the ACME file correctly, preventing the "ACME file not found" error for all users.

---

**Status:** Complete
**Local Environment:** ✅ Fixed
**Remote Server:** ⏳ Awaiting deployment
**New Installations:** ✅ Automatic fix included
