#!/usr/bin/env php
<?php
/**
 * Add PHP version support to sites
 */

require_once __DIR__ . '/includes/functions.php';

echo "=== PHP Version Migration ===\n\n";

try {
    $db = initDatabase();
    
    // Add php_version column
    echo "Adding php_version column to sites table...\n";
    try {
        $db->exec("ALTER TABLE sites ADD COLUMN php_version TEXT DEFAULT '8.3'");
        echo "✓ Added php_version column\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "⚠ Column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Set default for existing sites
    echo "\nSetting default PHP version for existing sites...\n";
    $stmt = $db->exec("UPDATE sites SET php_version = '8.3' WHERE php_version IS NULL OR php_version = ''");
    echo "✓ Updated existing sites to PHP 8.3\n";
    
    // Show current sites
    echo "\nCurrent sites:\n";
    $stmt = $db->query("SELECT id, name, type, php_version FROM sites");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['name']} ({$row['type']}): PHP {$row['php_version']}\n";
    }
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
