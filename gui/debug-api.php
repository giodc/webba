<?php
/**
 * Debug API responses - Shows raw output
 * Usage: http://192.168.68.190:9000/debug-api.php?action=ACTION&id=ID
 */

// Capture everything
ob_start();

require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    die('Please login first');
}

$action = $_GET['action'] ?? 'get_site';
$id = $_GET['id'] ?? '1';

echo "<h2>Debug API Call: $action</h2>";
echo "<p>ID: $id</p>";

// Make the actual API call
$url = "http://192.168.68.190:9000/api.php?action=$action&id=$id";
echo "<h3>Calling: $url</h3>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

// Split headers and body
list($headers, $body) = explode("\r\n\r\n", $response, 2);

echo "<h3>HTTP Status: " . $info['http_code'] . "</h3>";

echo "<h3>Response Headers:</h3>";
echo "<pre>" . htmlspecialchars($headers) . "</pre>";

echo "<h3>Response Body (first 1000 chars):</h3>";
echo "<pre>" . htmlspecialchars(substr($body, 0, 1000)) . "</pre>";

echo "<h3>Response Body Length: " . strlen($body) . " bytes</h3>";

if (empty($body)) {
    echo "<p style='color:red'><strong>EMPTY RESPONSE - This is the problem!</strong></p>";
} else {
    // Try to parse as JSON
    $json = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<h3>Parsed JSON:</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "<p style='color:red'><strong>JSON Parse Error: " . json_last_error_msg() . "</strong></p>";
        echo "<h3>Raw bytes (hex dump of first 100 bytes):</h3>";
        echo "<pre>" . bin2hex(substr($body, 0, 100)) . "</pre>";
    }
}

echo "<hr>";
echo "<h3>Test Other Endpoints:</h3>";
echo "<ul>";
echo "<li><a href='?action=get_site&id=1'>get_site</a></li>";
echo "<li><a href='?action=get_stats&id=1'>get_stats</a></li>";
echo "<li><a href='?action=get_site_containers&id=1'>get_site_containers</a></li>";
echo "<li><a href='?action=check_updates'>check_updates</a></li>";
echo "</ul>";

echo "<p style='color:red'><strong>Delete this file after debugging:</strong> rm /opt/wharftales/gui/debug-api.php</p>";
?>
