╔═══════════════════════════════════════════════════════════════════╗
║                    ACME SSL FIX - QUICK REFERENCE                 ║
╚═══════════════════════════════════════════════════════════════════╝

PROBLEM: "ACME file not found at: /opt/wharftales/ssl/acme.json"

═══════════════════════════════════════════════════════════════════

SOLUTION FOR EXISTING INSTALLATIONS (Remote Server):

Option 1: Quick One-Liner (Copy & Paste)
─────────────────────────────────────────
cd /opt/wharftales && sudo mkdir -p ssl && sudo tee ssl/acme.json > /dev/null << 'EOF'
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
sudo chmod 600 ssl/acme.json && sudo chown root:root ssl/acme.json && docker-compose restart traefik

Option 2: Use Fix Script
────────────────────────
scp fix-acme.sh user@remote:/opt/wharftales/
ssh user@remote
cd /opt/wharftales
sudo bash fix-acme.sh
docker-compose restart traefik

═══════════════════════════════════════════════════════════════════

SOLUTION FOR NEW INSTALLATIONS:

✅ ALREADY FIXED! Both install.sh and install-production.sh now
   automatically create the ACME file with correct permissions.

Just run:
  sudo bash install.sh
  OR
  sudo bash install-production.sh

═══════════════════════════════════════════════════════════════════

VERIFICATION:

ls -la /opt/wharftales/ssl/acme.json
# Expected: -rw------- 1 root root 169 Oct 11 14:43 acme.json

docker logs wharftales_traefik -f
# Watch for certificate acquisition messages

═══════════════════════════════════════════════════════════════════

FILES CREATED:

fix-acme.sh              - Main fix script
QUICK-FIX-ACME.sh        - Minimal quick fix
REMOTE-ACME-FIX.md       - Detailed guide
ACME-FIX-SUMMARY.md      - Summary & options
DEPLOY-TO-REMOTE.md      - Step-by-step guide
ACME-COMPLETE-FIX.md     - Comprehensive documentation
README-ACME-FIX.txt      - This file

UPDATED:
install.sh               - Now creates ACME file
install-production.sh    - Now creates ACME file
DEPLOY-FIXES.sh          - Includes ACME fix

═══════════════════════════════════════════════════════════════════

IMPORTANT:

✓ Permissions MUST be 600 (rw-------)
✓ Owner MUST be root:root
✓ File starts at 169 bytes (empty template)
✓ Grows to several KB after certificates are issued
✓ Traefik manages this file automatically

═══════════════════════════════════════════════════════════════════

NEXT STEPS:

1. Apply fix to remote server (use Option 1 or 2 above)
2. Monitor Traefik logs: docker logs wharftales_traefik -f
3. Verify HTTPS works on your domain
4. Run security audit: sudo bash production-check.sh

═══════════════════════════════════════════════════════════════════

For detailed help, see: ACME-COMPLETE-FIX.md
