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
            // First, clear the directory (but keep it)
            exec("docker exec {$containerName} sh -c 'rm -rf /var/www/html/* /var/www/html/.*  2>/dev/null || true'");
            
            // Clone into a temp directory then move contents
            $cloneCmd = "docker exec {$containerName} sh -c 'cd /tmp && rm -rf repo_clone && git clone -b {$githubBranch} {$repoUrl} repo_clone 2>&1 && cp -r /tmp/repo_clone/. /var/www/html/ && rm -rf /tmp/repo_clone'";
            exec($cloneCmd, $cloneOutput, $cloneReturn);
            
            if ($cloneReturn !== 0) {
                return ['success' => false, 'message' => 'Failed to clone from GitHub: ' . implode("\n", $cloneOutput)];
            }
            
            $message = 'Successfully cloned repository from GitHub';
        }
        
        // Set proper permissions
        exec("docker exec {$containerName} chown -R www-data:www-data /var/www/html");
        exec("docker exec {$containerName} chmod -R 755 /var/www/html");
        
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
        // Get current local commit
        exec("docker exec {$containerName} sh -c 'cd /var/www/html && git rev-parse HEAD 2>/dev/null'", $localOutput, $localReturn);
        
        if ($localReturn !== 0) {
            return ['success' => false, 'message' => 'Not a git repository'];
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
