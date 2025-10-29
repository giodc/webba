# Proposal: Store Docker Compose Configurations in Database

## Current Architecture vs. Proposed Architecture

### Current Approach (File-Based)
```
WharfTales currently stores docker-compose.yml files on disk:
├── /opt/wharftales/docker-compose.yml (main Traefik config)
└── /opt/wharftales/apps/
    ├── wordpress/sites/{container_name}/docker-compose.yml
    ├── php/sites/{container_name}/docker-compose.yml
    └── laravel/sites/{container_name}/docker-compose.yml
```

**Problems:**
1. ❌ File permission issues (as we just experienced)
2. ❌ PHP stat cache problems
3. ❌ Difficult to version/track changes
4. ❌ No atomic updates
5. ❌ Hard to audit who changed what
6. ❌ Backup complexity
7. ❌ Race conditions on concurrent edits

### Proposed Approach (Database-Based)

Store all docker-compose configurations in the database, generate files on-demand.

**Benefits:**
1. ✅ No file permission issues
2. ✅ Atomic database transactions
3. ✅ Full version history
4. ✅ Audit trail built-in
5. ✅ Easy backups (just backup database)
6. ✅ Concurrent access handled by DB
7. ✅ Can rollback to previous versions
8. ✅ Easier to implement multi-server deployments

## How Coolify Does It

Coolify (Laravel-based) uses a **database-first approach**:

1. **Database Models**: All configurations stored in PostgreSQL/MySQL
2. **Dynamic Generation**: Docker compose files generated from database on-demand
3. **Temporary Files**: Compose files written to temp locations only when needed
4. **Version Control**: Database stores configuration history
5. **API-Driven**: All changes go through API → Database → File generation

### Coolify's Architecture
```
User Action → API Endpoint → Database Update → Generate Compose File → Docker Deploy
```

## Proposed Implementation for WharfTales

### Phase 1: Database Schema Changes

Add new tables to store compose configurations:

```sql
-- Store docker-compose configurations
CREATE TABLE IF NOT EXISTS compose_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_type TEXT NOT NULL,  -- 'main', 'site'
    site_id INTEGER,            -- NULL for main config
    compose_yaml TEXT NOT NULL, -- Full YAML content
    version INTEGER DEFAULT 1,
    is_active INTEGER DEFAULT 1,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Store configuration history for rollback
CREATE TABLE IF NOT EXISTS compose_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_id INTEGER NOT NULL,
    compose_yaml TEXT NOT NULL,
    version INTEGER NOT NULL,
    changed_by INTEGER,
    change_description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (config_id) REFERENCES compose_configs(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Store individual configuration parameters for easy updates
CREATE TABLE IF NOT EXISTS compose_parameters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_id INTEGER NOT NULL,
    param_key TEXT NOT NULL,      -- e.g., 'letsencrypt_email', 'traefik_domain'
    param_value TEXT,
    param_type TEXT DEFAULT 'string', -- 'string', 'integer', 'boolean', 'json'
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (config_id) REFERENCES compose_configs(id) ON DELETE CASCADE,
    UNIQUE(config_id, param_key)
);
```

### Phase 2: Core Functions

Create helper functions to manage compose configurations:

