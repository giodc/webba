<?php
/**
 * Database Migration Script
 * Run this to update your database schema
 */

require_once 'includes/functions.php';

echo "WebBadeploy Database Migration\n";
echo "================================\n\n";

try {
    $db = initDatabase();
    
    // Check if ssl_config column exists
    $result = $db->query("PRAGMA table_info(sites)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $hasSSLConfig = false;
    $hasSFTPEnabled = false;
    $hasSFTPPort = false;
    $hasSFTPUsername = false;
    $hasSFTPPassword = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'ssl_config') {
            $hasSSLConfig = true;
        }
        if ($column['name'] === 'sftp_enabled') {
            $hasSFTPEnabled = true;
        }
        if ($column['name'] === 'sftp_port') {
            $hasSFTPPort = true;
        }
        if ($column['name'] === 'sftp_username') {
            $hasSFTPUsername = true;
        }
        if ($column['name'] === 'sftp_password') {
            $hasSFTPPassword = true;
        }
    }
    
    // Add missing columns
    if (!$hasSSLConfig) {
        echo "Adding ssl_config column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN ssl_config TEXT");
        echo "✓ Added ssl_config column\n";
    } else {
        echo "✓ ssl_config column already exists\n";
    }
    
    if (!$hasSFTPEnabled) {
        echo "Adding sftp_enabled column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN sftp_enabled INTEGER DEFAULT 0");
        echo "✓ Added sftp_enabled column\n";
    } else {
        echo "✓ sftp_enabled column already exists\n";
    }
    
    if (!$hasSFTPPort) {
        echo "Adding sftp_port column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN sftp_port INTEGER");
        echo "✓ Added sftp_port column\n";
    } else {
        echo "✓ sftp_port column already exists\n";
    }
    
    if (!$hasSFTPUsername) {
        echo "Adding sftp_username column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN sftp_username TEXT");
        echo "✓ Added sftp_username column\n";
    } else {
        echo "✓ sftp_username column already exists\n";
    }
    
    if (!$hasSFTPPassword) {
        echo "Adding sftp_password column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN sftp_password TEXT");
        echo "✓ Added sftp_password column\n";
    } else {
        echo "✓ sftp_password column already exists\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
