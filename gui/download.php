<?php
require_once 'includes/auth.php';

// Require authentication
if (!isLoggedIn()) {
    http_response_code(401);
    die('Unauthorized');
}

// Get the requested file
$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    die('No file specified');
}

// Security: Only allow files from the backups directory
// Prevent directory traversal attacks
$file = basename($file); // Remove any path components
$backupPath = '/app/data/backups/' . $file;

// Check if file exists
if (!file_exists($backupPath)) {
    http_response_code(404);
    die('File not found');
}

// Verify it's a .sql file
if (!str_ends_with($file, '.sql')) {
    http_response_code(403);
    die('Invalid file type');
}

// Get file info
$fileSize = filesize($backupPath);
$fileName = $file;

// Set headers for download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output the file
readfile($backupPath);
exit;
