# Fix ACME Email on Remote Server

## Problem

The remote server has a valid email set in the GUI settings, but Traefik is still using `test@example.com` from docker-compose.yml, causing Let's Encrypt to reject certificate requests.

**Error:**
```
Error validating contact(s) :: contact email has forbidden domain "example.com"
```

## Root Cause

The GUI settings store the email in the database, but **docker-compose.yml is not automatically updated**. Traefik reads its configuration from docker-compose.yml, not from the database.

## Solution

### Step 1: Check Current Configuration

SSH to your remote server and run:

```bash
cd /opt/webbadeploy
bash check-remote-email.sh
```

This will show:
- Email in docker-compose.yml (what Traefik uses)
- Email in database (what GUI shows)
- Email in running container
- Email registered with Let's Encrypt

### Step 2: Fix the Email Mismatch

Run the automated fix script:

```bash
cd /opt/webbadeploy
bash fix-remote-email.sh
```

This script will:
1. Read the correct email from database (GUI settings)
2. Update docker-compose.yml with that email
3. Remove old acme.json (cached with wrong email)
4. Create fresh acme.json
5. Recreate Traefik container with new configuration

### Step 3: Verify

Monitor Traefik logs to see certificate acquisition:

```bash
docker logs webbadeploy_traefik -f
```

Look for:
- ✅ No more "forbidden domain" errors
- ✅ "certificate obtained" messages
- ✅ Successful ACME registration

## Manual Fix (Alternative)

If you prefer to do it manually:

```bash
cd /opt/webbadeploy

# 1. Get email from database
EMAIL=$(sqlite3 data/database.sqlite "SELECT value FROM settings WHERE key='letsencrypt_email';")
echo "Email from database: $EMAIL"

# 2. Update docker-compose.yml
sed -i "s|acme.email=.*\"|acme.email=$EMAIL\"|" docker-compose.yml

# 3. Remove old acme.json
sudo rm ssl/acme.json

# 4. Create fresh acme.json
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

# 5. Recreate Traefik
docker-compose up -d --force-recreate traefik

# 6. Monitor logs
docker logs webbadeploy_traefik -f
```

## Why This Happens

1. **GUI updates database** - When you set email in GUI, it saves to SQLite database
2. **Traefik reads docker-compose.yml** - Traefik doesn't read from database
3. **Mismatch occurs** - docker-compose.yml still has old `test@example.com`
4. **Let's Encrypt rejects** - `example.com` is a forbidden domain

## Long-term Fix

The GUI should update docker-compose.yml when email is changed. This would require updating the PHP code that handles SSL settings to also update the docker-compose.yml file.

## Verification Commands

```bash
# Check email in docker-compose.yml
grep "acme.email" /opt/webbadeploy/docker-compose.yml

# Check email in database
sqlite3 /opt/webbadeploy/data/database.sqlite "SELECT value FROM settings WHERE key='letsencrypt_email';"

# Check email in running container
docker inspect webbadeploy_traefik | grep "acme.email"

# Check Traefik logs for errors
docker logs webbadeploy_traefik 2>&1 | grep -i "error\|forbidden"
```

## For All Remote Servers

Copy these scripts to each remote server:

```bash
# From local machine
scp check-remote-email.sh user@remote:/opt/webbadeploy/
scp fix-remote-email.sh user@remote:/opt/webbadeploy/

# On each remote server
ssh user@remote
cd /opt/webbadeploy
bash check-remote-email.sh
bash fix-remote-email.sh
```

## Prevention

After fixing, whenever you change the email in GUI settings:

1. SSH to server
2. Run: `bash fix-remote-email.sh`
3. This syncs database → docker-compose.yml → Traefik

Or manually update docker-compose.yml after changing email in GUI.

---

**Files:**
- `check-remote-email.sh` - Diagnose email configuration
- `fix-remote-email.sh` - Automatically fix email mismatch
- `FIX-EMAIL-REMOTE.md` - This guide
