# RBAC & 2FA Implementation Summary

## Overview

This document summarizes the implementation of Role-Based Access Control (RBAC), Two-Factor Authentication (2FA), and Redis support for all application types in WharfTales.

## Features Implemented

### 1. Two-Factor Authentication (2FA/TOTP)

**Files Created:**
- `/opt/wharftales/gui/includes/totp.php` - TOTP implementation (RFC 6238 compliant)
- `/opt/wharftales/gui/verify-2fa.php` - 2FA verification page

**Features:**
- TOTP-based authentication using standard authenticator apps
- QR code generation for easy setup
- 10 backup codes per user (single-use)
- Optional - users can enable/disable at will
- Session timeout for 2FA verification (5 minutes)
- Support for time-based code verification with 1-step discrepancy tolerance

**Database Schema:**
```sql
ALTER TABLE users ADD COLUMN totp_secret TEXT;
ALTER TABLE users ADD COLUMN totp_enabled INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN totp_backup_codes TEXT;
```

**API Endpoints:**
- `POST /api.php?action=setup_2fa` - Initialize 2FA setup
- `POST /api.php?action=verify_2fa_setup` - Verify setup code
- `POST /api.php?action=enable_2fa` - Enable 2FA and get backup codes
- `POST /api.php?action=disable_2fa` - Disable 2FA (requires password)

### 2. User Management & Role-Based Access Control

**Files Created:**
- `/opt/wharftales/gui/users.php` - User management interface (admin only)

**User Roles:**
- **Admin**: Full system access, user management, all sites
- **User**: Limited access based on permissions

**Database Schema:**
```sql
ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user';
ALTER TABLE users ADD COLUMN can_create_sites INTEGER DEFAULT 1;

CREATE TABLE site_permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    site_id INTEGER NOT NULL,
    permission TEXT DEFAULT 'view',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, site_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

ALTER TABLE sites ADD COLUMN owner_id INTEGER DEFAULT 1;
```

**Permission Levels:**
- **View**: Read-only access to site information
- **Edit**: Can modify site settings and content
- **Manage**: Full control including deletion

**Functions Added to auth.php:**
- `isAdmin()` - Check if current user is admin
- `requireAdmin()` - Require admin role or die
- `getAllUsers()` - Get all users (admin only)
- `getUserById($userId)` - Get user details
- `updateUser($userId, $data)` - Update user information
- `deleteUser($userId)` - Delete user (prevents deleting last admin)
- `canCreateSites($userId)` - Check if user can create sites
- `canAccessSite($userId, $siteId, $permission)` - Check site access
- `getUserSites($userId)` - Get sites accessible by user
- `grantSitePermission($userId, $siteId, $permission)` - Grant access
- `revokeSitePermission($userId, $siteId)` - Revoke access
- `getSitePermissions($siteId)` - Get all permissions for a site

**API Endpoints:**
- `POST /api.php?action=create_user` - Create new user (admin)
- `GET /api.php?action=get_user&id=X` - Get user details (admin)
- `POST /api.php?action=update_user` - Update user (admin)
- `POST /api.php?action=delete_user&id=X` - Delete user (admin)
- `POST /api.php?action=grant_site_permission` - Grant site access (admin)
- `POST /api.php?action=revoke_site_permission` - Revoke site access (admin)
- `GET /api.php?action=get_user_permissions&user_id=X` - Get user's site permissions (admin)

### 3. Audit Logging

**Database Schema:**
```sql
CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    resource_type TEXT,
    resource_id INTEGER,
    details TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Function:**
- `logAudit($action, $resourceType, $resourceId, $details)` - Log any action

**Logged Events:**
- User login/logout
- 2FA enabled/disabled
- User created/updated/deleted
- Site permissions granted/revoked
- All admin actions

### 4. Redis Support for PHP and Laravel

**Database Schema:**
```sql
ALTER TABLE sites ADD COLUMN redis_enabled INTEGER DEFAULT 0;
ALTER TABLE sites ADD COLUMN redis_host TEXT;
ALTER TABLE sites ADD COLUMN redis_port INTEGER DEFAULT 6379;
ALTER TABLE sites ADD COLUMN redis_password TEXT;
```

**Features:**
- Redis support added for WordPress (already existed)
- Redis support added for PHP applications
- Redis support added for Laravel applications
- Redis configuration visible in site settings
- Redis credentials stored securely in database

### 5. Database Migration

**File Created:**
- `/opt/wharftales/gui/migrate-rbac-2fa.php` - Automated migration script

**Migration Features:**
- Adds all new columns to existing tables
- Creates new tables (site_permissions, audit_log)
- Sets first user as admin automatically
- Idempotent - can be run multiple times safely
- Provides detailed output of all changes

**Running Migration:**
```bash
# Inside container
docker exec wharftales_gui php /app/migrate-rbac-2fa.php

