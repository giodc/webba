<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/db-token.php';

// Require authentication
requireAuth();

$db = initDatabase();
$currentUser = getCurrentUser();

// Debug: Check if user data is valid
if (!$currentUser || !isset($currentUser['id'])) {
    http_response_code(403);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Session Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="alert alert-danger">
                <h4><i class="bi bi-exclamation-triangle"></i> Session Error</h4>
                <p>Your session is invalid. Please log out and log in again.</p>
                <a href="/logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </body>
    </html>
    ');
}

// Get and validate token
$token = $_GET['token'] ?? '';
$validated = validateDatabaseToken($db, $token);

if (!$validated) {
    http_response_code(403);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="alert alert-danger">
                <h4><i class="bi bi-exclamation-triangle"></i> Access Denied</h4>
                <p>Invalid or expired database access token. Tokens expire after 5 minutes for security.</p>
                <a href="/" class="btn btn-primary">Return to Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    ');
}

// Get site information
$site = getSiteById($db, $validated['site_id']);

if (!$site) {
    die('Site not found');
}

// Check if user has permission to access this site's database
// Note: Currently all authenticated users can access all databases
// TODO: Implement per-site user permissions when multi-user support is added
$userRole = $currentUser['role'] ?? '';
$userId = $currentUser['id'] ?? 0;
$siteUserId = $site['user_id'] ?? null;

// If site has user_id and user is not admin, check ownership
if ($siteUserId !== null && $userRole !== 'admin' && $siteUserId !== $userId) {
    http_response_code(403);
    die('You do not have permission to access this database');
}

// Log database access
logDatabaseAccess($db, $site['id'], $currentUser['id'], 'database_manager_opened', 'Site: ' . $site['name']);

// Get database credentials
$dbType = $site['db_type'] ?? 'shared';

if ($dbType === 'dedicated') {
    // Dedicated database
    $dbHost = $site['container_name'] . '_db';
    $dbName = 'wordpress';
    $dbUser = 'wordpress';
    $dbPassword = $site['db_password'] ?? '';
} else {
    // Shared database
    $dbHost = 'webbadeploy_db';
    $dbName = 'webbadeploy';
    $dbUser = 'webbadeploy';
    $dbPassword = 'webbadeploy_pass';
}

// Set up Adminer auto-login
$_GET['server'] = $dbHost;
$_GET['username'] = $dbUser;
$_GET['db'] = $dbName;

// Store password in session for Adminer
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['adminer_password'] = $dbPassword;
$_SESSION['adminer_site_name'] = $site['name'];
$_SESSION['adminer_site_id'] = $site['id'];

// Custom Adminer class for auto-login and customization
class AdminerCustom {
    function name() {
        return 'Webbadeploy Database Manager';
    }
    
    function credentials() {
        // Auto-login with stored credentials
        return array(
            $_GET['server'],
            $_GET['username'],
            $_SESSION['adminer_password'] ?? ''
        );
    }
    
    function database() {
        return $_GET['db'];
    }
    
    function login($login, $password) {
        // Always allow login with session credentials
        return true;
    }
    
    function permanentLogin() {
        // Keep session for 5 minutes
        return false;
    }
    
    function operators() {
        // Return default operators
        return array();
    }
    
    function csp() {
        // Return Content Security Policy headers
        return array();
    }
}

function adminer_object() {
    return new AdminerCustom;
}

// Include Adminer
include 'adminer.php';
