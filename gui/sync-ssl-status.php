<?php
/**
 * Sync SSL certificate status from acme.json to database
 * This script reads the acme.json file and updates the database
 * to reflect which sites have certificates issued.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication
requireAuth();

// Check if user has admin permissions
$currentUser = getCurrentUser();
if (!hasPermission($currentUser['id'], 'manage_ssl')) {
    die('Unauthorized: You do not have permission to sync SSL status.');
}

$db = initDatabase();

// Try to read acme.json
$acmePaths = [
    '/opt/wharftales/ssl/acme.json',
    '/app/ssl/acme.json'
];

$acmeFile = null;
foreach ($acmePaths as $path) {
    if (file_exists($path) && is_readable($path)) {
        $acmeFile = $path;
        break;
    }
}

if (!$acmeFile) {
    die("Error: acme.json file not found or not readable.\n");
}

$acmeContent = @file_get_contents($acmeFile);
if (!$acmeContent) {
    die("Error: Could not read acme.json file.\n");
}

$acmeData = json_decode($acmeContent, true);
if (!$acmeData || !isset($acmeData['letsencrypt']['Certificates'])) {
    die("Error: Invalid acme.json format.\n");
}

$certificates = $acmeData['letsencrypt']['Certificates'];
if ($certificates === null || !is_array($certificates)) {
    echo "No certificates found in acme.json.\n";
    $certificates = [];
}

// Get all domains with certificates
$certDomains = [];
foreach ($certificates as $cert) {
    if (isset($cert['domain']['main'])) {
        $certDomains[] = $cert['domain']['main'];
    }
    if (isset($cert['domain']['sans']) && is_array($cert['domain']['sans'])) {
        $certDomains = array_merge($certDomains, $cert['domain']['sans']);
    }
}

echo "Found " . count($certDomains) . " certificate(s) in acme.json:\n";
foreach ($certDomains as $domain) {
    echo "  - $domain\n";
}

// Get all sites from database
$sites = getAllSites($db);
$updated = 0;
$errors = 0;

echo "\nSyncing certificate status to database...\n";

foreach ($sites as $site) {
    if ($site['ssl'] != 1) {
        continue; // Skip sites without SSL enabled
    }
    
    $domain = $site['domain'];
    $hasCert = in_array($domain, $certDomains);
    $currentStatus = isset($site['ssl_cert_issued']) ? $site['ssl_cert_issued'] : 0;
    
    if ($hasCert && $currentStatus != 1) {
        // Certificate exists but not marked in DB
        if (markCertificateIssued($db, $site['id'])) {
            echo "  ✓ Marked certificate as ISSUED for: $domain\n";
            $updated++;
        } else {
            echo "  ✗ Failed to update: $domain\n";
            $errors++;
        }
    } elseif (!$hasCert && $currentStatus == 1) {
        // Certificate doesn't exist but marked in DB
        if (markCertificateRemoved($db, $site['id'])) {
            echo "  ✓ Marked certificate as REMOVED for: $domain\n";
            $updated++;
        } else {
            echo "  ✗ Failed to update: $domain\n";
            $errors++;
        }
    } else {
        echo "  - No change needed for: $domain (status: " . ($hasCert ? "issued" : "pending") . ")\n";
    }
}

echo "\nSync complete!\n";
echo "Updated: $updated\n";
echo "Errors: $errors\n";