# Or from host
cd /opt/wharftales
docker-compose exec web-gui php /app/migrate-rbac-2fa.php
```

## Installation Integration

### Updated Files:
1. `/opt/wharftales/install.sh` - Local development installer
2. `/opt/wharftales/install-production.sh` - Production installer

**Changes:**
- Both installers now run `migrate-rbac-2fa.php` automatically
- Migration runs after services start and database is ready
- Displays new features in completion message

## UI Changes

### Navigation Updates:
- Added "Users" menu item (admin only)
- Added "Two-Factor Auth" to user dropdown menu
- Added admin badge indicator in user dropdown

### New Pages:
1. `/opt/wharftales/gui/users.php` - User management interface
2. `/opt/wharftales/gui/verify-2fa.php` - 2FA verification page

### User Management Interface Features:
- List all users with roles and permissions
- Create new users with role assignment
- Edit user details (email, role, site creation permission)
- Delete users (with protection for last admin)
- Manage site permissions per user
- Visual indicators for 2FA status
- Last login tracking

## Security Enhancements

### Authentication Flow:
1. User enters username/password
2. If 2FA enabled → redirect to 2FA verification page
3. User enters 6-digit code or backup code
4. On success → complete login
5. Failed attempts are logged

### Session Security:
- 2FA verification has 5-minute timeout
- Session regeneration on login
- Temporary 2FA session data cleared after verification
- CSRF protection on all forms

### Password Requirements:
- Minimum 8 characters
- BCrypt hashing with cost factor 12
- Account lockout after 5 failed attempts (15 minutes)
- Rate limiting: 5 attempts per IP per 15 minutes

## Testing Checklist

### 2FA Testing:
- [ ] Setup 2FA with QR code
- [ ] Verify code from authenticator app
- [ ] Test backup codes
- [ ] Test 2FA login flow
- [ ] Test 2FA timeout
- [ ] Disable 2FA

### User Management Testing:
- [ ] Create admin user
- [ ] Create regular user
- [ ] Update user role
- [ ] Toggle site creation permission
- [ ] Delete user
- [ ] Prevent deleting last admin

### Permission Testing:
- [ ] Grant site view permission
- [ ] Grant site edit permission
- [ ] Grant site manage permission
- [ ] Revoke site permission
- [ ] Test user can only see assigned sites
- [ ] Test admin sees all sites

### Redis Testing:
- [ ] Enable Redis for WordPress site
- [ ] Enable Redis for PHP site
- [ ] Enable Redis for Laravel site
- [ ] Verify Redis connection info displayed
- [ ] Test Redis functionality

## Migration Path for Existing Installations

### For Existing Users:

1. **Pull latest code:**
   ```bash
   cd /opt/wharftales
   git pull origin master
   ```

2. **Run migration:**
   ```bash
   docker exec wharftales_gui php /app/migrate-rbac-2fa.php
   ```

3. **Restart services:**
   ```bash
   docker-compose restart
   ```

4. **Verify:**
   - Login to dashboard
   - Check "Users" menu appears (if admin)
   - Check 2FA option in user menu
   - All existing sites should work normally

### Backward Compatibility:
- All existing functionality preserved
- Existing users become admins by default
- Existing sites assigned to first user (owner_id = 1)
- 2FA is optional - no forced enrollment
- No breaking changes to existing sites

## Configuration Options

### Global Settings:
- `users_can_create_sites` - Default permission for new users (stored in settings table)

### Per-User Settings:
- `role` - admin or user
- `can_create_sites` - boolean flag
- `totp_enabled` - 2FA status

### Per-Site Settings:
- `owner_id` - User who created the site
- `redis_enabled` - Redis caching status
- `redis_host`, `redis_port`, `redis_password` - Redis connection details

## API Reference

### User Management Endpoints (Admin Only):

```
POST /api.php?action=create_user
Body: username, password, email, role, can_create_sites

GET /api.php?action=get_user&id={userId}

POST /api.php?action=update_user
Body: user_id, email, role, can_create_sites

DELETE /api.php?action=delete_user&id={userId}

POST /api.php?action=grant_site_permission
Body: user_id, site_id, permission (view|edit|manage)

POST /api.php?action=revoke_site_permission
Body: user_id, site_id

GET /api.php?action=get_user_permissions&user_id={userId}
```

### 2FA Endpoints:

```
POST /api.php?action=setup_2fa
Returns: secret, qr_code_url, provisioning_uri

POST /api.php?action=verify_2fa_setup
Body: code

POST /api.php?action=enable_2fa
Body: code
Returns: backup_codes[]

POST /api.php?action=disable_2fa
Body: password
```

## Future Enhancements

### Potential Additions:
- [ ] Email notifications for security events
- [ ] Password reset via email
- [ ] Session management (view/revoke active sessions)
- [ ] More granular permissions (per-feature access)
- [ ] Team/organization support
- [ ] API keys for programmatic access
- [ ] Webhook support for audit events
- [ ] Export audit logs
- [ ] User activity dashboard
- [ ] IP whitelisting per user

## Support & Documentation

### Resources:
- Main README: `/opt/wharftales/README.md`
- This document: `/opt/wharftales/RBAC_2FA_IMPLEMENTATION.md`
- Migration script: `/opt/wharftales/gui/migrate-rbac-2fa.php`

### Getting Help:
- Check logs: `docker-compose logs -f web-gui`
- Run migration manually if needed
- Verify database schema with SQLite browser
- Check audit_log table for security events

## Credits

Implementation follows industry best practices:
- TOTP: RFC 6238 standard
- Password hashing: BCrypt (PHP password_hash)
- Session security: HTTPOnly, Secure, SameSite cookies
- CSRF protection: Token-based validation
- Rate limiting: IP-based with time windows
