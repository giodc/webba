# SSL Certificate Status Tracking

## Problem
The dashboard was showing "SSL: Pending" for all sites even when SSL certificates were working. This was because:
1. The `hasCertificate()` function tried to read `/opt/webbadeploy/ssl/acme.json`
2. The file had permission issues or showed `"Certificates": null`
3. The function couldn't reliably determine if certificates were actually issued

## Solution
Instead of checking the `acme.json` file, we now track SSL certificate status in the database.

## Changes Made

### 1. Database Schema
Added two new columns to the `sites` table:
- `ssl_cert_issued` (INTEGER): 0 = pending, 1 = issued
- `ssl_cert_issued_at` (DATETIME): Timestamp when certificate was marked as issued

### 2. Functions Added (`/opt/webbadeploy/gui/includes/functions.php`)

**`hasCertificate($domain)`**
- Now checks the database instead of reading acme.json
- Returns true if `ssl_cert_issued = 1` for the domain

**`markCertificateIssued($db, $siteId)`**
- Marks a certificate as issued for a site
- Sets `ssl_cert_issued = 1` and `ssl_cert_issued_at = CURRENT_TIMESTAMP`

**`markCertificateRemoved($db, $siteId)`**
- Marks a certificate as removed/revoked for a site
- Sets `ssl_cert_issued = 0` and `ssl_cert_issued_at = NULL`

### 3. SSL Debug Page (`/opt/webbadeploy/gui/ssl-debug.php`)
Enhanced with certificate management:
- Added "Certificate" column showing Issued/Pending status
- Added "Mark Issued" button for pending certificates
- Added "Mark Removed" button for issued certificates
- Shows certificate issue date when available

### 4. Sync Script (`/opt/webbadeploy/gui/sync-ssl-status.php`)
A utility script to sync certificate status from acme.json to database:
- Reads acme.json and extracts all certificate domains
- Compares with database and updates status accordingly
- Useful for initial setup or verification

## Usage

### Manual Management
1. Go to **SSL Debug** page in the dashboard
2. View all sites with SSL enabled
3. For each site:
   - If certificate is working but shows "Pending": Click **"Mark Issued"**
   - If certificate was revoked but shows "Issued": Click **"Mark Removed"**

### Automatic Sync (Optional)
Run the sync script from command line:
```bash
docker exec webbadeploy_gui php /app/sync-ssl-status.php
```

Or access it via browser (requires admin permissions):
```
https://your-dashboard-domain.com/sync-ssl-status.php
```

## Benefits
1. **No file permission issues**: Database is always accessible
2. **Manual control**: You can mark certificates as issued/removed
3. **Persistent**: Status survives container restarts
4. **Fast**: No need to parse JSON files on every page load
5. **Accurate**: Shows exactly what you set, not what the file says

## Migration
The database columns are automatically added when you load any page. Existing sites will have `ssl_cert_issued = 0` by default. You need to:
1. Visit the SSL Debug page
2. Click "Mark Issued" for sites that already have working certificates

## Future Enhancements
Consider adding:
- Automatic certificate detection via Traefik API
- Webhook from Traefik when certificates are issued
- Certificate expiration tracking
- Automatic renewal status
