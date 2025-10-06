<?php
/**
 * Database Migration for RBAC and 2FA
 * Adds user roles, permissions, 2FA support, and Redis configuration
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

echo "Webbadeploy RBAC & 2FA Migration\n";
echo "==================================\n\n";

try {
    $db = initAuthDatabase();
    
    // 1. Add columns to users table for roles and 2FA
    echo "1. Updating users table...\n";
    
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    if (!in_array('role', $columnNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user'");
        echo "   ✓ Added 'role' column (admin/user)\n";
    } else {
        echo "   ✓ 'role' column already exists\n";
    }
    
    if (!in_array('can_create_sites', $columnNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN can_create_sites INTEGER DEFAULT 1");
        echo "   ✓ Added 'can_create_sites' column\n";
    } else {
        echo "   ✓ 'can_create_sites' column already exists\n";
    }
    
    if (!in_array('totp_secret', $columnNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN totp_secret TEXT");
        echo "   ✓ Added 'totp_secret' column for 2FA\n";
    } else {
        echo "   ✓ 'totp_secret' column already exists\n";
    }
    
    if (!in_array('totp_enabled', $columnNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN totp_enabled INTEGER DEFAULT 0");
        echo "   ✓ Added 'totp_enabled' column\n";
    } else {
        echo "   ✓ 'totp_enabled' column already exists\n";
    }
    
    if (!in_array('totp_backup_codes', $columnNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN totp_backup_codes TEXT");
        echo "   ✓ Added 'totp_backup_codes' column\n";
    } else {
        echo "   ✓ 'totp_backup_codes' column already exists\n";
    }
    
    // Set first user as admin if exists
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        $db->exec("UPDATE users SET role = 'admin' WHERE id = (SELECT MIN(id) FROM users)");
        echo "   ✓ Set first user as admin\n";
    }
    
    // 2. Create site_permissions table
    echo "\n2. Creating site_permissions table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS site_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        site_id INTEGER NOT NULL,
        permission TEXT DEFAULT 'view',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, site_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
    )");
    echo "   ✓ Created site_permissions table\n";
    
    // 3. Update sites table for owner tracking
    echo "\n3. Updating sites table...\n";
    $mainDb = initDatabase();
    $result = $mainDb->query("PRAGMA table_info(sites)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    if (!in_array('owner_id', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN owner_id INTEGER DEFAULT 1");
        echo "   ✓ Added 'owner_id' column\n";
    } else {
        echo "   ✓ 'owner_id' column already exists\n";
    }
    
    if (!in_array('redis_enabled', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN redis_enabled INTEGER DEFAULT 0");
        echo "   ✓ Added 'redis_enabled' column\n";
    } else {
        echo "   ✓ 'redis_enabled' column already exists\n";
    }
    
    if (!in_array('redis_host', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN redis_host TEXT");
        echo "   ✓ Added 'redis_host' column\n";
    } else {
        echo "   ✓ 'redis_host' column already exists\n";
    }
    
    if (!in_array('redis_port', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN redis_port INTEGER DEFAULT 6379");
        echo "   ✓ Added 'redis_port' column\n";
    } else {
        echo "   ✓ 'redis_port' column already exists\n";
    }
    
    if (!in_array('redis_password', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN redis_password TEXT");
        echo "   ✓ Added 'redis_password' column\n";
    } else {
        echo "   ✓ 'redis_password' column already exists\n";
    }
    
    // 4. Create audit log table
    echo "\n4. Creating audit_log table...\n";
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
    echo "   ✓ Created audit_log table\n";
    
    // 5. Create settings for global flags
    echo "\n5. Setting up global settings...\n";
    $mainDb->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('users_can_create_sites', '1')");
    echo "   ✓ Added 'users_can_create_sites' setting\n";
    
    echo "\n✅ Migration completed successfully!\n\n";
    echo "Summary:\n";
    echo "--------\n";
    echo "• Added role-based access control (admin/user)\n";
    echo "• Added 2FA/TOTP support (optional)\n";
    echo "• Added site ownership and permissions\n";
    echo "• Added Redis support for all app types\n";
    echo "• Added audit logging\n";
    echo "• Added user site creation control flag\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
