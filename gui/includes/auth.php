<?php
/**
 * Webbadeploy Authentication System
 * Lightweight, secure authentication for Webbadeploy
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

/**
 * Initialize authentication database
 */
function initAuthDatabase() {
    $db = new PDO('sqlite:/app/data/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME,
        failed_attempts INTEGER DEFAULT 0,
        locked_until DATETIME
    )");
    
    // Create sessions table for tracking
    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        success INTEGER DEFAULT 0,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    return $db;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php');
        exit;
    }
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Check if account is locked due to failed attempts
 */
function isAccountLocked($db, $username) {
    $stmt = $db->prepare("SELECT locked_until FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['locked_until']) {
        $lockedUntil = strtotime($user['locked_until']);
        if ($lockedUntil > time()) {
            return true;
        } else {
            // Unlock account
            $stmt = $db->prepare("UPDATE users SET locked_until = NULL, failed_attempts = 0 WHERE username = ?");
            $stmt->execute([$username]);
        }
    }
    
    return false;
}

/**
 * Check rate limiting based on IP
 */
function checkRateLimit($db, $ipAddress) {
    // Allow max 5 attempts per IP in last 15 minutes
    $stmt = $db->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                         WHERE ip_address = ? 
                         AND attempted_at > datetime('now', '-15 minutes')
                         AND success = 0");
    $stmt->execute([$ipAddress]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['attempts'] < 5;
}

/**
 * Log login attempt
 */
function logLoginAttempt($db, $username, $ipAddress, $success) {
    $stmt = $db->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)");
    $stmt->execute([$username, $ipAddress, $success ? 1 : 0]);
}

/**
 * Authenticate user
 */
function authenticateUser($username, $password) {
    $db = initAuthDatabase();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Check rate limiting
    if (!checkRateLimit($db, $ipAddress)) {
        return ['success' => false, 'error' => 'Too many failed attempts. Please try again later.'];
    }
    
    // Check if account is locked
    if (isAccountLocked($db, $username)) {
        return ['success' => false, 'error' => 'Account is temporarily locked. Please try again later.'];
    }
    
    // Get user from database
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        logLoginAttempt($db, $username, $ipAddress, false);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Increment failed attempts
        $failedAttempts = $user['failed_attempts'] + 1;
        $lockedUntil = null;
        
        // Lock account after 5 failed attempts for 15 minutes
        if ($failedAttempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        }
        
        $stmt = $db->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
        $stmt->execute([$failedAttempts, $lockedUntil, $user['id']]);
        
        logLoginAttempt($db, $username, $ipAddress, false);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }
    
    // Successful login
    // Reset failed attempts
    $stmt = $db->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Set session variables
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['last_regeneration'] = time();
    
    logLoginAttempt($db, $username, $ipAddress, true);
    
    return ['success' => true, 'user' => $user];
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Create new user
 */
function createUser($username, $password, $email = null) {
    $db = initAuthDatabase();
    
    // Validate password strength
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters long.'];
    }
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Username already exists.'];
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert user
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, email) VALUES (?, ?, ?)");
        $stmt->execute([$username, $passwordHash, $email]);
        
        return ['success' => true, 'user_id' => $db->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Failed to create user: ' . $e->getMessage()];
    }
}

/**
 * Check if any users exist
 */
function hasUsers() {
    $db = initAuthDatabase();
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = initAuthDatabase();
    $stmt = $db->prepare("SELECT id, username, email, created_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
