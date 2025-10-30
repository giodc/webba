<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/db-token.php';

// Redirect to SSL URL if dashboard has SSL enabled and request is from :9000
redirectToSSLIfEnabled();

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
$siteType = $site['type'];

if ($dbType === 'dedicated' || in_array($dbType, ['mysql', 'postgresql'])) {
    // Dedicated database (WordPress, PHP, Laravel)
    $dbHost = $site['container_name'] . '_db';
    
    if ($siteType === 'wordpress') {
        $dbName = 'wordpress';
        $dbUser = 'wordpress';
    } else {
        // PHP or Laravel
        $dbName = 'appdb';
        $dbUser = 'appuser';
    }
    
    $dbPassword = $site['db_password'] ?? '';
} else {
    // Shared database
    $dbHost = 'wharftales_db';
    $dbName = 'wharftales';
    $dbUser = 'wharftales';
    $dbPassword = 'wharftales_pass';
}

// Auto-login: Pre-fill credentials and auto-submit
?>
<!DOCTYPE html>
<html>
<head>
    <title>Connecting to Database...</title>
</head>
<body>
    <h3>Connecting to <?= htmlspecialchars($site['name']) ?> database...</h3>
    <form method="post" action="adminer.php" id="loginForm">
        <input type="hidden" name="auth[driver]" value="server">
        <input type="hidden" name="auth[server]" value="<?= htmlspecialchars($dbHost) ?>">
        <input type="hidden" name="auth[username]" value="<?= htmlspecialchars($dbUser) ?>">
        <input type="hidden" name="auth[password]" value="<?= htmlspecialchars($dbPassword) ?>">
        <input type="hidden" name="auth[db]" value="<?= htmlspecialchars($dbName) ?>">
    </form>
    <script>
        // Auto-submit the form
        document.getElementById('loginForm').submit();
    </script>
</body>
</html>
