# Database Schema & Migration Guide

## Overview

WharfTales uses SQLite for data storage with automatic schema migrations. The database is located at `/app/data/database.sqlite` inside the Docker container.

## Automatic Migrations

The application automatically handles database migrations when you upgrade. All missing columns and tables are created automatically on first run.

### What Happens on Fresh Install

1. **Users Table** - Created with RBAC support
2. **Sites Table** - Created with ownership tracking
3. **RBAC Tables** - Permission and audit tables created
4. **Settings Table** - Application settings storage

### What Happens on Upgrade

The system detects missing columns and adds them automatically:
- No data loss
- No manual intervention required
- Safe to upgrade anytime

## Database Tables

### 1. Users Table (`users`)

Stores user accounts and authentication data.

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    email TEXT,
    role TEXT DEFAULT 'user',              -- NEW in v2.0
    totp_secret TEXT,                      -- NEW in v2.0
    totp_enabled INTEGER DEFAULT 0,        -- NEW in v2.0
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    failed_attempts INTEGER DEFAULT 0,
    locked_until DATETIME
);
```

**Roles:**
- `admin` - Full system access
- `user` - Limited access based on permissions

---

### 2. Sites Table (`sites`)

Stores deployed applications.

```sql
CREATE TABLE sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    domain TEXT UNIQUE NOT NULL,
    ssl INTEGER DEFAULT 0,
    ssl_config TEXT,
    status TEXT DEFAULT 'stopped',
    container_name TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    config TEXT,
    sftp_enabled INTEGER DEFAULT 0,
    sftp_username TEXT,
    sftp_password TEXT,
    sftp_port INTEGER,
    db_password TEXT,
    db_type TEXT DEFAULT 'shared',
    owner_id INTEGER DEFAULT 1,            -- NEW in v2.0
    redis_enabled INTEGER DEFAULT 0,       -- NEW in v2.0
    redis_container TEXT                   -- NEW in v2.0
);
```

**New Columns:**
- `owner_id` - User who created the site
- `redis_enabled` - Whether Redis is enabled
- `redis_container` - Redis container name

---

### 3. User Permissions Table (`user_permissions`)

Stores global user permissions (e.g., can create sites).

```sql
CREATE TABLE user_permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    permission_key TEXT NOT NULL,
    permission_value INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, permission_key)
);
```

**Permission Keys:**
- `can_create_sites` - User can create new sites
- `can_manage_users` - User can manage other users (admin only)

---

### 4. Site Permissions Table (`site_permissions`)

Stores per-site access permissions.

```sql
CREATE TABLE site_permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    site_id INTEGER NOT NULL,
    permission_level TEXT DEFAULT 'view',
    granted_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, site_id)
);
```

**Permission Levels:**
- `view` - Read-only access
- `edit` - Can modify settings
- `manage` - Full control (including delete)

---

### 5. Audit Log Table (`audit_log`)

Tracks all user actions for security and compliance.

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

**Tracked Actions:**
- User login/logout
- Site creation/deletion
- Permission changes
- Settings updates
- 2FA setup/disable

---

### 6. Settings Table (`settings`)

Stores application-wide settings.

```sql
CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Common Settings:**
- `setup_completed` - Whether setup wizard was completed
- `custom_wildcard_domain` - Custom wildcard domain
- `dashboard_domain` - Custom dashboard domain
- `dashboard_ssl_enabled` - SSL enabled for dashboard
- `letsencrypt_email` - Email for SSL certificates

---

### 7. Login Attempts Table (`login_attempts`)

Tracks login attempts for security monitoring.

```sql
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    ip_address TEXT NOT NULL,
    success INTEGER DEFAULT 0,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## Migration Process

### Automatic Migration (Recommended)

Simply restart the application after upgrading:

```bash
docker-compose restart gui
```

The database will be automatically migrated on first request.

### Manual Migration Check

If you want to verify the migration, check the logs:

```bash
docker-compose logs gui | grep -i "alter table\|create table"
```

### Rollback (If Needed)

If something goes wrong, restore from backup:

```bash
# Stop the application
docker-compose down

# Restore database backup
cp /opt/wharftales/backups/database_backup_YYYYMMDD.sqlite /opt/wharftales/data/database.sqlite

# Start the application
docker-compose up -d
```

---

## Backup Recommendations

### Automatic Backups

The system automatically backs up the database before major operations.

### Manual Backup

```bash
# Create a backup
docker exec wharftales-gui-1 cp /app/data/database.sqlite /app/data/database_backup_$(date +%Y%m%d_%H%M%S).sqlite

# Copy to host
docker cp wharftales-gui-1:/app/data/database_backup_*.sqlite ./backups/
```

### Scheduled Backups

Add to crontab:

```bash
# Daily backup at 2 AM
0 2 * * * docker exec wharftales-gui-1 cp /app/data/database.sqlite /app/data/database_backup_$(date +\%Y\%m\%d).sqlite
```

---

## Troubleshooting

### Error: "no such column: role"

**Solution:** The database wasn't migrated. Restart the container:

```bash
docker-compose restart gui
```

### Error: "table users already exists"

**Solution:** This is normal. The system uses `CREATE TABLE IF NOT EXISTS`.

### Error: "UNIQUE constraint failed"

**Solution:** Trying to add duplicate data. Check the unique constraints in the schema.

### Database Corruption

**Solution:** Restore from backup:

```bash
docker-compose down
cp /opt/wharftales/backups/database_backup_latest.sqlite /opt/wharftales/data/database.sqlite
docker-compose up -d
```

---

## Schema Version History

### v2.0.1 (Current)
- ✅ Added RBAC tables (`user_permissions`, `site_permissions`, `audit_log`)
- ✅ Added `role`, `totp_secret`, `totp_enabled` to `users` table
- ✅ Added `owner_id`, `redis_enabled`, `redis_container` to `sites` table
- ✅ Automatic migration for existing installations

### v2.0.0
- ✅ Added 2FA support
- ✅ Added user management
- ✅ Added settings table

### v1.x
- Initial schema
- Basic site management

---

## Development Notes

### Adding New Columns

When adding new columns, always:

1. Add to `CREATE TABLE IF NOT EXISTS` statement
2. Add migration in `try/catch` block
3. Use `DEFAULT` values for existing rows
4. Test on fresh install AND upgrade

Example:

```php
// In CREATE TABLE
new_column TEXT DEFAULT 'default_value'

// In migration section
try {
    $pdo->exec("ALTER TABLE sites ADD COLUMN new_column TEXT DEFAULT 'default_value'");
} catch (PDOException $e) {
    // Column already exists, ignore
}
```

### Adding New Tables

Always use `CREATE TABLE IF NOT EXISTS` to avoid errors on re-runs.

---

**Last Updated:** 2025-10-06  
**Schema Version:** 2.0.1
