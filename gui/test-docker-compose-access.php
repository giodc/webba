<?php
/**
 * Diagnostic script to test docker-compose.yml access
 * Access via: http://your-server:9000/test-docker-compose-access.php
 */

header('Content-Type: text/plain');

echo "=== Docker Compose File Access Test ===\n\n";

$dockerComposePath = '/opt/wharftales/docker-compose.yml';

echo "Testing file: $dockerComposePath\n\n";

// Test 1: file_exists()
echo "1. file_exists(): ";
clearstatcache(true, $dockerComposePath);
$exists = file_exists($dockerComposePath);
echo $exists ? "TRUE\n" : "FALSE\n";

// Test 2: is_file()
echo "2. is_file(): ";
clearstatcache(true, $dockerComposePath);
$isFile = is_file($dockerComposePath);
echo $isFile ? "TRUE\n" : "FALSE\n";

// Test 3: is_readable()
echo "3. is_readable(): ";
clearstatcache(true, $dockerComposePath);
$readable = is_readable($dockerComposePath);
echo $readable ? "TRUE\n" : "FALSE\n";

// Test 4: is_writable()
echo "4. is_writable(): ";
clearstatcache(true, $dockerComposePath);
$writable = is_writable($dockerComposePath);
echo $writable ? "TRUE\n" : "FALSE\n";

// Test 5: file_get_contents()
echo "5. file_get_contents(): ";
clearstatcache(true, $dockerComposePath);
$content = @file_get_contents($dockerComposePath);
if ($content !== false) {
    echo "SUCCESS (read " . strlen($content) . " bytes)\n";
} else {
    echo "FAILED\n";
    $error = error_get_last();
    if ($error) {
        echo "   Error: " . $error['message'] . "\n";
    }
}

// Test 6: stat()
echo "6. stat(): ";
clearstatcache(true, $dockerComposePath);
$stat = @stat($dockerComposePath);
if ($stat !== false) {
    echo "SUCCESS\n";
    echo "   Size: " . $stat['size'] . " bytes\n";
    echo "   Mode: " . decoct($stat['mode'] & 0777) . "\n";
    echo "   UID: " . $stat['uid'] . "\n";
    echo "   GID: " . $stat['gid'] . "\n";
} else {
    echo "FAILED\n";
}

// Test 7: Current user
echo "\n7. Current PHP user: ";
echo get_current_user() . "\n";

// Test 8: Process user
echo "8. Process user (posix): ";
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $processUser = posix_getpwuid(posix_geteuid());
    echo $processUser['name'] . " (UID: " . posix_geteuid() . ")\n";
} else {
    echo "POSIX functions not available\n";
}

// Test 9: exec test
echo "\n9. exec('ls -la'): ";
$output = [];
$returnCode = 0;
exec("ls -la $dockerComposePath 2>&1", $output, $returnCode);
if ($returnCode === 0) {
    echo "SUCCESS\n";
    echo "   " . implode("\n   ", $output) . "\n";
} else {
    echo "FAILED (code: $returnCode)\n";
    echo "   " . implode("\n   ", $output) . "\n";
}

// Test 10: exec cat
echo "\n10. exec('cat'): ";
$output = [];
$returnCode = 0;
exec("cat $dockerComposePath 2>&1", $output, $returnCode);
if ($returnCode === 0) {
    echo "SUCCESS (read " . count($output) . " lines)\n";
} else {
    echo "FAILED (code: $returnCode)\n";
    echo "   " . implode("\n   ", $output) . "\n";
}

// Test 11: realpath
echo "\n11. realpath(): ";
$realPath = realpath($dockerComposePath);
if ($realPath !== false) {
    echo "$realPath\n";
} else {
    echo "FAILED\n";
}

// Test 12: Directory listing
echo "\n12. Parent directory listing:\n";
$parentDir = dirname($dockerComposePath);
if (is_dir($parentDir)) {
    $files = @scandir($parentDir);
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (strpos($file, 'docker-compose') !== false) {
                echo "   Found: $file\n";
            }
        }
    } else {
        echo "   Cannot read directory\n";
    }
} else {
    echo "   Parent directory not accessible\n";
}

echo "\n=== End of Test ===\n";
echo "\nIf file_exists() is FALSE but exec('ls') works, this is a PHP stat cache issue.\n";
echo "If exec('cat') works but file_get_contents() fails, this is a permissions issue.\n";
?>
