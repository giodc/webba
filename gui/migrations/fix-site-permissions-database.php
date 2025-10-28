#!/usr/bin/env php
<?php
/**
 * Migration: Fix Site Permissions Database Mismatch
 * 
 * This migration fixes the bug where site_permissions were being read from
 * the wrong database, causing "Access denied" errors for regular users.
 * 
 * Changes:
 * - Ensures site_permissions table exists in main database
 * - Migrates any permissions from auth database to main database (if any)
 * - Verifies column names are correct (permission_level not permission)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== Site Permissions Database Fix Migration ===\n\n";

try {
    $mainDb = initDatabase();
    $authDb = initAuthDatabase();
    
    echo "Step 1: Checking main database for site_permissions table...\n";
    
    // Check if site_permissions exists in main database
    $result = $mainDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name='site_permissions'");
    $tableExists = $result->fetch();
    
    if ($tableExists) {
        echo "  ✓ site_permissions table exists in main database\n";
        
        // Check column structure
        $result = $mainDb->query("PRAGMA table_info(site_permissions)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');
        
        if (in_array('permission_level', $columnNames)) {
            echo "  ✓ Column 'permission_level' exists (correct)\n";
        } else if (in_array('permission', $columnNames)) {
            echo "  ⚠ Column 'permission' exists (needs migration to 'permission_level')\n";
            
            // Rename column
            echo "  → Migrating column 'permission' to 'permission_level'...\n";
            
            // SQLite doesn't support ALTER COLUMN, so we need to recreate the table
            $mainDb->exec("BEGIN TRANSACTION");
            
            // Create new table with correct schema
            $mainDb->exec("CREATE TABLE site_permissions_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                site_id INTEGER NOT NULL,
                permission_level TEXT DEFAULT 'view',
                granted_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, site_id)
            )");
            
            // Copy data
            $mainDb->exec("INSERT INTO site_permissions_new (id, user_id, site_id, permission_level, granted_by, created_at)
                          SELECT id, user_id, site_id, permission, granted_by, created_at FROM site_permissions");
            
            // Drop old table
            $mainDb->exec("DROP TABLE site_permissions");
            
            // Rename new table
            $mainDb->exec("ALTER TABLE site_permissions_new RENAME TO site_permissions");
            
            $mainDb->exec("COMMIT");
            
            echo "  ✓ Column migrated successfully\n";
        } else {
            echo "  ⚠ Neither 'permission' nor 'permission_level' column found - table may be corrupted\n";
        }
    } else {
        echo "  ⚠ site_permissions table does NOT exist in main database\n";
        echo "  → Creating table...\n";
        
        $mainDb->exec("CREATE TABLE site_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            site_id INTEGER NOT NULL,
            permission_level TEXT DEFAULT 'view',
            granted_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, site_id)
        )");
        
        echo "  ✓ Table created successfully\n";
    }
    
    echo "\nStep 2: Checking auth database for orphaned site_permissions...\n";
    
    // Check if site_permissions exists in auth database (wrong location)
    $result = $authDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name='site_permissions'");
    $authTableExists = $result->fetch();
    
    if ($authTableExists) {
        echo "  ⚠ Found site_permissions in auth database (wrong location)\n";
        echo "  → Migrating data to main database...\n";
        
        // Get all permissions from auth database
        $stmt = $authDb->query("SELECT * FROM site_permissions");
        $authPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migratedCount = 0;
        foreach ($authPermissions as $perm) {
            // Check if permission already exists in main database
            $stmt = $mainDb->prepare("SELECT id FROM site_permissions WHERE user_id = ? AND site_id = ?");
            $stmt->execute([$perm['user_id'], $perm['site_id']]);
            
            if (!$stmt->fetch()) {
                // Insert into main database
                $permissionLevel = $perm['permission_level'] ?? $perm['permission'] ?? 'view';
                $stmt = $mainDb->prepare("INSERT INTO site_permissions (user_id, site_id, permission_level, granted_by, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $perm['user_id'],
                    $perm['site_id'],
                    $permissionLevel,
                    $perm['granted_by'] ?? null,
                    $perm['created_at'] ?? date('Y-m-d H:i:s')
                ]);
                $migratedCount++;
            }
        }
        
        echo "  ✓ Migrated $migratedCount permission(s) to main database\n";
        
        // Optionally drop the table from auth database
        echo "  → Removing site_permissions from auth database...\n";
        $authDb->exec("DROP TABLE site_permissions");
        echo "  ✓ Cleanup complete\n";
    } else {
        echo "  ✓ No orphaned permissions in auth database\n";
    }
    
    echo "\nStep 3: Verifying permissions...\n";
    
    // Count permissions in main database
    $stmt = $mainDb->query("SELECT COUNT(*) as count FROM site_permissions");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $permCount = $result['count'];
    
    echo "  ✓ Total permissions in main database: $permCount\n";
    
    // Show sample permissions
    if ($permCount > 0) {
        $stmt = $mainDb->query("SELECT sp.*, u.username, s.name as site_name 
                                FROM site_permissions sp
                                LEFT JOIN users u ON sp.user_id = u.id
                                LEFT JOIN sites s ON sp.site_id = s.id
                                LIMIT 5");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n  Sample permissions:\n";
        foreach ($samples as $sample) {
            echo "    - User: {$sample['username']} | Site: {$sample['site_name']} | Level: {$sample['permission_level']}\n";
        }
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "✓ Site permissions database structure is now correct\n";
    echo "✓ Regular users should now be able to access their assigned sites\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
