#!/usr/bin/env php
<?php
/**
 * Migration Script: Move Docker Compose Configurations to Database
 * 
 * This script migrates existing docker-compose.yml files to the database.
 * Run this once to migrate from file-based to database-based storage.
 */

require_once __DIR__ . '/includes/functions.php';

echo "=== Docker Compose Configuration Migration ===\n\n";

try {
    $db = initDatabase();
    
    // Check if table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='compose_configs'");
    if (!$result->fetch()) {
        echo "❌ Error: compose_configs table not found. Please update includes/functions.php first.\n";
        exit(1);
    }
    
    echo "✓ Database connection established\n";
    echo "✓ compose_configs table exists\n\n";
    
    $migratedCount = 0;
    $skippedCount = 0;
    
    // ========================================================================
    // 1. Migrate Main Traefik Configuration
    // ========================================================================
    echo "Step 1: Migrating main Traefik configuration...\n";
    
    $mainComposePath = '/opt/webbadeploy/docker-compose.yml';
    
    if (file_exists($mainComposePath)) {
        $yaml = file_get_contents($mainComposePath);
        
        if ($yaml && !empty(trim($yaml))) {
            // Check if already migrated
            $existing = getComposeConfig($db, null);
            
            if ($existing) {
                echo "  ⚠ Main config already exists in database (skipping)\n";
                $skippedCount++;
            } else {
                // Save to database (user_id = 1 for migration)
                saveComposeConfig($db, $yaml, 1, null);
                echo "  ✓ Migrated main Traefik configuration (" . strlen($yaml) . " bytes)\n";
                $migratedCount++;
            }
        } else {
            echo "  ⚠ Main compose file is empty\n";
        }
    } else {
        echo "  ⚠ Main compose file not found at: $mainComposePath\n";
    }
    
    echo "\n";
    
    // ========================================================================
    // 2. Migrate Site-Specific Configurations
    // ========================================================================
    echo "Step 2: Migrating site-specific configurations...\n";
    
    $sites = getAllSites($db);
    
    if (empty($sites)) {
        echo "  ℹ No sites found in database\n";
    } else {
        foreach ($sites as $site) {
            $siteId = $site['id'];
            $siteName = $site['name'];
            $siteType = $site['type'];
            $containerName = $site['container_name'];
            
            // Try both container path and host path
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
                    // Check if already migrated
                    $existing = getComposeConfig($db, $siteId);
                    
                    if ($existing) {
                        echo "  ⚠ Site '$siteName' (ID: $siteId) already in database (skipping)\n";
                        $skippedCount++;
                    } else {
                        // Save to database
                        saveComposeConfig($db, $yaml, $site['owner_id'] ?? 1, $siteId);
                        echo "  ✓ Migrated site '$siteName' (ID: $siteId, " . strlen($yaml) . " bytes)\n";
                        $migratedCount++;
                    }
                } else {
                    echo "  ⚠ Site '$siteName' compose file is empty\n";
                }
            } else {
                echo "  ⚠ Site '$siteName' (ID: $siteId) - compose file not found\n";
            }
        }
    }
    
    echo "\n";
    echo "=== Migration Complete ===\n";
    echo "Migrated: $migratedCount\n";
    echo "Skipped: $skippedCount\n";
    echo "\n";
    
    // ========================================================================
    // 3. Verify Migration
    // ========================================================================
    echo "Verification:\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM compose_configs WHERE config_type = 'main'");
    $mainCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  Main configs in database: $mainCount\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM compose_configs WHERE config_type = 'site'");
    $siteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  Site configs in database: $siteCount\n";
    
    echo "\n";
    echo "✓ Migration successful!\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Test the settings page to ensure Let's Encrypt email updates work\n";
    echo "2. Test creating/updating sites\n";
    echo "3. Original files are still on disk (not deleted for safety)\n";
    echo "4. You can manually delete old files after verifying everything works\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
