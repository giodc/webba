<?php
// Direct API test - bypasses authentication
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();
header('Content-Type: application/json');

try {
    require_once 'includes/functions.php';
    
    $db = initDatabase();
    $updateInfo = checkForUpdates(false);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'update_available' => $updateInfo['update_available'] ?? false,
            'current_version' => $updateInfo['current_version'] ?? 'unknown',
            'latest_version' => $updateInfo['latest_version'] ?? 'unknown',
        ],
        'info' => $updateInfo
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

ob_end_flush();
?>
