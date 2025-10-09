#!/usr/bin/env php
<?php
/**
 * Import Missing Docker Compose Configurations
 * 
 * This script finds sites that don't have configs in the database
 * and imports them from disk.
 */

require_once __DIR__ . '/includes/functions.php';

echo "=== Import Missing Compose Configurations ===\n\n";

try {
    $db = initDatabase();
    
    // Get all sites
    $sites = getAllSites($db);
    
    echo "Found " . count($sites) . " sites in database\n\n";
    
    $imported = 0;
    $skipped = 0;
    $notFound = 0;
    
    foreach ($sites as $site) {
        $siteId = $site['id'];
        $siteName = $site['name'];
        $siteType = $site['type'];
        $containerName = $site['container_name'];
        
        // Check if config already exists in database
        $existing = getComposeConfig($db, $siteId);
        
        if ($existing) {
            echo "  ⚠ Site '$siteName' (ID: $siteId) - Already in database (skipping)\n";
            $skipped++;
            continue;
        }
        
        // Try to find the docker-compose.yml file
        $possiblePaths = [
            "/app/apps/{$siteType}/sites/{$containerName}/docker-compose.yml",
            "/opt/webbadeploy/apps/{$siteType}/sites/{$containerName}/docker-compose.yml"
        ];
        
        $composePath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $composePath = $path;
                break;
            }
        }
        
        if ($composePath) {
            $yaml = file_get_contents($composePath);
            
            if ($yaml && !empty(trim($yaml))) {
                // Save to database
                saveComposeConfig($db, $yaml, $site['owner_id'] ?? 1, $siteId);
                echo "  ✓ Imported site '$siteName' (ID: $siteId, " . strlen($yaml) . " bytes)\n";
                $imported++;
            } else {
                echo "  ⚠ Site '$siteName' (ID: $siteId) - File is empty\n";
                $notFound++;
            }
        } else {
            echo "  ⚠ Site '$siteName' (ID: $siteId) - No docker-compose.yml file found\n";
            $notFound++;
        }
    }
    
    echo "\n";
    echo "=== Import Complete ===\n";
    echo "Imported: $imported\n";
    echo "Skipped (already in DB): $skipped\n";
    echo "Not found: $notFound\n";
    echo "\n";
    
    if ($imported > 0) {
        echo "✓ Successfully imported $imported site configuration(s)!\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Import failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
