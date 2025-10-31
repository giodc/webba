<?php
/**
 * Test API responses - delete after testing
 */
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    die('Please login first');
}

$db = initDatabase();

echo "<h2>Testing Update Functions</h2>";

// Test 1: Check for updates
echo "<h3>1. Check for Updates</h3>";
try {
    $result = checkForUpdates(true);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Test 2: Get update status
echo "<h3>2. Get Update Status</h3>";
try {
    $status = getUpdateStatus();
    echo "<pre>";
    print_r($status);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Test 3: Get current version
echo "<h3>3. Get Current Version</h3>";
try {
    $version = getCurrentVersion();
    echo "<p>Current version: <strong>$version</strong></p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Test 4: Check settings
echo "<h3>4. Update Settings</h3>";
try {
    $settings = [
        'update_check_enabled' => getSetting($db, 'update_check_enabled', 'not set'),
        'auto_update_enabled' => getSetting($db, 'auto_update_enabled', 'not set'),
        'versions_url' => getSetting($db, 'versions_url', 'not set'),
        'update_notification' => getSetting($db, 'update_notification', 'not set'),
    ];
    echo "<pre>";
    print_r($settings);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='/settings.php'>Go to Settings</a></p>";
echo "<p style='color:red'><strong>Delete this file after testing:</strong> rm /opt/wharftales/gui/test-api.php</p>";
?>
