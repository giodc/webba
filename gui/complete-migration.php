<?php
/**
 * Complete the migration for sites table
 */

require_once 'includes/functions.php';

echo "Completing Sites Table Migration\n";
echo "=================================\n\n";

try {
    $mainDb = initDatabase();
    
    // Get current columns
    $result = $mainDb->query("PRAGMA table_info(sites)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    // Add owner_id
    if (!in_array('owner_id', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN owner_id INTEGER DEFAULT 1");
        echo "✓ Added 'owner_id' column\n";
    } else {
        echo "✓ 'owner_id' column already exists\n";
    }
    
    // Add redis columns
    if (!in_array('redis_enabled', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN redis_enabled INTEGER DEFAULT 0");
        echo "✓ Added 'redis_enabled' column\n";
    } else {
        echo "✓ 'redis_enabled' column already exists\n";
    }
    
    if (!in_array('redis_host', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN redis_host TEXT");
        echo "✓ Added 'redis_host' column\n";
    } else {
        echo "✓ 'redis_host' column already exists\n";
    }
    
    if (!in_array('redis_port', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN redis_port INTEGER DEFAULT 6379");
        echo "✓ Added 'redis_port' column\n";
    } else {
        echo "✓ 'redis_port' column already exists\n";
    }
    
    if (!in_array('redis_password', $columnNames)) {
        $mainDb->exec("ALTER TABLE sites ADD COLUMN redis_password TEXT");
        echo "✓ Added 'redis_password' column\n";
    } else {
        echo "✓ 'redis_password' column already exists\n";
    }
    
    // Create audit_log table
    $mainDb->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        resource_type TEXT,
        resource_id INTEGER,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ Created audit_log table\n";
    
    // Add global settings
    $mainDb->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('users_can_create_sites', '1')");
    echo "✓ Added 'users_can_create_sites' setting\n";
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
