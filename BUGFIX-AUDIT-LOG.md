# Bug Fixes: Missing Database Tables and Columns

## Issue 1: Missing audit_log Table

**Error on first login:**
```
Fatal error: Uncaught PDOException: SQLSTATE[HY000]: General error: 1 no such table: audit_log 
in /var/www/html/includes/auth.php:638
```

**Symptoms:**
- Error appears on first login attempt
- Error disappears after page refresh
- Login works but audit logging fails

## Root Cause

The `initAuthDatabase()` function in `/opt/webbadeploy/gui/includes/auth.php` was creating the `users` and `login_attempts` tables but **not** the `audit_log` table.

The `audit_log` table was only defined in `initDatabase()` from `functions.php`, but the authentication system uses its own separate database initialization function.

## Fix Applied

Added the `audit_log` table creation to `initAuthDatabase()` function in `auth.php`:

```php
// Create audit_log table
$db->exec("CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    resource_type TEXT,
    resource_id INTEGER,
    details TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
```

Also added missing column migration for 2FA backup codes:

```php
try {
    $db->exec("ALTER TABLE users ADD COLUMN totp_backup_codes TEXT");
} catch (PDOException $e) {
    // Column already exists, ignore
}
```

## Files Modified

- `/opt/webbadeploy/gui/includes/auth.php`
  - Added `audit_log` table creation in `initAuthDatabase()`
  - Added `totp_backup_codes` column migration

## Verification

```bash
# Verify audit_log table exists
docker exec webbadeploy_gui php -r "
require_once '/var/www/html/includes/auth.php';
\$db = initAuthDatabase();
\$tables = \$db->query(\"SELECT name FROM sqlite_master WHERE type='table' AND name='audit_log'\")->fetchColumn();
echo \$tables ? 'audit_log table: EXISTS' : 'audit_log table: MISSING';
"

# Test audit log insertion
docker exec webbadeploy_gui php -r "
require_once '/var/www/html/includes/auth.php';
\$db = initAuthDatabase();
\$stmt = \$db->prepare('INSERT INTO audit_log (user_id, action, ip_address) VALUES (?, ?, ?)');
\$stmt->execute([1, 'test', '127.0.0.1']);
echo 'Audit log test: SUCCESS';
"
```

## Status

✅ **FIXED** - The `audit_log` table is now created automatically on first database initialization.

## Impact

- **Before**: Login would fail with fatal error on first attempt
- **After**: Login works correctly and audit logging functions properly

## Related Tables

The authentication system now properly creates:
1. `users` - User accounts
2. `login_attempts` - Failed login tracking
3. `audit_log` - Audit trail for all user actions

## Testing

1. Clear browser cache and cookies
2. Navigate to login page
3. Enter credentials and login
4. Should login successfully without errors
5. Audit log should record the login action

---

## Issue 2: Missing php_version Column

**Error on creating new site:**
```
SQLSTATE[HY000]: General error: 1 table sites has no column named php_version
```

**Symptoms:**
- Error when creating a new site
- PHP version selector not working
- Site creation fails

### Root Cause

The `php_version` column (and several other columns) were missing from:
1. The `CREATE TABLE sites` statement in `initDatabase()`
2. The migration section that adds columns to existing tables

### Fix Applied

Added missing columns to both the CREATE TABLE statement and migration section in `/opt/webbadeploy/gui/includes/functions.php`:

**Columns added to CREATE TABLE:**
- `php_version TEXT DEFAULT '8.3'`
- `redis_host TEXT`
- `redis_port INTEGER`
- `redis_password TEXT`

**Columns added to migrations:**
- `php_version`
- `redis_host`
- `redis_port`
- `redis_password`
- `github_repo`
- `github_branch`
- `github_token`
- `github_last_commit`
- `github_last_pull`
- `deployment_method`

### Files Modified

- `/opt/webbadeploy/gui/includes/functions.php`
  - Updated `CREATE TABLE sites` statement
  - Added comprehensive column migrations

### Verification

```bash
# Verify all columns exist
docker exec webbadeploy_gui php -r "
require_once '/var/www/html/includes/functions.php';
\$db = initDatabase();
\$schema = \$db->query('PRAGMA table_info(sites)')->fetchAll(PDO::FETCH_ASSOC);
foreach(\$schema as \$col) { echo \$col['name'] . PHP_EOL; }
"
```

### Status

✅ **FIXED** - All required columns are now created automatically.

---

## Summary

Both issues have been resolved:

1. ✅ **audit_log table** - Created in `initAuthDatabase()`
2. ✅ **php_version column** - Added to sites table with migrations
3. ✅ **redis columns** - Added to sites table with migrations
4. ✅ **github columns** - Added to sites table with migrations

### Impact

- **Before**: Database errors on first login and site creation
- **After**: Clean installation with all required tables and columns

---

**Fixed**: 2025-10-11  
**Version**: 3.0+  
**Severity**: High (affects core functionality)
