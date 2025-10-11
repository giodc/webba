# Deploy ACME Fix to Remote Server

## Quick Start (Choose One Method)

### Method 1: Quick Copy-Paste Fix ⚡ (30 seconds)

Copy this entire block and paste into your remote server terminal:

```bash
cd /opt/webbadeploy
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
ls -la ssl/acme.json
docker-compose restart traefik
echo "✅ Done! Monitor: docker logs webbadeploy_traefik -f"
```

### Method 2: Copy Script and Run 📋 (1 minute)

From your local machine:

```bash
# Copy the fix script
scp /opt/webbadeploy/fix-acme.sh user@your-server:/opt/webbadeploy/

# SSH to remote
ssh user@your-server

# Run the script
cd /opt/webbadeploy
sudo bash fix-acme.sh

# Restart Traefik
docker-compose restart traefik

# Monitor logs
docker logs webbadeploy_traefik -f
```

### Method 3: Full Deployment Script 🚀 (2 minutes)

Use this if you have other pending fixes too:

```bash
# From local machine, copy deployment files
scp /opt/webbadeploy/fix-acme.sh user@your-server:/opt/webbadeploy/
scp /opt/webbadeploy/DEPLOY-FIXES.sh user@your-server:/opt/webbadeploy/

# SSH to remote
ssh user@your-server

# Run deployment
cd /opt/webbadeploy
bash DEPLOY-FIXES.sh
```

## Verification Checklist

After deploying, verify everything is working:

```bash
# 1. Check file exists with correct permissions
ls -la /opt/webbadeploy/ssl/acme.json
# Expected output: -rw------- 1 root root 169 Oct 11 14:43 acme.json

# 2. Verify file content
sudo cat /opt/webbadeploy/ssl/acme.json | jq
# Should show JSON structure

# 3. Check Traefik is running
docker ps | grep traefik
# Should show webbadeploy_traefik container

# 4. Monitor certificate acquisition
docker logs webbadeploy_traefik 2>&1 | tail -50
# Look for certificate-related messages

# 5. Watch real-time logs
docker logs webbadeploy_traefik -f
# Press Ctrl+C to exit
```

## Expected Results

### Immediately After Fix
- ✅ File exists: `/opt/webbadeploy/ssl/acme.json`
- ✅ Permissions: `600` (rw-------)
- ✅ Owner: `root:root`
- ✅ Size: `169 bytes` (initial template)
- ✅ Traefik restarts successfully

### Within 1-5 Minutes (if domain configured)
- ✅ Traefik attempts certificate acquisition
- ✅ acme.json file grows in size (several KB)
- ✅ Certificates appear in logs
- ✅ HTTPS works on your domain

## Troubleshooting

### Issue: "Permission denied" when creating file
**Solution:** Make sure you're using `sudo`:
```bash
sudo bash fix-acme.sh
```

### Issue: Traefik not acquiring certificates
**Check these:**

1. **Valid domain configured?**
   ```bash
   grep "Host(" docker-compose.yml
   # Should show real domain, not demo.test.local
   ```

2. **Valid email configured?**
   ```bash
   grep "acme.email" docker-compose.yml
   # Should NOT be testdd@example.com
   ```

3. **Ports accessible?**
   ```bash
   sudo ufw status
   curl -I http://your-domain.com
   ```

4. **DNS pointing to server?**
   ```bash
   nslookup your-domain.com
   dig your-domain.com +short
   ```

### Issue: Rate limited by Let's Encrypt
**Symptoms:** Logs show "too many certificates" or "rate limit"

**Solution:** Wait 1 hour to 1 week depending on limit hit. See:
https://letsencrypt.org/docs/rate-limits/

### Issue: File exists but still getting error
**Solution:** Restart all containers:
```bash
docker-compose down
docker-compose up -d
docker logs webbadeploy_traefik -f
```

## Important Notes

### About acme.json
- **Initial size:** 169 bytes (empty template)
- **After certificates:** Several KB (contains encrypted cert data)
- **Permissions:** MUST be 600 or Traefik refuses to use it
- **Auto-managed:** Traefik updates this file automatically
- **Backup:** Consider backing up when it contains certificates

### About Let's Encrypt
- **Free SSL certificates** valid for 90 days
- **Auto-renewal** happens 30 days before expiration
- **Requires:**
  - Valid domain name (not IP)
  - Domain pointing to your server
  - Ports 80 & 443 accessible
  - Valid email address

### About Traefik
- **Handles SSL automatically** once configured
- **HTTP-01 challenge** requires port 80
- **Stores certificates** in acme.json
- **Logs everything** to docker logs

## Files Reference

| File | Purpose |
|------|---------|
| `fix-acme.sh` | Main fix script with validation |
| `QUICK-FIX-ACME.sh` | Minimal quick fix |
| `DEPLOY-FIXES.sh` | Full deployment script |
| `REMOTE-ACME-FIX.md` | Detailed guide |
| `ACME-FIX-SUMMARY.md` | Summary and overview |
| `DEPLOY-TO-REMOTE.md` | This file |

## Support Commands

```bash
# View all SSL-related logs
docker logs webbadeploy_traefik 2>&1 | grep -i "acme\|certificate\|ssl"

# Check Traefik configuration
docker exec webbadeploy_traefik traefik version

# Restart just Traefik
docker-compose restart traefik

# Restart everything
docker-compose restart

# Check container health
docker ps -a

# View docker-compose config
cat docker-compose.yml | grep -A 20 "traefik:"
```

## Success Indicators

You'll know it's working when:

1. ✅ No more "ACME file not found" errors
2. ✅ Traefik logs show certificate acquisition attempts
3. ✅ acme.json file grows beyond 169 bytes
4. ✅ HTTPS works on your domain
5. ✅ Browser shows valid SSL certificate
6. ✅ `production-check.sh` passes SSL checks

## Next Steps After Fix

1. **Update email** in docker-compose.yml if using test email
2. **Configure real domains** for your applications
3. **Run security audit:** `sudo bash production-check.sh`
4. **Set up monitoring** for certificate expiration
5. **Document your domains** and their SSL status

---

**Need Help?**
- Check `REMOTE-ACME-FIX.md` for detailed troubleshooting
- Review Traefik logs: `docker logs webbadeploy_traefik -f`
- Verify with: `sudo bash production-check.sh`
