<?php
/**
 * Password Reset Script
 * Run this from command line to reset a user's password
 */

require_once 'includes/functions.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line\n");
}

$db = initDatabase();

// Get username
$username = $argv[1] ?? 'admin';
$newPassword = $argv[2] ?? 'admin';

// Hash the password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update the password
$stmt = $db->prepare('UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE username = ?');
$result = $stmt->execute([$hashedPassword, $username]);

if ($result) {
    echo "✅ Password reset successfully!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Username: $username\n";
    echo "Password: $newPassword\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\nYou can now login at: http://192.168.64.4:3000/\n";
} else {
    echo "❌ Failed to reset password\n";
    exit(1);
}