```php
// Get active compose configuration
function getComposeConfig($db, $siteId = null) {
    if ($siteId === null) {
        // Get main Traefik config
        $stmt = $db->prepare("SELECT * FROM compose_configs WHERE config_type = 'main' AND is_active = 1 LIMIT 1");
    } else {
        // Get site-specific config
        $stmt = $db->prepare("SELECT * FROM compose_configs WHERE config_type = 'site' AND site_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$siteId]);
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Update compose configuration with versioning
function updateComposeConfig($db, $configId, $newYaml, $userId, $description = '') {
    $db->beginTransaction();
    try {
        // Get current config
        $stmt = $db->prepare("SELECT * FROM compose_configs WHERE id = ?");
        $stmt->execute([$configId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Save to history
        $stmt = $db->prepare("INSERT INTO compose_history (config_id, compose_yaml, version, changed_by, change_description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$configId, $current['compose_yaml'], $current['version'], $userId, $description]);
        
        // Update current config
        $newVersion = $current['version'] + 1;
        $stmt = $db->prepare("UPDATE compose_configs SET compose_yaml = ?, version = ? WHERE id = ?");
        $stmt->execute([$newYaml, $newVersion, $configId]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// Update a specific parameter (like Let's Encrypt email)
function updateComposeParameter($db, $configId, $paramKey, $paramValue, $userId) {
    // Update parameter table
    $stmt = $db->prepare("INSERT OR REPLACE INTO compose_parameters (config_id, param_key, param_value, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$configId, $paramKey, $paramValue]);
    
    // Regenerate YAML from parameters
    $yaml = generateComposeYaml($db, $configId);
    
    // Update config with versioning
    updateComposeConfig($db, $configId, $yaml, $userId, "Updated $paramKey");
}

// Generate docker-compose.yml file from database
function generateComposeFile($db, $configId, $outputPath) {
    $config = getComposeConfig($db, $configId);
    if (!$config) {
        throw new Exception("Configuration not found");
    }
    
    // Write to file
    $result = file_put_contents($outputPath, $config['compose_yaml']);
    if ($result === false) {
        throw new Exception("Failed to write compose file");
    }
    
    return $outputPath;
}

// Rollback to previous version
function rollbackComposeConfig($db, $configId, $version, $userId) {
    $db->beginTransaction();
    try {
        // Get historical version
        $stmt = $db->prepare("SELECT * FROM compose_history WHERE config_id = ? AND version = ?");
        $stmt->execute([$configId, $version]);
        $historical = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$historical) {
            throw new Exception("Version not found");
        }
        
        // Update current config
        updateComposeConfig($db, $configId, $historical['compose_yaml'], $userId, "Rollback to version $version");
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
```

### Phase 3: Migration Strategy

Migrate existing file-based configs to database:

```php
function migrateComposeToDatabase($db) {
    echo "Starting compose configuration migration...\n";
    
    // 1. Migrate main Traefik config
    $mainComposePath = '/opt/wharftales/docker-compose.yml';
    if (file_exists($mainComposePath)) {
        $yaml = file_get_contents($mainComposePath);
        
        // Extract parameters
        preg_match('/acme\.email=([^\s"]+)/', $yaml, $matches);
        $letsencryptEmail = $matches[1] ?? 'admin@example.com';
        
        // Insert into database
        $stmt = $db->prepare("INSERT INTO compose_configs (config_type, compose_yaml, created_by) VALUES ('main', ?, 1)");
        $stmt->execute([$yaml]);
        $configId = $db->lastInsertId();
        
        // Store parameters
        $stmt = $db->prepare("INSERT INTO compose_parameters (config_id, param_key, param_value) VALUES (?, 'letsencrypt_email', ?)");
        $stmt->execute([$configId, $letsencryptEmail]);
        
        echo "✓ Migrated main Traefik configuration\n";
    }
    
    // 2. Migrate site-specific configs
    $sites = getAllSites($db);
    foreach ($sites as $site) {
        $composePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/docker-compose.yml";
        
        if (file_exists($composePath)) {
            $yaml = file_get_contents($composePath);
            
            $stmt = $db->prepare("INSERT INTO compose_configs (config_type, site_id, compose_yaml, created_by) VALUES ('site', ?, ?, ?)");
            $stmt->execute([$site['id'], $yaml, $site['owner_id']]);
            
            echo "✓ Migrated config for site: {$site['name']}\n";
        }
    }
    
    echo "Migration complete!\n";
}
```

### Phase 4: Update Settings Page

Replace file operations with database operations:

```php
// OLD CODE (settings.php)
if (isset($_POST['letsencrypt_email'])) {
    $newEmail = trim($_POST['letsencrypt_email']);
    
    if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        clearstatcache(true);
        $fileContent = @file_get_contents($dockerComposePath);
        // ... complex file operations ...
    }
}

// NEW CODE (settings.php)
if (isset($_POST['letsencrypt_email'])) {
    $newEmail = trim($_POST['letsencrypt_email']);
    
    if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            // Get main config
            $config = getComposeConfig($db, null);
            
            // Update parameter
            updateComposeParameter($db, $config['id'], 'letsencrypt_email', $newEmail, $currentUser['id']);
            
            // Regenerate file for Docker
            generateComposeFile($db, $config['id'], '/opt/wharftales/docker-compose.yml');
            
            $successMessage = 'Let\'s Encrypt email updated successfully!';
            
            // Log the change
            logAudit($db, $currentUser['id'], 'update_letsencrypt_email', 'compose_config', $config['id'], json_encode(['email' => $newEmail]));
        } catch (Exception $e) {
            $errorMessage = 'Failed to update email: ' . $e->getMessage();
        }
    }
}
```

