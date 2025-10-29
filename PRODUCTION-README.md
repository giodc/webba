# Production Deployment - Complete Package

## 📦 What's Included

This production deployment package includes everything you need to securely deploy WharfTales to production.

### Files Created

1. **`production-readiness-check.sh`** (19KB)
   - Comprehensive security audit script
   - Checks 8 critical security areas
   - Auto-fixes common issues
   - Provides detailed reports

2. **`PRODUCTION-DEPLOYMENT-GUIDE.md`** (11KB)
   - Complete deployment guide
   - Step-by-step instructions
   - Security hardening procedures
   - Troubleshooting solutions

3. **`PRODUCTION-QUICK-REFERENCE.md`** (5.4KB)
   - Quick command reference
   - Emergency procedures
   - Common tasks
   - Monitoring commands

---

## 🚀 Quick Start (3 Steps)

### Step 1: Run Security Check
```bash
sudo bash /opt/wharftales/production-readiness-check.sh --dry-run
```

This will scan your installation and report any security issues **without making changes**.

### Step 2: Apply Fixes
```bash
sudo bash /opt/wharftales/production-readiness-check.sh
```

This will automatically fix most common security issues.

### Step 3: Verify
```bash
sudo bash /opt/wharftales/production-readiness-check.sh --dry-run
```

Run again to verify all issues are resolved. You should see:
```
✓ PRODUCTION READY!
Your WharfTales installation is secure and ready for production.
```

---

## 🔍 What Gets Checked

### 1. System Security
- ✅ Firewall configuration (UFW)
- ✅ Automatic security updates
- ✅ Fail2ban installation
- ✅ Essential ports (80, 443)
- ⚠️ Dashboard port (9000) exposure

### 2. File Permissions
- ✅ Directory ownership (www-data)
- ✅ Correct permissions (755, 750, 640)
- ✅ Database file permissions (664)
- ✅ No world-writable files
- ✅ SSL directory security

### 3. Docker Security
- ✅ Docker socket permissions (660)
- ✅ Docker GID configuration
- ✅ No privileged containers
- ✅ Container resource limits
- ✅ Network isolation

### 4. Database Security
- ✅ No default passwords
- ✅ SQLite file permissions
- ✅ Database backups exist
- ✅ Proper ownership
- ✅ Not web-accessible

### 5. Application Security
- ✅ Encryption key configured
- ✅ 2FA availability
- ✅ Session security
- ✅ Secure cookies
- ✅ CSRF protection

### 6. SSL/TLS
- ✅ SSL certificates present
- ✅ acme.json permissions (600)
- ✅ Traefik configuration
- ✅ Dashboard disabled
- ✅ HTTPS enforcement

### 7. Exposed Services
- ✅ No sensitive ports public
- ✅ SFTP security
- ✅ Database not exposed
- ✅ Redis not exposed
- ✅ Proper port binding

### 8. Malware & Integrity
- ✅ WordPress malware scan
- ✅ Suspicious file detection
- ✅ Cron job audit
- ✅ eval() detection
- ✅ base64_decode checks

---

## 📊 Security Levels

### 🔴 Critical Issues (Must Fix)
- Default database passwords
- World-writable Docker socket (666)
- Port 9000 publicly accessible
- Missing encryption key
- World-writable files
- Firewall disabled

### 🟡 Warnings (Should Fix)
- No SSL certificates
- 2FA not enabled
- No database backups
- Automatic updates not configured
- Fail2ban not installed
- Unusual permissions

### 🟢 Production Ready
- All critical issues resolved
- Warnings reviewed and addressed
- Security best practices followed
- Monitoring configured
- Backups scheduled

---

## 🛡️ What Gets Fixed Automatically

When you run the script **without** `--dry-run`, it will automatically:

1. **Open firewall ports** (80, 443)
2. **Fix file permissions** (755, 750, 664, 640)
3. **Fix ownership** (www-data:www-data, root:www-data)
4. **Fix Docker socket** (660 permissions)
5. **Update Docker GID** in docker-compose.yml
6. **Remove world-writable** permissions
7. **Fix acme.json** permissions (600)
8. **Fix database** permissions (664)

---

## 📋 Manual Steps Required

Some things **cannot** be auto-fixed and require manual intervention:

### 1. Change Default Database Password
```bash
# Edit docker-compose.yml
nano /opt/wharftales/docker-compose.yml

# Change these lines:
MYSQL_ROOT_PASSWORD=YOUR_STRONG_PASSWORD_HERE
MYSQL_PASSWORD=YOUR_STRONG_PASSWORD_HERE

# Restart database
docker-compose restart db
```

### 2. Restrict Dashboard Access
```bash
# Option A: Restrict to your IP
sudo ufw allow from YOUR_IP to any port 9000

# Option B: Use SSH tunnel
ssh -L 9000:localhost:9000 user@your-server
# Access via: http://localhost:9000
```

### 3. Enable 2FA
1. Login to dashboard
2. Go to **Users** → Edit your user
3. Enable **Two-Factor Authentication**
4. Scan QR code with authenticator app
5. Save and test

### 4. Configure SSL
1. Update domain in docker-compose.yml
2. Set proper email for Let's Encrypt
3. Restart Traefik: `docker-compose restart traefik`
4. Verify: `docker logs wharftales_traefik`

