<?php
/**
 * GitHub Deployment Functions
 * Simple deployment from GitHub repositories
 */

/**
 * Deploy code from GitHub repository to container
 * 
 * @param array $site Site configuration with github_repo, github_branch, github_token
 * @param string $containerName Docker container name
 * @return array Result with success status and message
 */
function deployFromGitHub($site, $containerName) {
    $githubRepo = $site['github_repo'] ?? null;
    $githubBranch = $site['github_branch'] ?? 'main';
    $githubToken = $site['github_token'] ?? null;
    
    if (empty($githubRepo)) {
        return ['success' => false, 'message' => 'No GitHub repository configured'];
    }
    
    // Decrypt GitHub token if present
    if ($githubToken) {
        $githubToken = decryptGitHubToken($githubToken);
    }
    
    // Normalize repository URL
    $repoUrl = normalizeGitHubUrl($githubRepo, $githubToken);
    
    // Clone or pull repository
    try {
        // Check if git is installed in container
        exec("docker exec {$containerName} which git 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            // Install git
            exec("docker exec {$containerName} apt-get update && docker exec {$containerName} apt-get install -y git 2>&1");
        }
        
        // Check if repo already exists
        exec("docker exec {$containerName} test -d /var/www/html/.git 2>/dev/null", $output, $returnCode);
        
        if ($returnCode === 0) {
            // Repository exists, pull latest changes
            // First, mark directory as safe to avoid "dubious ownership" error
            exec("docker exec {$containerName} sh -c 'git config --global --add safe.directory /var/www/html 2>&1'");
            
            $pullCmd = "docker exec {$containerName} sh -c 'cd /var/www/html && git pull origin {$githubBranch} 2>&1'";
            exec($pullCmd, $pullOutput, $pullReturn);
            
            if ($pullReturn !== 0) {
                return ['success' => false, 'message' => 'Failed to pull from GitHub: ' . implode("\n", $pullOutput)];
            }
            
            $message = 'Successfully pulled latest changes from GitHub';
        } else {
            // Clone repository
            // Clear the html directory completely and clone fresh
            exec("docker exec {$containerName} sh -c 'rm -rf /var/www/html'");
            exec("docker exec {$containerName} sh -c 'mkdir -p /var/www/html'");
            
            // Clone directly into /var/www/html
            $cloneCmd = "docker exec {$containerName} sh -c 'cd /var/www && git clone -b {$githubBranch} {$repoUrl} html 2>&1'";
            exec($cloneCmd, $cloneOutput, $cloneReturn);
            
            if ($cloneReturn !== 0) {
                return ['success' => false, 'message' => 'Failed to clone from GitHub: ' . implode("\n", $cloneOutput)];
            }
            
            // Mark directory as safe for future git operations
            exec("docker exec {$containerName} sh -c 'git config --global --add safe.directory /var/www/html 2>&1'");
            
            $message = 'Successfully cloned repository from GitHub';
        }
        
        // Set proper permissions
        exec("docker exec {$containerName} chown -R www-data:www-data /var/www/html");
        exec("docker exec {$containerName} chmod -R 755 /var/www/html");
        
        // Run Laravel build steps if it's a Laravel site
        $siteType = $site['type'] ?? '';
        if ($siteType === 'laravel') {
            $buildResult = runLaravelBuild($containerName, $siteType);
            if (!$buildResult['success']) {
                return $buildResult;
            }
            $message .= "\n" . ($buildResult['details'] ?? '');
        }
        
        // Get current commit hash
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && git rev-parse HEAD 2>/dev/null'", $commitOutput);
        $commitHash = trim($commitOutput[0] ?? '');
        
        return [
            'success' => true,
            'message' => $message,
            'commit_hash' => $commitHash
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'GitHub deployment error: ' . $e->getMessage()];
    }
}

/**
 * Normalize GitHub repository URL
 * Supports: https://github.com/user/repo, github.com/user/repo, user/repo
 * 
 * @param string $repo Repository identifier
 * @param string|null $token Personal access token for private repos (DECRYPTED)
 * @return string Normalized HTTPS URL with embedded token (DO NOT LOG!)
 */