## Implementation Roadmap

### Step 1: Database Schema (1-2 hours)
- [ ] Create migration script for new tables
- [ ] Add indexes for performance
- [ ] Test schema with sample data

### Step 2: Core Functions (2-3 hours)
- [ ] Implement CRUD functions for compose configs
- [ ] Add versioning logic
- [ ] Add rollback functionality
- [ ] Write unit tests

### Step 3: Migration Script (1-2 hours)
- [ ] Create migration script to move files → DB
- [ ] Add validation to ensure data integrity
- [ ] Create backup before migration
- [ ] Test migration on sample data

### Step 4: Update Settings Page (2-3 hours)
- [ ] Replace file operations with DB operations
- [ ] Add version history UI
- [ ] Add rollback button
- [ ] Test all settings updates

### Step 5: Update API Endpoints (3-4 hours)
- [ ] Update site creation to use DB
- [ ] Update site updates to use DB
- [ ] Update site deletion to use DB
- [ ] Ensure backward compatibility

### Step 6: Testing & Documentation (2-3 hours)
- [ ] Test all workflows end-to-end
- [ ] Update documentation
- [ ] Create rollback plan
- [ ] Performance testing

**Total Estimated Time: 12-18 hours**

## Comparison: Current vs. Proposed

| Feature | Current (File-Based) | Proposed (Database) |
|---------|---------------------|---------------------|
| **Permissions** | Complex (file system) | Simple (DB only) |
| **Versioning** | Manual backups | Automatic |
| **Audit Trail** | None | Built-in |
| **Rollback** | Manual | One-click |
| **Concurrent Access** | Race conditions | DB handles it |
| **Backup** | Multiple files | Single DB |
| **Multi-Server** | File sync needed | DB replication |
| **Performance** | Fast (direct file) | Fast (cached) |
| **Complexity** | Low | Medium |

## Hybrid Approach (Recommended)

For maximum compatibility and performance, use a **hybrid approach**:

1. **Source of Truth**: Database
2. **Working Files**: Generated on-demand
3. **Caching**: Keep generated files cached
4. **Sync**: Regenerate files only when DB changes

```php
function getComposeFilePath($db, $siteId = null, $forceRegenerate = false) {
    $config = getComposeConfig($db, $siteId);
    
    if ($siteId === null) {
        $filePath = '/opt/wharftales/docker-compose.yml';
    } else {
        $site = getSiteById($db, $siteId);
        $filePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/docker-compose.yml";
    }
    
    // Check if file exists and is up-to-date
    if (!$forceRegenerate && file_exists($filePath)) {
        $fileTime = filemtime($filePath);
        $dbTime = strtotime($config['updated_at']);
        
        if ($fileTime >= $dbTime) {
            // File is up-to-date, use it
            return $filePath;
        }
    }
    
    // Regenerate file from database
    generateComposeFile($db, $config['id'], $filePath);
    
    return $filePath;
}
```

## Security Considerations

1. **Access Control**: Only admins can modify main config
2. **Validation**: Validate YAML before saving to DB
3. **Sanitization**: Sanitize all inputs
4. **Audit Logging**: Log all configuration changes
5. **Encryption**: Consider encrypting sensitive values (passwords, keys)

## Backward Compatibility

During transition period:
1. Keep file-based system working
2. Add DB layer alongside
3. Gradually migrate endpoints
4. Deprecate file operations
5. Remove file operations after testing

## Conclusion

**Recommendation: Implement the Database-Based Approach**

**Why:**
- Solves current file permission issues permanently
- Aligns with modern practices (like Coolify)
- Enables advanced features (versioning, rollback, audit)
- Better scalability for multi-server deployments
- Easier to maintain and debug

**When:**
- Can be implemented incrementally
- Start with main Traefik config (highest pain point)
- Gradually migrate site configs
- Maintain backward compatibility during transition

**Effort:**
- Initial implementation: 12-18 hours
- Testing & refinement: 4-6 hours
- Documentation: 2-3 hours
- **Total: ~20-25 hours** for complete implementation

This approach transforms WharfTales from a file-based system to a modern, database-driven platform, similar to Coolify's architecture.
