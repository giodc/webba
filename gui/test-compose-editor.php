<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Starting...<br>";

require_once 'includes/functions.php';
echo "Step 2: Functions loaded<br>";

require_once 'includes/auth.php';
echo "Step 3: Auth loaded<br>";

requireAuth();
echo "Step 4: Auth checked<br>";

$currentUser = getCurrentUser();
echo "Step 5: Got user: " . print_r($currentUser, true) . "<br>";

$db = initDatabase();
echo "Step 6: DB initialized<br>";

$config = getComposeConfig($db, null);
echo "Step 7: Got config: " . ($config ? "YES" : "NO") . "<br>";

if ($config) {
    echo "Config ID: " . $config['id'] . "<br>";
    echo "YAML length: " . strlen($config['compose_yaml']) . " bytes<br>";
}

echo "<br>All tests passed!";
