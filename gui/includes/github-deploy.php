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
    
    // 1. Run Composer Install
    $results[] = "Running composer install...";
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && composer install --no-dev --optimize-autoloader 2>&1'", $composerOutput, $composerReturn);
    
    if ($composerReturn !== 0) {
        return ['success' => false, 'message' => 'Composer install failed: ' . implode("\n", $composerOutput)];
    }
    $results[] = "✓ Composer dependencies installed";
    
    // 2. Check if .env exists, if not copy from .env.example
    exec("docker exec {$containerName} test -f /var/www/html/.env", $envOutput, $envReturn);
    if ($envReturn !== 0) {
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && cp .env.example .env 2>&1'");
        $results[] = "✓ Created .env from .env.example";
        
        // Generate application key
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan key:generate 2>&1'");
        $results[] = "✓ Generated application key";
    }
    
    // 3. Set proper permissions
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && chmod -R 775 storage bootstrap/cache 2>&1'");
    exec("docker exec {$containerName} chown -R www-data:www-data /var/www/html");
    $results[] = "✓ Set proper permissions";
    
    // 4. Run migrations (if database is configured)
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan migrate --force 2>&1'", $migrateOutput, $migrateReturn);
    if ($migrateReturn === 0) {
        $results[] = "✓ Database migrations completed";
    } else {
        $results[] = "⚠ Migrations skipped (database may not be configured)";
    }
    
    // 5. Clear and cache config
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan config:cache 2>&1'");
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan route:cache 2>&1'");
    exec("docker exec {$containerName} sh -c 'cd /var/www/html && php artisan view:cache 2>&1'");
    $results[] = "✓ Cached configuration, routes, and views";
    
    // 6. Check if package.json exists for npm
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
