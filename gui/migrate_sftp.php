<?php
require_once 'includes/functions.php';

echo "Starting SFTP migration...\n";

try {
    $db = initDatabase();
    
    // Check if columns already exist
    $result = $db->query("PRAGMA table_info(sites)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    // Add sftp_enabled column if it doesn't exist
    if (!in_array('sftp_enabled', $columnNames)) {
        echo "Adding sftp_enabled column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN sftp_enabled INTEGER DEFAULT 0");
        echo "✓ sftp_enabled column added\n";
    } else {
        echo "✓ sftp_enabled column already exists\n";
    }
    
    // Add sftp_username column if it doesn't exist
    if (!in_array('sftp_username', $columnNames)) {
        echo "Adding sftp_username column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN sftp_username TEXT");
        echo "✓ sftp_username column added\n";
    } else {
        echo "✓ sftp_username column already exists\n";
    }
    
    // Add sftp_password column if it doesn't exist
    if (!in_array('sftp_password', $columnNames)) {
        echo "Adding sftp_password column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN sftp_password TEXT");
        echo "✓ sftp_password column added\n";
    } else {
        echo "✓ sftp_password column already exists\n";
    }
    
    // Add sftp_port column if it doesn't exist
    if (!in_array('sftp_port', $columnNames)) {
        echo "Adding sftp_port column...\n";
        $db->exec("ALTER TABLE sites ADD COLUMN sftp_port INTEGER");
        echo "✓ sftp_port column added\n";
    } else {
        echo "✓ sftp_port column already exists\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