function normalizeGitHubUrl($repo, $token = null) {
    // Remove any trailing .git
    $repo = preg_replace('/\.git$/', '', $repo);
    
    // Remove any existing tokens from URL (security: clean input)
    $repo = preg_replace('/https:\/\/[^@]+@github\.com/', 'https://github.com', $repo);
    
    // If it's already a full URL
    if (preg_match('/^https?:\/\//', $repo)) {
        $url = $repo;
    }
    // If it's github.com/user/repo
    elseif (preg_match('/^github\.com\//', $repo)) {
        $url = 'https://' . $repo;
    }
    // If it's just user/repo
    elseif (preg_match('/^[a-zA-Z0-9_-]+\/[a-zA-Z0-9_.-]+$/', $repo)) {
        $url = 'https://github.com/' . $repo;
    }
    else {
        $url = $repo; // Use as-is
    }
    
    // Add token for private repos
    // WARNING: This URL contains sensitive data - never log or display it!
    if ($token) {
        // Use token as username in URL (GitHub supports this)
        $url = preg_replace('/^https:\/\/github\.com/', "https://{$token}@github.com", $url);
    }
    
    return $url;
}

/**
 * Check if there are updates available from GitHub
 * 
 * @param array $site Site configuration
 * @param string $containerName Docker container name
 * @return array Result with has_updates boolean and remote_commit
 */
