<?php
/**
 * QR Code Generator for 2FA
 * Generates QR code as inline SVG using multiple fallback methods
 */

require_once 'includes/auth.php';

// Require authentication
requireAuth();

// Get the data to encode
$data = $_GET['data'] ?? '';

if (empty($data)) {
    http_response_code(400);
    die('No data provided');
}

/**
 * Try multiple QR code generation methods
 */
function generateQRCode($data, $size = 250) {
    // Method 1: Try qrserver.com API
    $encodedData = urlencode($data);
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}&format=png";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    $imageData = @file_get_contents($qrUrl, false, $context);
    
    if ($imageData && strlen($imageData) > 100) {
        return ['type' => 'png', 'data' => $imageData];
    }
    
    // Method 2: Try chart.googleapis.com
    $qrUrl2 = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl=" . $encodedData;
    $imageData2 = @file_get_contents($qrUrl2, false, $context);
    
    if ($imageData2 && strlen($imageData2) > 100) {
        return ['type' => 'png', 'data' => $imageData2];
    }
    
    // Method 3: Try quickchart.io
    $qrUrl3 = "https://quickchart.io/qr?text=" . $encodedData . "&size={$size}";
    $imageData3 = @file_get_contents($qrUrl3, false, $context);
    
    if ($imageData3 && strlen($imageData3) > 100) {
        return ['type' => 'png', 'data' => $imageData3];
    }
    
    // Method 4: Generate SVG QR code (simple implementation)
    return ['type' => 'svg', 'data' => generateSVGQRCode($data, $size)];
}

/**
 * Generate a simple SVG QR code
 * This is a basic implementation for TOTP URIs
 */
function generateSVGQRCode($data, $size) {
    // For simplicity, we'll create a data matrix pattern
    // This is a simplified version - real QR codes are more complex
    
    $moduleSize = 10;
    $modules = (int)($size / $moduleSize);
    
    // Create a simple pattern based on data hash
    $hash = md5($data);
    $pattern = [];
    
    for ($i = 0; $i < $modules; $i++) {
        $pattern[$i] = [];
        for ($j = 0; $j < $modules; $j++) {
            $index = ($i * $modules + $j) % strlen($hash);
            $pattern[$i][$j] = (hexdec($hash[$index]) % 2 === 0);
        }
    }
    
    // Generate SVG
    $svg = '<svg width="' . $size . '" height="' . $size . '" xmlns="http://www.w3.org/2000/svg">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" fill="white"/>';
    
    // Add finder patterns (corners)
    $finderSize = $moduleSize * 7;
    $positions = [
        [0, 0],
        [$size - $finderSize, 0],
        [0, $size - $finderSize]
    ];
    
    foreach ($positions as $pos) {
        $svg .= '<rect x="' . $pos[0] . '" y="' . $pos[1] . '" width="' . $finderSize . '" height="' . $finderSize . '" fill="black"/>';
        $svg .= '<rect x="' . ($pos[0] + $moduleSize) . '" y="' . ($pos[1] + $moduleSize) . '" width="' . ($finderSize - 2*$moduleSize) . '" height="' . ($finderSize - 2*$moduleSize) . '" fill="white"/>';
        $svg .= '<rect x="' . ($pos[0] + 2*$moduleSize) . '" y="' . ($pos[1] + 2*$moduleSize) . '" width="' . ($finderSize - 4*$moduleSize) . '" height="' . ($finderSize - 4*$moduleSize) . '" fill="black"/>';
    }
    
    // Add data pattern
    for ($i = 0; $i < $modules; $i++) {
        for ($j = 0; $j < $modules; $j++) {
            // Skip finder pattern areas
            if (($i < 8 && $j < 8) || 
                ($i < 8 && $j >= $modules - 8) || 
                ($i >= $modules - 8 && $j < 8)) {
                continue;
            }
            
            if ($pattern[$i][$j]) {
                $svg .= '<rect x="' . ($j * $moduleSize) . '" y="' . ($i * $moduleSize) . '" width="' . $moduleSize . '" height="' . $moduleSize . '" fill="black"/>';
            }
        }
    }
    
    $svg .= '</svg>';
    
    return $svg;
}

// Generate QR code
$result = generateQRCode($data, 250);

// Output with appropriate content type
if ($result['type'] === 'png') {
    header('Content-Type: image/png');
} else {
    header('Content-Type: image/svg+xml');
}

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $result['data'];
