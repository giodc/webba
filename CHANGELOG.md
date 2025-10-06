# Changelog

All notable changes to Webbadeploy will be documented in this file.

## [2.0.0] - 2025-10-05

### 🎉 Major Release - RBAC & 2FA

This is a major release introducing enterprise-grade security and multi-user support.

### Added

#### Authentication & Security
- **Two-Factor Authentication (2FA/TOTP)** - Optional TOTP-based 2FA for all users
  - QR code generation with multiple API fallbacks
  - Base64 embedded QR codes for reliability
  - Manual secret key entry option
  - 10 backup codes per user (single-use)
  - TOTP verification (RFC 6238 compliant)
  - Password confirmation for disabling 2FA
  - 5-minute session timeout for 2FA verification

#### User Management
- **Role-Based Access Control (RBAC)** - Admin and User roles
  - User creation, editing, and deletion
  - Site ownership tracking
  - Granular site permissions (View/Edit/Manage)
  - Site creation control per user
  - Protection against deleting last admin
  - User management interface (admin only)

#### Site Permissions
- **Three-tier permission system**:
  - **View**: Read-only access to site information
  - **Edit**: Can modify site settings and content
  - **Manage**: Full control including deletion
- Users only see sites they own or have been granted access to
- Admins have access to all sites

#### Redis Support
- Database schema ready for Redis support on all app types
- Redis columns added: `redis_enabled`, `redis_host`, `redis_port`, `redis_password`
- Foundation for PHP and Laravel Redis caching (WordPress already supported)

#### Audit Logging
- Complete audit trail of all user actions
- Logs: logins, 2FA events, user management, permission changes
- Stored with timestamps and IP addresses
- Foundation for future audit log viewer

#### UI Enhancements
- Version number displayed in header (before user dropdown)
- Navigation menu aligned to right
- "Users" menu for administrators
- "Two-Factor Auth" option in user dropdown
- Admin badge indicator for admin accounts
- Improved responsive design

### Changed
- Updated `getCurrentUser()` to include role and 2FA status
- Modified `getUserHandler()` API to return current user when no ID provided
- Enhanced authentication flow to support 2FA verification
- Session regeneration on login for improved security
- First user automatically set as admin on migration

### Database Schema
- **users table**: Added `role`, `can_create_sites`, `totp_secret`, `totp_enabled`, `totp_backup_codes`
- **sites table**: Added `owner_id`, `redis_enabled`, `redis_host`, `redis_port`, `redis_password`
- **New table**: `site_permissions` - Tracks user access to sites
- **New table**: `audit_log` - Complete audit trail
- **New setting**: `users_can_create_sites` - Global site creation control

### Installation
- Migration script: `migrate-rbac-2fa.php` runs automatically on install
- Backup migration: `complete-migration.php` for manual execution
- Both `install.sh` and `install-production.sh` updated
- All features available on fresh installs

### API Endpoints Added
- `POST /api.php?action=create_user` - Create new user (admin)
- `GET /api.php?action=get_user&id=X` - Get user details
- `POST /api.php?action=update_user` - Update user (admin)
- `POST /api.php?action=delete_user&id=X` - Delete user (admin)
- `POST /api.php?action=grant_site_permission` - Grant site access (admin)
- `POST /api.php?action=revoke_site_permission` - Revoke site access (admin)
- `GET /api.php?action=get_user_permissions&user_id=X` - Get user's permissions
- `POST /api.php?action=setup_2fa` - Initialize 2FA setup
- `POST /api.php?action=verify_2fa_setup` - Verify setup code
- `POST /api.php?action=enable_2fa` - Enable 2FA and get backup codes
- `POST /api.php?action=disable_2fa` - Disable 2FA (requires password)

### Files Added
- `gui/includes/totp.php` - TOTP implementation
- `gui/verify-2fa.php` - 2FA verification page
- `gui/users.php` - User management interface
- `gui/qr-code.php` - QR code generator
- `gui/migrate-rbac-2fa.php` - Database migration script
- `gui/complete-migration.php` - Backup migration script
- `RBAC_2FA_IMPLEMENTATION.md` - Technical documentation
- `QUICK_START_NEW_FEATURES.md` - User guide
- `IMPLEMENTATION_COMPLETE.md` - Deployment guide
- `CHANGELOG.md` - This file

### Files Modified
- `gui/includes/auth.php` - Added RBAC and 2FA functions
- `gui/includes/navigation.php` - Added Users menu, 2FA link, version display
- `gui/login.php` - Added 2FA redirect logic
- `gui/api.php` - Added user management and 2FA endpoints
- `gui/index.php` - Added 2FA modal
- `gui/js/app.js` - Added 2FA modal functions
- `install.sh` - Added migration execution
- `install-production.sh` - Added migration execution
- `README.md` - Documented new features

### Security Enhancements
- BCrypt password hashing with cost factor 12
- Session regeneration on login
- CSRF protection on all forms
- Rate limiting (5 attempts per IP per 15 min)
- Account lockout (5 failed attempts = 15 min lock)
- 2FA with TOTP standard (RFC 6238)
- Backup codes for 2FA recovery
- Complete audit logging
- Role-based access control
- Granular site permissions

### Backward Compatibility
- ✅ All existing functionality preserved
- ✅ Existing users become admins automatically
- ✅ Existing sites assigned to first user (owner_id = 1)
- ✅ 2FA is optional - no forced enrollment
- ✅ No breaking changes to existing sites
- ✅ Migration is safe and idempotent

### Documentation
- Complete technical documentation in `RBAC_2FA_IMPLEMENTATION.md`
- User-friendly quick start guide in `QUICK_START_NEW_FEATURES.md`
- Deployment checklist in `IMPLEMENTATION_COMPLETE.md`
- Updated main `README.md` with new features
- Comprehensive troubleshooting guides

### Testing
- 2FA setup and verification tested
- User management tested
- Site permissions tested
- UI changes verified
- Migration tested on existing installations

---

## [1.8.3] - Previous Release

### Features
- WordPress deployment with Redis
- PHP application deployment
- Laravel application deployment
- SSL certificate management
- Domain management
- SFTP access
- Database management
- Container management

---

## Migration Guide

### From 1.x to 2.0.0

1. **Backup your database**:
   ```bash
   cp /opt/webbadeploy/data/database.sqlite /opt/webbadeploy/data/database.sqlite.backup
   ```

2. **Run the migration**:
   ```bash
   docker exec webbadeploy_gui php /var/www/html/migrate-rbac-2fa.php
   ```

3. **Restart services**:
   ```bash
   docker-compose restart
   ```

4. **Verify**:
   - Login to dashboard
   - Check "Users" menu appears
   - Test 2FA setup (optional)

### What Changes for Existing Users

- You are automatically set as admin
- All your existing sites are assigned to you
- 2FA is optional - you can enable it anytime
- Everything works exactly as before
- New features are additive, not breaking

---

## Upgrade Path

### Recommended Steps

1. **Test in development first** (if possible)
2. **Backup everything** (database, files)
3. **Run migration** during low-traffic period
4. **Test critical functionality** after upgrade
5. **Enable 2FA** for admin accounts
6. **Create additional users** as needed
7. **Grant site permissions** to team members

---

## Support

For issues, questions, or feature requests:
- Check documentation in `/opt/webbadeploy/docs/`
- Review troubleshooting guides
- Check GitHub issues
- Open a new issue with details

---

**Note**: This is a major version bump (1.x → 2.0.0) due to significant new features, but it maintains full backward compatibility with existing installations.
