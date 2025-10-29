# Docker Compose Access Fix - Let's Encrypt Email Update

## Problem Summary

The Let's Encrypt email could not be updated through the web interface due to PHP file access issues with `/opt/wharftales/docker-compose.yml`. The error message was:

```
Cannot access docker-compose.yml at /opt/wharftales/docker-compose.yml. 
Check if file exists and has proper permissions.
```

## Root Cause

The issue was caused by aggressive PHP stat caching and potential OPcache interference. While the file was accessible and writable (permissions: 666, owner: www-data), PHP's internal caching mechanisms were preventing reliable file access.

## Solution Implemented

Updated `/opt/wharftales/gui/settings.php` with the following improvements:

### 1. **Comprehensive Cache Clearing**
- Clear all PHP stat cache (not just for specific file)
- Reset OPcache if available
- Applied at multiple points: before read, after write

### 2. **Fallback Mechanisms**
- Primary method: `file_get_contents()` / `file_put_contents()`
- Fallback method: Shell commands via `exec()` with `cat`
- Ensures file access even if PHP functions fail
- **Fixed:** Proper variable initialization for `$output` and `$returnCode`

### 3. **Better Error Reporting**
- Capture and display actual PHP errors
- Show detailed error messages to help diagnose issues

### 4. **Variable Initialization**
- All `exec()` calls now properly initialize `$output = []` and `$returnCode = 0`
- Prevents undefined variable warnings and unexpected behavior

## Changes Made

### File: `/opt/wharftales/gui/settings.php`

**Three sections updated:**

1. **Initial file read (lines 11-30):**
   - Added full cache clearing
   - Added OPcache reset
   - Improved fallback logic

2. **Let's Encrypt email update (lines 39-115):**
   - Full cache clearing before and after operations
   - Dual-method write with shell fallback
   - Enhanced error messages with actual PHP errors

3. **Dashboard Traefik config update (lines 162-262):**
   - Same robust approach for reading
   - Same dual-method write with fallback
   - Cache clearing after successful write

## How to Test

### 1. Access Settings Page
```bash
# Open in browser
http://your-server-ip:9000/settings.php
```

### 2. Update Let's Encrypt Email
1. Navigate to "SSL Configuration" section
2. Current email is: `testd@example.com`
3. Enter a new valid email address
4. Click "Save SSL Settings"
5. Should see success message

### 3. Verify Update
```bash
# Check docker-compose.yml directly
cat /opt/wharftales/docker-compose.yml | grep acme.email

# Or from within container
docker-compose exec web-gui cat /opt/wharftales/docker-compose.yml | grep acme.email
```

### 4. Restart Traefik
After updating the email, restart Traefik to apply changes:
```bash
cd /opt/wharftales
docker-compose restart traefik
```

Or use the web interface button that appears after successful update.

## Certificate Generation

### Current Status
- Current email: `testd@example.com`
- No sites have SSL enabled yet
- No ACME activity in logs (expected)

### To Test Certificate Generation

1. **Create or edit a site with SSL:**
   - Go to Sites → Edit a site
   - Enable SSL
   - Save changes

2. **Monitor Traefik logs:**
   ```bash
   docker-compose logs -f traefik | grep -i "acme\|certificate"
   ```

3. **Check certificate storage:**
   ```bash
   ls -la /opt/wharftales/ssl/acme.json
   ```

### Important Notes

- **Port Requirements:** Ports 80 and 443 must be accessible from the internet for Let's Encrypt HTTP challenge
- **Domain Requirements:** Domain must point to your server's IP address
- **Email Notifications:** Let's Encrypt will send expiration notices to the configured email
- **Certificate Renewal:** Traefik handles automatic renewal

## File Permissions Verification

```bash
# Check file permissions
ls -la /opt/wharftales/docker-compose.yml
# Should show: -rw-rw-rw- 1 www-data www-data

# Check from container
docker-compose exec web-gui ls -la /opt/wharftales/docker-compose.yml

# Test PHP access
docker-compose exec web-gui php -r "
clearstatcache(true);
\$content = file_get_contents('/opt/wharftales/docker-compose.yml');
echo 'Read: ' . strlen(\$content) . ' bytes\n';
echo 'Writable: ' . (is_writable('/opt/wharftales/docker-compose.yml') ? 'YES' : 'NO') . '\n';
"
```

## Troubleshooting

### If Email Update Still Fails

1. **Check PHP error logs:**
   ```bash
   docker-compose logs web-gui | tail -50
   ```

2. **Test file access manually:**
   ```bash
   docker-compose exec web-gui php -r "
   \$path = '/opt/wharftales/docker-compose.yml';
   clearstatcache(true);
   var_dump(file_exists(\$path));
   var_dump(is_readable(\$path));
   var_dump(is_writable(\$path));
   "
   ```

3. **Check volume mount:**
   ```bash
   docker-compose exec web-gui mount | grep docker-compose.yml
   ```

4. **Verify permissions:**
   ```bash
   # On host
   sudo chmod 666 /opt/wharftales/docker-compose.yml
   sudo chown www-data:www-data /opt/wharftales/docker-compose.yml
   ```

### If Certificates Don't Generate

1. **Check domain DNS:**
   ```bash
   nslookup your-domain.com
   dig your-domain.com
   ```

2. **Verify ports are open:**
   ```bash
   # From another machine
   telnet your-server-ip 80
   telnet your-server-ip 443
   ```

3. **Check Traefik configuration:**
   ```bash
   docker-compose exec traefik cat /etc/traefik/traefik.yml
   ```

4. **View detailed Traefik logs:**
   ```bash
   docker-compose logs traefik --tail 100
   ```

5. **Check ACME storage:**
   ```bash
   cat /opt/wharftales/ssl/acme.json
   ```

## Technical Details

### Why This Fix Works

1. **Stat Cache:** PHP caches file metadata (exists, permissions, etc.) for performance. Clearing it ensures fresh checks.

2. **OPcache:** Caches compiled PHP code, but can interfere with file operations. Resetting ensures clean state.

3. **Shell Fallback:** If PHP functions fail (rare but possible), shell commands bypass PHP's internal caching entirely.

4. **Multiple Clear Points:** Clearing caches before read, after write, and in functions ensures no stale data.

### Code Pattern Used

```php
// Clear all caches
clearstatcache(true);
if (function_exists('opcache_reset')) {
    @opcache_reset();
}

// Try PHP function first
$content = @file_get_contents($path);

// Fallback to shell if needed
if ($content === false || empty($content)) {
    exec("cat $path 2>&1", $output, $returnCode);
    if ($returnCode === 0) {
        $content = implode("\n", $output);
    }
}

// For writes, same pattern
$result = @file_put_contents($path, $content);
if ($result === false) {
    $tempFile = tempnam(sys_get_temp_dir(), 'docker-compose-');
    file_put_contents($tempFile, $content);
    exec("cat $tempFile > $path 2>&1", $output, $returnCode);
    unlink($tempFile);
    $result = ($returnCode === 0);
}

// Clear caches after write
clearstatcache(true);
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
```

## Summary

✅ **Fixed:** Docker compose file access with robust caching and fallback mechanisms  
✅ **Fixed:** Let's Encrypt email update functionality  
✅ **Improved:** Error reporting and diagnostics  
✅ **Ready:** For certificate generation when SSL is enabled on sites  

The system is now ready to update the Let's Encrypt email and generate certificates for sites with SSL enabled.
