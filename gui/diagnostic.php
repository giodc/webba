<?php
// Diagnostic script to check what's wrong
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Webbadeploy Diagnostic</h1>";

// Check PHP version
echo "<h2>PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// Check if files exist
echo "<h2>File Check</h2>";
$files = [
    'includes/functions.php',
    'includes/auth.php',
    'includes/updater.php',
    'includes/update-config.php',
    'api.php',
    'index.php'
];

foreach ($files as $file) {
    $exists = file_exists($file);
    $readable = is_readable($file);
    echo "$file: " . ($exists ? "✓ Exists" : "✗ Missing") . " | " . ($readable ? "✓ Readable" : "✗ Not readable") . "<br>";
}

// Check database
echo "<h2>Database Check</h2>";
try {
    require_once 'includes/functions.php';
    $db = initDatabase();
    echo "✓ Database connection successful<br>";
    
    // Check tables
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "<br>";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Check auth
echo "<h2>Auth Check</h2>";
try {
    require_once 'includes/auth.php';
    echo "✓ Auth system loaded<br>";
    echo "Logged in: " . (isLoggedIn() ? "Yes" : "No") . "<br>";
} catch (Exception $e) {
    echo "✗ Auth error: " . $e->getMessage() . "<br>";
}

// Check Docker
echo "<h2>Docker Check</h2>";
exec('docker --version 2>&1', $dockerVersion, $dockerCode);
if ($dockerCode === 0) {
    echo "✓ Docker: " . implode(" ", $dockerVersion) . "<br>";
} else {
    echo "✗ Docker not available<br>";
}

// Check permissions
echo "<h2>Permissions Check</h2>";
$dataDir = '/app/data';
echo "Data directory: $dataDir<br>";
echo "Exists: " . (is_dir($dataDir) ? "✓ Yes" : "✗ No") . "<br>";
echo "Writable: " . (is_writable($dataDir) ? "✓ Yes" : "✗ No") . "<br>";

// Check recent errors
echo "<h2>Recent PHP Errors</h2>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $errors = file($errorLog);
    $recentErrors = array_slice($errors, -10);
    echo "<pre>" . htmlspecialchars(implode("", $recentErrors)) . "</pre>";
} else {
    echo "No error log found at: $errorLog<br>";
}

echo "<h2>All Checks Complete</h2>";
echo "<a href='/'>Go to Dashboard</a>";