---

## 📖 Documentation Structure

```
/opt/wharftales/
├── production-readiness-check.sh      # Main security script
├── PRODUCTION-README.md               # This file (overview)
├── PRODUCTION-DEPLOYMENT-GUIDE.md     # Complete guide
├── PRODUCTION-QUICK-REFERENCE.md      # Quick commands
├── SECURITY-CHECKLIST.md              # Security details
├── security-audit.sh                  # Additional audit
└── README.md                          # General README
```

### When to Use Each Document

| Document | When to Use |
|----------|-------------|
| **PRODUCTION-README.md** | First time setup, overview |
| **PRODUCTION-DEPLOYMENT-GUIDE.md** | Detailed deployment steps |
| **PRODUCTION-QUICK-REFERENCE.md** | Daily operations, quick lookup |
| **SECURITY-CHECKLIST.md** | Security review, compliance |

---

## 🎯 Deployment Workflow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. PREPARE                                                  │
│    └─ Run: production-readiness-check.sh --dry-run         │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. FIX ISSUES                                               │
│    ├─ Auto: production-readiness-check.sh                  │
│    └─ Manual: Follow recommendations                        │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. VERIFY                                                   │
│    └─ Run: production-readiness-check.sh --dry-run         │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. DEPLOY                                                   │
│    ├─ Enable firewall                                      │
│    ├─ Start containers                                     │
│    └─ Configure monitoring                                 │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. MAINTAIN                                                 │
│    ├─ Weekly: Security audit                               │
│    ├─ Monthly: Updates & backups                           │
│    └─ Quarterly: Full review                               │
└─────────────────────────────────────────────────────────────┘
```

---

## ⚡ Example Output

### Successful Check
```
╔════════════════════════════════════════════════════╗
║   WharfTales Production Readiness Check          ║
║   Security Hardening & Verification Script        ║
╚════════════════════════════════════════════════════╝

[SECTION 1/8] System Security
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[1.1] Checking firewall configuration...
  ✓ UFW firewall is active
  ✓ Port 80 (HTTP) allowed
  ✓ Port 443 (HTTPS) allowed

[... 7 more sections ...]

╔════════════════════════════════════════════════════╗
║              Security Audit Summary                ║
╚════════════════════════════════════════════════════╝

Critical Issues:     0
Warnings:            0

✓ PRODUCTION READY!
Your WharfTales installation is secure and ready for production.
```

### Issues Found
```
╔════════════════════════════════════════════════════╗
║              Security Audit Summary                ║
╚════════════════════════════════════════════════════╝

Critical Issues:     3
Warnings:            5
Fixes Applied:       2

✗ NOT PRODUCTION READY
Found 3 critical issue(s) that must be fixed.

Required Actions:
  1. Fix all critical issues listed above
  2. Run this script again to verify
  3. Review SECURITY-CHECKLIST.md
```

---

## 🔗 Quick Links

### Essential Commands
```bash
# Security check
sudo bash production-readiness-check.sh --dry-run

# Apply fixes
sudo bash production-readiness-check.sh

# Start services
docker-compose up -d

# Check status
docker ps

# View logs
docker-compose logs -f
```

### Essential Files
- **Main Script**: `production-readiness-check.sh`
- **Full Guide**: `PRODUCTION-DEPLOYMENT-GUIDE.md`
- **Quick Ref**: `PRODUCTION-QUICK-REFERENCE.md`
- **Security**: `SECURITY-CHECKLIST.md`

---

## 💡 Tips

### Before Running
1. **Backup everything** first
2. Run in **dry-run mode** first
3. Review the output carefully
4. Understand what will be changed

### During Deployment
1. Keep SSH session open
2. Test each change
3. Monitor logs continuously
4. Have rollback plan ready

### After Deployment
1. Test all functionality
2. Enable monitoring
3. Schedule backups
4. Document any custom changes

---

## 🆘 Need Help?

### Script Issues
```bash
# View help
bash production-readiness-check.sh --help

# Run in dry-run mode
sudo bash production-readiness-check.sh --dry-run
```

### General Issues
1. Check logs: `docker-compose logs`
2. Review documentation: `PRODUCTION-DEPLOYMENT-GUIDE.md`
3. Check security: `SECURITY-CHECKLIST.md`
4. Run audit: `sudo bash security-audit.sh`

---

## ✅ Success Criteria

Your installation is **production ready** when:

- ✅ Security check passes with 0 critical issues
- ✅ Firewall is enabled and configured
- ✅ All default passwords changed
- ✅ SSL certificates configured
- ✅ 2FA enabled for admin accounts
- ✅ Backups scheduled and tested
- ✅ Monitoring configured
- ✅ All services running properly

---

## 📅 Maintenance Schedule

| Frequency | Tasks |
|-----------|-------|
| **Daily** | Check container status, review logs |
| **Weekly** | Run security audit, check disk space |
| **Monthly** | Update containers, test backups, rotate credentials |
| **Quarterly** | Full security review, disaster recovery test |

---

**Ready to deploy?**

```bash
sudo bash /opt/wharftales/production-readiness-check.sh
```

**Last Updated**: 2025-10-11  
**Version**: 1.0  
**Status**: Production Ready ✓
