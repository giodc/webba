#!/usr/bin/env php
<?php
/**
 * Set versions URL to local file for testing
 */

chdir('/var/www/html');
require_once '/var/www/html/includes/functions.php';

try {
    $db = initDatabase();
    
    // Set to local file
    $localPath = '/opt/wharftales/versions.json';
    setSetting($db, 'versions_url', $localPath);
    
    echo "✓ Updated versions_url to: $localPath\n";
    echo "\n";
    echo "You can now test the update system with the local file.\n";
    echo "Go to Settings → System Updates → Click 'Check Now'\n";
    echo "\n";
    
    // Show current setting
    $currentUrl = getSetting($db, 'versions_url', 'not set');
    echo "Current versions_url: $currentUrl\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
