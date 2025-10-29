<?php
/**
 * WordPress Security Hardening Configuration
 * Add this to wp-config.php for enhanced security
 */

// Disable file editing from WordPress admin
define('DISALLOW_FILE_EDIT', true);

// Disable plugin/theme installation and updates from admin
define('DISALLOW_FILE_MODS', true);

// Force SSL for admin area (if SSL is enabled)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    define('FORCE_SSL_ADMIN', true);
}

// Limit post revisions to save database space
define('WP_POST_REVISIONS', 3);

// Set autosave interval to 5 minutes
define('AUTOSAVE_INTERVAL', 300);

// Disable WordPress debug mode in production
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Increase memory limit if needed
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Security keys and salts - REPLACE THESE WITH UNIQUE VALUES
// Generate new keys at: https://api.wordpress.org/secret-key/1.1/salt/
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

// Disable XML-RPC if not needed (prevents brute force attacks)
add_filter('xmlrpc_enabled', '__return_false');

// Remove WordPress version from head
remove_action('wp_head', 'wp_generator');

// Disable pingback
add_filter('wp_headers', function($headers) {
    unset($headers['X-Pingback']);
    return $headers;
});

// Add security headers
add_action('send_headers', function() {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
});

// Limit login attempts (basic implementation)
add_action('wp_login_failed', function($username) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $attempts = get_transient('login_attempts_' . $ip);
    if (!$attempts) {
        $attempts = 0;
    }
    $attempts++;
    set_transient('login_attempts_' . $ip, $attempts, 900); // 15 minutes
    
    if ($attempts >= 5) {
        wp_die('Too many failed login attempts. Please try again in 15 minutes.');
    }
});

// Clear login attempts on successful login
add_action('wp_login', function() {
    $ip = $_SERVER['REMOTE_ADDR'];
    delete_transient('login_attempts_' . $ip);
});
