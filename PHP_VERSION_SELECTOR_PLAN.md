# PHP Version Selector Implementation Plan

## Overview
Add ability to select and switch PHP versions for PHP, Laravel, and WordPress sites.

## Supported PHP Versions
- PHP 7.4 (Legacy)
- PHP 8.0
- PHP 8.1
- PHP 8.2
- PHP 8.3 (Latest, Default)

## Implementation Steps

### 1. Database Schema Update
Add `php_version` column to `sites` table:
```sql
ALTER TABLE sites ADD COLUMN php_version TEXT DEFAULT '8.3';
```

### 2. UI Changes

#### A. Create Site Modal (`gui/index.php`)
Add PHP version selector after "Application Type":
```html
<div class="col-md-6" id="phpVersionSelector" style="display:none;">
    <label class="form-label">PHP Version</label>
    <select class="form-select" name="php_version">
        <option value="8.3" selected>PHP 8.3 (Latest, Recommended)</option>
        <option value="8.2">PHP 8.2</option>
        <option value="8.1">PHP 8.1</option>
        <option value="8.0">PHP 8.0</option>
        <option value="7.4">PHP 7.4 (Legacy)</option>
    </select>
    <div class="form-text">Choose PHP version for your application</div>
</div>
```

Show/hide based on app type (show for PHP, Laravel, WordPress).

#### B. Edit Site Page (`gui/edit-site.php`)
Add PHP version switcher in "Advanced" or "Settings" section:
```html
<div class="mb-3">
    <label class="form-label">PHP Version</label>
    <select class="form-select" id="phpVersion">
        <option value="8.3">PHP 8.3</option>
        <option value="8.2">PHP 8.2</option>
        <option value="8.1">PHP 8.1</option>
        <option value="8.0">PHP 8.0</option>
        <option value="7.4">PHP 7.4</option>
    </select>
    <button class="btn btn-warning mt-2" onclick="changePHPVersion()">
        <i class="bi bi-arrow-repeat"></i> Switch PHP Version
    </button>
    <div class="form-text">
        ⚠️ Changing PHP version will restart the container
    </div>
</div>
```

### 3. API Changes (`gui/api.php`)

#### A. Create Site
Update `createSite` action to accept `php_version` parameter:
```php
$phpVersion = $_POST['php_version'] ?? '8.3';

// Save to database
$stmt = $db->prepare("INSERT INTO sites (..., php_version) VALUES (..., ?)");
$stmt->execute([..., $phpVersion]);
```

#### B. Update Site
Add new action `change_php_version`:
```php
case "change_php_version":
    $siteId = $_POST['site_id'];
    $newVersion = $_POST['php_version'];
    
    // Update database
    $stmt = $db->prepare("UPDATE sites SET php_version = ? WHERE id = ?");
    $stmt->execute([$newVersion, $siteId]);
    
    // Regenerate docker-compose with new PHP version
    $site = getSiteById($db, $siteId);
    $newCompose = createPHPDockerCompose($site, []);
    
    // Save and restart
    saveComposeConfig($db, $newCompose, $userId, $siteId);
    file_put_contents($composePath, $newCompose);
    executeDockerCompose($composePath, "up -d --force-recreate");
    break;
```

### 4. Docker Compose Generation

#### A. PHP Sites (`createPHPDockerCompose`)
```php
function createPHPDockerCompose($site, $config, &$generatedPassword = null) {
    $phpVersion = $site['php_version'] ?? '8.3';
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: php:{$phpVersion}-apache  # Dynamic PHP version
    container_name: {$containerName}
    ...
```

#### B. Laravel Sites (`createLaravelDockerCompose`)
```php
function createLaravelDockerCompose($site, $config, &$generatedPassword = null) {
    $phpVersion = $site['php_version'] ?? '8.3';
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: php:{$phpVersion}-apache  # Dynamic PHP version
    ...
```

#### C. WordPress Sites (`createWordPressDockerCompose`)
```php
function createWordPressDockerCompose($site, $config, &$generatedPassword = null) {
    $phpVersion = $site['php_version'] ?? '8.3';
    
    // WordPress uses special images
    $wpImage = "wordpress:{$phpVersion}-apache";
    // Or use: wordpress:php{$phpVersion}
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: {$wpImage}  # Dynamic WordPress+PHP version
    ...
```

### 5. Migration Script
Create `gui/migrate-php-version.php`:
```php
<?php
require_once 'includes/functions.php';

$db = initDatabase();

// Add column if doesn't exist
try {
    $db->exec("ALTER TABLE sites ADD COLUMN php_version TEXT DEFAULT '8.3'");
    echo "✓ Added php_version column\n";
} catch (Exception $e) {
    echo "Column already exists or error: " . $e->getMessage() . "\n";
}

// Set default for existing sites
$db->exec("UPDATE sites SET php_version = '8.3' WHERE php_version IS NULL");
echo "✓ Set default PHP version for existing sites\n";
```

### 6. Docker Image Notes

**Available Images:**
- PHP: `php:8.3-apache`, `php:8.2-apache`, `php:8.1-apache`, `php:8.0-apache`, `php:7.4-apache`
- WordPress: `wordpress:php8.3-apache`, `wordpress:php8.2-apache`, etc.
  - Or: `wordpress:6.4-php8.3-apache` (specific WP version)

### 7. Testing Checklist
- [ ] Create new PHP site with PHP 8.3
- [ ] Create new PHP site with PHP 7.4
- [ ] Switch existing site from 8.3 to 8.1
- [ ] Verify container uses correct PHP version (`docker exec site php -v`)
- [ ] Create Laravel site with different PHP versions
- [ ] Create WordPress site with different PHP versions
- [ ] Test that site works after version switch
- [ ] Verify database stores PHP version correctly

### 8. User Experience Flow

**Creating Site:**
1. User selects "PHP Application"
2. PHP version selector appears
3. User chooses PHP 8.2
4. Site created with PHP 8.2

**Switching Version:**
1. User goes to site edit page
2. Clicks "Advanced" or "Settings" tab
3. Selects new PHP version (8.3)
4. Clicks "Switch PHP Version"
5. Confirmation modal: "This will restart your site. Continue?"
6. Container recreated with new PHP version
7. Success message with verification command

### 9. Safety Features
- ⚠️ Warning before switching versions
- ✅ Backup recommendation before major version changes (7.4 → 8.x)
- ✅ Show current PHP version prominently
- ✅ Compatibility notes (e.g., "PHP 7.4 reaches EOL")

### 10. Future Enhancements
- [ ] PHP extension selector per version
- [ ] Automatic compatibility check
- [ ] One-click rollback if site breaks
- [ ] Performance comparison between versions
- [ ] Memory limit per PHP version

## Files to Modify
1. `gui/includes/functions.php` - Add migration function
2. `gui/index.php` - Add PHP version selector to create modal
3. `gui/edit-site.php` - Add PHP version switcher
4. `gui/api.php` - Handle PHP version in create/update
5. `gui/api.php` - Update docker-compose generation functions
6. `gui/migrate-php-version.php` - New migration script
7. `install.sh` - Add migration to install script

## Estimated Implementation Time
- Database migration: 10 minutes
- UI changes: 30 minutes
- API changes: 45 minutes
- Docker compose updates: 30 minutes
- Testing: 30 minutes
- **Total: ~2.5 hours**

## Priority
**High** - This is a commonly requested feature that significantly improves flexibility.

---

Ready to implement? Let me know and I'll start with the database migration!
