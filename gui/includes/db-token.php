<?php
// Database access token management

define('DB_TOKEN_SECRET', 'wharftales_db_token_secret_' . md5(__DIR__));
define('DB_TOKEN_EXPIRY', 300); // 5 minutes

function generateDatabaseToken($db, $siteId, $userId) {
    // Generate token data
    $data = [
        'site_id' => $siteId,
        'user_id' => $userId,
        'expires' => time() + DB_TOKEN_EXPIRY,
        'nonce' => bin2hex(random_bytes(16))
    ];
    
    // Create signature
    $payload = json_encode($data);
    $signature = hash_hmac('sha256', $payload, DB_TOKEN_SECRET);
    
    // Combine payload and signature
    $token = base64_encode($payload . '.' . $signature);
    
    // Store token in database for validation and logging
    $stmt = $db->prepare("INSERT INTO db_access_tokens (token, site_id, user_id, expires_at, created_at) VALUES (?, ?, ?, ?, ?)");
    $expiresAt = date('Y-m-d H:i:s', $data['expires']);
    $createdAt = date('Y-m-d H:i:s');
    $stmt->execute([$token, $siteId, $userId, $expiresAt, $createdAt]);
    
    return $token;
}

function validateDatabaseToken($db, $token) {
    if (empty($token)) {
        return false;
    }
    
    try {
        // Decode token
        $decoded = base64_decode($token);
        $parts = explode('.', $decoded);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        list($payload, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $payload, DB_TOKEN_SECRET);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        // Parse data
        $data = json_decode($payload, true);
        if (!$data) {
            return false;
        }
        
        // Check expiration
        if (time() > $data['expires']) {
            return false;
        }
        
        // Check if token exists in database and hasn't been used
        $stmt = $db->prepare("SELECT * FROM db_access_tokens WHERE token = ? AND used = 0");
        $stmt->execute([$token]);
        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenRecord) {
            return false;
        }
        
        // Mark token as used
        $stmt = $db->prepare("UPDATE db_access_tokens SET used = 1, used_at = ? WHERE token = ?");
        $stmt->execute([date('Y-m-d H:i:s'), $token]);
        
        return $data;
        
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        return false;
    }
}

function logDatabaseAccess($db, $siteId, $userId, $action, $details = '') {
    $stmt = $db->prepare("INSERT INTO db_access_logs (site_id, user_id, action, details, created_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$siteId, $userId, $action, $details, date('Y-m-d H:i:s')]);
}

function createDatabaseTokenTables($db) {
    // Create tokens table
    $db->exec("CREATE TABLE IF NOT EXISTS db_access_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT NOT NULL,
        site_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        used INTEGER DEFAULT 0,
        used_at DATETIME
    )");
    
    // Create access logs table
    $db->exec("CREATE TABLE IF NOT EXISTS db_access_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        details TEXT,
        created_at DATETIME NOT NULL
    )");
    
    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tokens_site ON db_access_tokens(site_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tokens_expires ON db_access_tokens(expires_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_site ON db_access_logs(site_id)");
}

// Clean up expired tokens (call periodically)
function cleanupExpiredTokens($db) {
    $stmt = $db->prepare("DELETE FROM db_access_tokens WHERE expires_at < ?");
    $stmt->execute([date('Y-m-d H:i:s', time() - 3600)]); // Delete tokens older than 1 hour
}