function checkGitHubUpdates($site, $containerName) {
    $githubRepo = $site['github_repo'] ?? null;
    $githubBranch = $site['github_branch'] ?? 'main';
    $githubToken = $site['github_token'] ?? null;
    
    if (empty($githubRepo)) {
        return ['success' => false, 'message' => 'No GitHub repository configured'];
    }
    
    // Decrypt GitHub token if present
    if ($githubToken) {
        $githubToken = decryptGitHubToken($githubToken);
    }
    
    try {
        // First check if .git directory exists
        exec("docker exec {$containerName} test -d /var/www/html/.git", $testOutput, $testReturn);
        
        if ($testReturn !== 0) {
            return ['success' => false, 'message' => 'Not a git repository. The site may have been deployed manually or the .git folder is missing. Try pulling from GitHub first.'];
        }
        
        // Mark directory as safe to avoid "dubious ownership" error
        exec("docker exec {$containerName} sh -c 'git config --global --add safe.directory /var/www/html 2>&1'");
        
        // Get current local commit
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && git rev-parse HEAD 2>/dev/null'", $localOutput, $localReturn);
        
        if ($localReturn !== 0) {
            return ['success' => false, 'message' => 'Cannot read git repository. Try pulling from GitHub to reinitialize.'];
        }
        
        $localCommit = trim($localOutput[0] ?? '');
        
        // Fetch latest from remote
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && git fetch origin {$githubBranch} 2>&1'", $fetchOutput, $fetchReturn);
        
        // Get remote commit
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && git rev-parse origin/{$githubBranch} 2>/dev/null'", $remoteOutput, $remoteReturn);
        
        if ($remoteReturn !== 0) {
            return ['success' => false, 'message' => 'Failed to get remote commit'];
        }
        
        $remoteCommit = trim($remoteOutput[0] ?? '');
        
        return [
            'success' => true,
            'has_updates' => $localCommit !== $remoteCommit,
            'local_commit' => substr($localCommit, 0, 7),
            'remote_commit' => substr($remoteCommit, 0, 7),
            'local_commit_full' => $localCommit,
            'remote_commit_full' => $remoteCommit
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error checking updates: ' . $e->getMessage()];
    }
}

/**
 * Run composer install for PHP/Laravel projects
 * 
 * @param string $containerName Docker container name
 * @return array Result with success status
 */
function runComposerInstall($containerName) {
    // Check if composer.json exists
    exec("docker exec {$containerName} test -f /var/www/html/composer.json 2>/dev/null", $output, $returnCode);
    
    if ($returnCode !== 0) {
        return ['success' => true, 'message' => 'No composer.json found, skipping'];
    }
    
    // Check if composer is installed
    exec("docker exec {$containerName} which composer 2>&1", $output, $returnCode);
    if ($returnCode !== 0) {
        // Install composer
        $installCmd = "docker exec {$containerName} sh -c 'curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer'";
        exec($installCmd);
    }
    
    // Run composer install
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && composer install --no-dev --optimize-autoloader 2>&1'", $output, $returnCode);
    
    if ($returnCode !== 0) {
        return ['success' => false, 'message' => 'Composer install failed: ' . implode("\n", $output)];
    }
    
    return ['success' => true, 'message' => 'Composer dependencies installed'];
}

/**
 * Run Laravel-specific deployment steps
 * 
 * @param string $containerName Docker container name
 * @param string $siteType Site type (laravel, php, etc.)
 * @return array Result with success status
 */
function runLaravelBuild($containerName, $siteType = 'laravel') {
    $results = [];
    
    // Only run for Laravel sites
    if ($siteType !== 'laravel') {
        return ['success' => true, 'message' => 'Not a Laravel site, skipping Laravel build steps'];
    }
    
    // 1. Install required PHP extensions for Laravel
    $results[] = "Checking required PHP extensions...";
    
    // Check if zip extension is installed
    exec("docker exec {$containerName} php -m | grep -i zip", $zipCheckOutput, $zipCheckReturn);
    if ($zipCheckReturn !== 0) {
        $results[] = "Installing PHP zip extension...";
        exec("docker exec -u root {$containerName} sh -c 'apt-get update && apt-get install -y libzip-dev zip unzip && docker-php-ext-install zip 2>&1'", $zipInstallOutput, $zipInstallReturn);
        
        if ($zipInstallReturn === 0) {
            $results[] = "✓ PHP zip extension installed";
        } else {
            $results[] = "⚠ Warning: Could not install zip extension";
        }
    } else {
        $results[] = "✓ PHP zip extension already installed";
    }
    
    // 2. Check if Composer exists, install if needed
    exec("docker exec {$containerName} which composer 2>&1", $composerCheckOutput, $composerCheckReturn);
    
    if ($composerCheckReturn !== 0) {
        $results[] = "Installing Composer...";
        exec("docker exec -u root {$containerName} sh -c 'curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer 2>&1'", $installOutput, $installReturn);
        
        if ($installReturn !== 0) {
            return ['success' => false, 'message' => 'Failed to install Composer: ' . implode("\n", $installOutput)];
        }
        $results[] = "✓ Composer installed";
    }
    
    // 3. Run Composer Install
    $results[] = "Running composer install...";
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && composer install --no-dev --optimize-autoloader 2>&1'", $composerOutput, $composerReturn);
    
    if ($composerReturn !== 0) {
        return ['success' => false, 'message' => 'Composer install failed: ' . implode("\n", $composerOutput)];
    }
    $results[] = "✓ Composer dependencies installed";
    
    // 4. Check if .env exists, if not copy from .env.example
    exec("docker exec {$containerName} test -f /var/www/html/.env", $envOutput, $envReturn);
    if ($envReturn !== 0) {
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && cp .env.example .env 2>&1'");
        $results[] = "✓ Created .env from .env.example";
        
        // Generate application key
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan key:generate 2>&1'");
        $results[] = "✓ Generated application key";
    }
    
    // Ensure critical .env values are set
    exec("docker exec {$containerName} sh -c 'grep -q \"^LOG_CHANNEL=\" /var/www/html/.env || echo \"LOG_CHANNEL=stack\" >> /var/www/html/.env'");
    exec("docker exec {$containerName} sh -c 'grep -q \"^APP_ENV=\" /var/www/html/.env || echo \"APP_ENV=production\" >> /var/www/html/.env'");
    
    // Sync Docker environment variables to .env file (critical for database connection)
    $results[] = "Syncing Docker environment variables to .env...";
    
    // Get Docker environment variables
    exec("docker exec {$containerName} sh -c 'printenv | grep -E \"^(DB_|REDIS_|MAIL_|AWS_)\"'", $dockerEnvOutput);
    
    if (!empty($dockerEnvOutput)) {
        foreach ($dockerEnvOutput as $envLine) {
            if (strpos($envLine, '=') !== false) {
                list($key, $value) = explode('=', $envLine, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Update or add the variable in .env
                exec("docker exec {$containerName} sh -c 'grep -q \"^{$key}=\" /var/www/html/.env && sed -i \"s|^{$key}=.*|{$key}={$value}|\" /var/www/html/.env || echo \"{$key}={$value}\" >> /var/www/html/.env'");
            }
        }
        $results[] = "✓ Synced " . count($dockerEnvOutput) . " environment variables to .env";
    } else {
        $results[] = "→ No Docker environment variables to sync";
    }
    
    $results[] = "✓ Verified .env configuration";
    
    // 5. Create required Laravel directories
    $results[] = "Creating required directories...";
    exec("docker exec {$containerName} sh -c 'mkdir -p /var/www/html/storage/framework/sessions 2>&1'");
    exec("docker exec {$containerName} sh -c 'mkdir -p /var/www/html/storage/framework/views 2>&1'");
    exec("docker exec {$containerName} sh -c 'mkdir -p /var/www/html/storage/framework/cache 2>&1'");
    exec("docker exec {$containerName} sh -c 'mkdir -p /var/www/html/storage/logs 2>&1'");
    exec("docker exec {$containerName} sh -c 'mkdir -p /var/www/html/bootstrap/cache 2>&1'");
    exec("docker exec {$containerName} sh -c 'mkdir -p /var/www/html/database 2>&1'");
    $results[] = "✓ Required directories created";
    
    // 6. Set proper permissions (critical for Laravel)
    // First set ownership to www-data
    exec("docker exec {$containerName} chown -R www-data:www-data /var/www/html 2>&1", $chownOutput, $chownReturn);
    if ($chownReturn !== 0) {
        $results[] = "⚠ Warning: Could not set ownership (may need root access)";
    }
    
    // Set directory permissions (755 = rwxr-xr-x)
    exec("docker exec {$containerName} find /var/www/html -type d -exec chmod 755 {} \\; 2>&1");
    
    // Set file permissions (644 = rw-r--r--)
    exec("docker exec {$containerName} find /var/www/html -type f -exec chmod 644 {} \\; 2>&1");
    
    // Storage and cache need write permissions (775 = rwxrwxr-x)
    exec("docker exec {$containerName} sh -c 'chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>&1'");
    
    // Database directory needs write permissions for SQLite
    exec("docker exec {$containerName} sh -c 'chmod -R 775 /var/www/html/database 2>&1'");
    
    // Ensure public/index.php is readable
    exec("docker exec {$containerName} chmod 644 /var/www/html/public/index.php 2>&1");
    
    $results[] = "✓ Set proper permissions";
    
    // 7. Setup database and run migrations
    $results[] = "Setting up database...";
    
    // Check database configuration in .env
    exec("docker exec {$containerName} sh -c 'grep \"^DB_CONNECTION=\" /var/www/html/.env'", $dbConnectionOutput);
    $dbConnection = isset($dbConnectionOutput[0]) ? trim(str_replace('DB_CONNECTION=', '', $dbConnectionOutput[0])) : 'unknown';
    $results[] = "→ DB_CONNECTION: {$dbConnection}";
    
    exec("docker exec {$containerName} sh -c 'grep \"^DB_HOST=\" /var/www/html/.env'", $dbHostOutput);
    $dbHost = isset($dbHostOutput[0]) ? trim(str_replace('DB_HOST=', '', $dbHostOutput[0])) : '';
    if (!empty($dbHost)) {
        $results[] = "→ DB_HOST: {$dbHost}";
    }
    
    exec("docker exec {$containerName} sh -c 'grep \"^DB_DATABASE=\" /var/www/html/.env'", $dbDatabaseOutput);
    $dbDatabase = isset($dbDatabaseOutput[0]) ? trim(str_replace('DB_DATABASE=', '', $dbDatabaseOutput[0])) : '';
    $results[] = "→ DB_DATABASE: {$dbDatabase}";
    
    exec("docker exec {$containerName} sh -c 'grep \"^DB_USERNAME=\" /var/www/html/.env'", $dbUsernameOutput);
    $dbUsername = isset($dbUsernameOutput[0]) ? trim(str_replace('DB_USERNAME=', '', $dbUsernameOutput[0])) : '';
    if (!empty($dbUsername)) {
        $results[] = "→ DB_USERNAME: {$dbUsername}";
    }
    
    // If SQLite, ensure database file exists
    if ($dbConnection === 'sqlite' && !empty($dbDatabase)) {
        // Create the database file if it doesn't exist
        exec("docker exec {$containerName} sh -c 'touch {$dbDatabase} 2>&1'", $touchOutput, $touchReturn);
        if ($touchReturn === 0) {
            $results[] = "✓ SQLite database file created/verified";
            
            // Set proper permissions on database file
            exec("docker exec {$containerName} sh -c 'chmod 664 {$dbDatabase} 2>&1'");
            exec("docker exec {$containerName} sh -c 'chown www-data:www-data {$dbDatabase} 2>&1'");
            $results[] = "✓ Database file permissions set";
        } else {
            $results[] = "⚠ Could not create database file: " . implode("\n", $touchOutput);
        }
    }
    
    // Run migrations with detailed output
    $results[] = "Running migrations...";
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan migrate --force 2>&1'", $migrateOutput, $migrateReturn);
    
    if ($migrateReturn === 0) {
        $results[] = "✓ Database migrations completed";
        if (!empty($migrateOutput)) {
            $results[] = "  Output: " . implode("\n  ", array_slice($migrateOutput, 0, 5));
        }
    } else {
        $results[] = "✗ Migration failed (exit code: {$migrateReturn})";
        $results[] = "  Error output:";
        foreach (array_slice($migrateOutput, 0, 10) as $line) {
            $results[] = "  " . $line;
        }
    }
    
    // 8. Clear and cache config
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan config:cache 2>&1'");
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan route:cache 2>&1'");
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan view:cache 2>&1'");
    $results[] = "✓ Cached configuration, routes, and views";
    
    // 9. Check if package.json exists for npm
    exec("docker exec {$containerName} test -f /var/www/html/package.json", $npmCheckOutput, $npmCheckReturn);
    if ($npmCheckReturn === 0) {
        // Check if npm is installed
        exec("docker exec {$containerName} which npm 2>&1", $npmOutput, $npmReturn);
        if ($npmReturn !== 0) {
            // Install Node.js and npm
            $results[] = "Installing Node.js and npm...";
            exec("docker exec {$containerName} sh -c 'curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs 2>&1'");
        }
        
        // Run npm install
        $results[] = "Running npm install...";
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && npm install 2>&1'", $npmInstallOutput, $npmInstallReturn);
        
        if ($npmInstallReturn === 0) {
            $results[] = "✓ NPM dependencies installed";
            
            // Run npm build
            $results[] = "Running npm run build...";
            exec("docker exec {$containerName} sh -c 'cd /var/www/html && npm run build 2>&1'", $npmBuildOutput, $npmBuildReturn);
            
            if ($npmBuildReturn === 0) {
                $results[] = "✓ Frontend assets built";
            } else {
                $results[] = "⚠ Frontend build failed (may not be configured)";
            }
        } else {
            $results[] = "⚠ NPM install failed";
        }
    } else {
        $results[] = "⚠ No package.json found, skipping npm steps";
    }
    
    return [
        'success' => true,
        'message' => 'Laravel build completed',
        'details' => implode("\n", $results)
    ];
}

/**
 * Sync Docker environment variables to Laravel .env file
 * 
 * @param string $containerName Docker container name
 * @param array $envVars Array of environment variables from Docker
 * @return array Result with success status
 */
function syncEnvToLaravel($containerName, $envVars = []) {
    $results = [];
    
    // Check if .env exists
    exec("docker exec {$containerName} test -f /var/www/html/.env", $envOutput, $envReturn);
    if ($envReturn !== 0) {
        return ['success' => false, 'message' => 'No .env file found'];
    }
    
    // Read current .env file
    exec("docker exec {$containerName} cat /var/www/html/.env", $currentEnv);
    $currentEnvContent = implode("\n", $currentEnv);
    
    // Parse current .env into array
    $envLines = [];
    $existingKeys = [];
    foreach ($currentEnv as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            $envLines[] = $line;
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $existingKeys[] = $key;
            
            // Check if this key should be updated from Docker env vars
            if (isset($envVars[$key])) {
                $envLines[] = $key . '=' . $envVars[$key];
                $results[] = "Updated: {$key}";
            } else {
                $envLines[] = $line;
            }
        } else {
            $envLines[] = $line;
        }
    }
    
    // Add new variables that don't exist in .env
    foreach ($envVars as $key => $value) {
        if (!in_array($key, $existingKeys)) {
            $envLines[] = $key . '=' . $value;
            $results[] = "Added: {$key}";
        }
    }
    
    // Write updated .env file
    $newEnvContent = implode("\n", $envLines);
    $escapedContent = str_replace("'", "'\\''", $newEnvContent);
    exec("docker exec {$containerName} sh -c 'echo \"{$escapedContent}\" > /var/www/html/.env'");
    
    // Clear Laravel config cache
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan config:clear 2>&1'");
    
    return [
        'success' => true,
        'message' => 'Environment variables synced to .env',
        'details' => implode("\n", $results)
    ];
}
