# Remote ACME File Fix Guide

## Problem
The error "ACME file not found at: /opt/wharftales/ssl/acme.json" indicates that the SSL certificate storage file is missing or empty on the remote server.

## Solution

### Option 1: Run the Fix Script (Recommended)

1. **Copy the fix script to remote server:**
   ```bash
   scp fix-acme.sh user@remote-server:/opt/wharftales/
   ```

2. **SSH into remote server:**
   ```bash
   ssh user@remote-server
   ```

3. **Run the fix script:**
   ```bash
   cd /opt/wharftales
   sudo bash fix-acme.sh
   ```

4. **Restart Traefik:**
   ```bash
   docker-compose restart traefik
   ```

5. **Monitor certificate acquisition:**
   ```bash
   docker logs wharftales_traefik -f
   ```

### Option 2: Manual Fix

If you can't copy the script, run these commands on the remote server:

```bash
# Navigate to wharftales directory
cd /opt/wharftales

# Create ssl directory if missing
sudo mkdir -p ssl

# Create acme.json with proper structure
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

# Set correct permissions (CRITICAL!)
sudo chmod 600 ssl/acme.json
sudo chown root:root ssl/acme.json

# Verify
ls -la ssl/acme.json

# Restart Traefik
docker-compose restart traefik

# Watch logs
docker logs wharftales_traefik -f
```

## Verification

After applying the fix, verify:

1. **File exists and has correct permissions:**
   ```bash
   ls -la /opt/wharftales/ssl/acme.json
   # Should show: -rw------- 1 root root
   ```

2. **Traefik is running:**
   ```bash
   docker ps | grep traefik
   ```

3. **Check for certificate acquisition in logs:**
   ```bash
   docker logs wharftales_traefik 2>&1 | grep -i "certificate"
   ```

4. **Verify acme.json is being populated:**
   ```bash
   sudo cat /opt/wharftales/ssl/acme.json | jq '.letsencrypt.Certificates'
   ```

## Important Notes

- **Permissions are critical:** The file MUST be 600 (rw-------) or Traefik will refuse to use it
- **Traefik populates the file:** The initial file is just a template; Traefik fills it with certificates
- **Domain requirements:** SSL certificates require:
  - Valid domain name (not IP address)
  - Domain pointing to your server
  - Ports 80 and 443 accessible from internet
  - Valid email in docker-compose.yml (not testdd@example.com)

## Troubleshooting

### If certificates aren't being issued:

1. **Check email configuration:**
   ```bash
   grep "acme.email" docker-compose.yml
   ```
   Update if it's a test email.

2. **Verify domain DNS:**
   ```bash
   nslookup your-domain.com
   ```

3. **Check firewall:**
   ```bash
   sudo ufw status
   # Ensure ports 80 and 443 are allowed
   ```

4. **Force certificate renewal:**
   ```bash
   sudo rm /opt/wharftales/ssl/acme.json
   sudo bash fix-acme.sh
   docker-compose restart traefik
   ```

5. **Check Traefik logs for errors:**
   ```bash
   docker logs wharftales_traefik 2>&1 | grep -i "error\|fail"
   ```

## What Happens Next

1. Traefik will attempt to obtain SSL certificates via Let's Encrypt
2. It uses HTTP-01 challenge (requires port 80 accessible)
3. Certificates are stored in acme.json
4. Auto-renewal happens 30 days before expiration
5. You can monitor progress in Traefik logs

## Related Files

- `docker-compose.yml` - Contains Traefik configuration
- `ssl/acme.json` - Certificate storage (auto-managed by Traefik)
- `production-check.sh` - Security audit script
