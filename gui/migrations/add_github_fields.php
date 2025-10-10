<?php
// Migration: Add GitHub deployment fields to sites table

require_once __DIR__ . '/../includes/functions.php';

$db = initDatabase();

try {
    // Check if columns already exist
    $columns = $db->query("PRAGMA table_info(sites)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    $newColumns = [
        'github_repo' => 'TEXT',
        'github_branch' => 'TEXT DEFAULT "main"',
        'github_token' => 'TEXT',
        'github_last_commit' => 'TEXT',
        'github_last_pull' => 'DATETIME',
        'deployment_method' => 'TEXT DEFAULT "manual"'
    ];
    
    foreach ($newColumns as $column => $type) {
        if (!in_array($column, $columnNames)) {
            echo "Adding column: $column\n";
            $db->exec("ALTER TABLE sites ADD COLUMN $column $type");
        } else {
            echo "Column already exists: $column\n";
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
