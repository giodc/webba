<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication
requireAuth();

$db = initDatabase();
$currentUser = getCurrentUser();

// Get current Let's Encrypt email from docker-compose.yml
$dockerComposePath = '/opt/webbadeploy/docker-compose.yml';

// Try to read the file directly first, fallback to exec if not accessible
if (file_exists($dockerComposePath) && is_readable($dockerComposePath)) {
    $dockerComposeContent = file_get_contents($dockerComposePath);
} else {
    // Fallback: try using exec to read the file
    exec("cat $dockerComposePath 2>&1", $output, $returnCode);
    $dockerComposeContent = $returnCode === 0 ? implode("\n", $output) : '';
}

preg_match('/acme\.email=([^\s"]+)/', $dockerComposeContent, $matches);
$currentEmail = $matches[1] ?? 'admin@example.com';

// Get custom wildcard domain from settings
$customWildcardDomain = getSetting($db, 'custom_wildcard_domain', '');

// Get dashboard domain settings
$dashboardDomain = getSetting($db, 'dashboard_domain', '');
$dashboardSSL = getSetting($db, 'dashboard_ssl', '0');

$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['letsencrypt_email'])) {
        $newEmail = trim($_POST['letsencrypt_email']);
        
        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            // Check if file exists and is accessible
            if (!file_exists($dockerComposePath)) {
                $errorMessage = 'docker-compose.yml not found. Make sure the file is mounted in the container at ' . $dockerComposePath;
            } elseif (!is_readable($dockerComposePath)) {
                $errorMessage = 'Cannot read docker-compose.yml. Check file permissions.';
            } elseif (!is_writable($dockerComposePath)) {
                $errorMessage = 'Cannot write to docker-compose.yml. Check file permissions.';
            } else {
                // Read, modify, and write the file using PHP
                $fileContent = file_get_contents($dockerComposePath);
                
                if ($fileContent !== false) {
                    // Create backup first
                    $backupPath = $dockerComposePath . '.backup';
                    file_put_contents($backupPath, $fileContent);
                    
                    // Replace the email using preg_replace
                    $newContent = preg_replace(
                        '/acme\.email=[^\s"]+/',
                        'acme.email=' . $newEmail,
                        $fileContent
                    );
                    
                    // Write the updated content
                    if (file_put_contents($dockerComposePath, $newContent) !== false) {
                        $successMessage = 'Let\'s Encrypt email updated successfully! Please restart Traefik for changes to take effect.';
                        $currentEmail = $newEmail;
                        $dockerComposeContent = $newContent;
                    } else {
                        // Restore backup if write failed
                        file_put_contents($dockerComposePath, $fileContent);
                        $errorMessage = 'Failed to write to docker-compose.yml. Check file permissions.';
                    }
                } else {
                    $errorMessage = 'Failed to read docker-compose.yml content.';
                }
            }
        } else {
            $errorMessage = 'Please enter a valid email address.';
        }
    }
    
    if (isset($_POST['custom_wildcard_domain'])) {
        $newDomain = trim($_POST['custom_wildcard_domain']);
        
        // Validate domain format (should start with a dot for wildcard)
        if (empty($newDomain) || preg_match('/^\.[a-z0-9.-]+$/i', $newDomain)) {
            if (setSetting($db, 'custom_wildcard_domain', $newDomain)) {
                $successMessage = 'Custom wildcard domain updated successfully!';
                $customWildcardDomain = $newDomain;
            } else {
                $errorMessage = 'Failed to update custom wildcard domain.';
            }
        } else {
            $errorMessage = 'Invalid domain format. Use format like: .example.com or .yourdomain.com';
        }
    }
    
    if (isset($_POST['dashboard_domain'])) {
        $newDashboardDomain = trim($_POST['dashboard_domain']);
        $enableSSL = isset($_POST['dashboard_ssl']) ? '1' : '0';
        
        // Validate domain format (should NOT start with a dot, regular domain)
        if (empty($newDashboardDomain) || preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $newDashboardDomain)) {
            // Save settings
            setSetting($db, 'dashboard_domain', $newDashboardDomain);
            setSetting($db, 'dashboard_ssl', $enableSSL);
            
            // Update docker-compose.yml with Traefik labels for web-gui
            $result = updateDashboardTraefikConfig($newDashboardDomain, $enableSSL);
            
            if ($result['success']) {
                $successMessage = 'Dashboard domain updated successfully! <button class="btn btn-sm btn-warning ms-2" onclick="restartWebGui()"><i class="bi bi-arrow-clockwise me-1"></i>Restart Now</button>';
                $dashboardDomain = $newDashboardDomain;
                $dashboardSSL = $enableSSL;
            } else {
                $errorMessage = 'Failed to update dashboard configuration: ' . $result['error'];
            }
        } else {
            $errorMessage = 'Invalid domain format. Use format like: dashboard.example.com';
        }
    }
}

function updateDashboardTraefikConfig($domain, $enableSSL) {
    $dockerComposePath = '/opt/webbadeploy/docker-compose.yml';
    
    if (!file_exists($dockerComposePath) || !is_writable($dockerComposePath)) {
        return ['success' => false, 'error' => 'Cannot access docker-compose.yml'];
    }
    
    $content = file_get_contents($dockerComposePath);
    
    if (empty($domain)) {
        // Remove Traefik labels if domain is empty
        $content = preg_replace('/\s+labels:.*?(?=\n\s{4}[a-z]|\n[a-z]|$)/s', '', $content);
        file_put_contents($dockerComposePath, $content);
        return ['success' => true, 'message' => 'Dashboard domain removed. Restart web-gui to apply changes.'];
    }
    
    // Find the web-gui service section
    if (!preg_match('/web-gui:/', $content)) {
        return ['success' => false, 'error' => 'web-gui service not found in docker-compose.yml'];
    }
    
    // Build Traefik labels
    $labels = "\n    labels:\n";
    $labels .= "      - traefik.enable=true\n";
    $labels .= "      - traefik.http.routers.webgui.rule=Host(`{$domain}`)\n";
    $labels .= "      - traefik.http.routers.webgui.entrypoints=web\n";
    $labels .= "      - traefik.http.services.webgui.loadbalancer.server.port=80\n";
    
    if ($enableSSL === '1') {
        $labels .= "      - traefik.http.routers.webgui-secure.rule=Host(`{$domain}`)\n";
        $labels .= "      - traefik.http.routers.webgui-secure.entrypoints=websecure\n";
        $labels .= "      - traefik.http.routers.webgui-secure.tls=true\n";
        $labels .= "      - traefik.http.routers.webgui-secure.tls.certresolver=letsencrypt\n";
        $labels .= "      - traefik.http.middlewares.webgui-redirect.redirectscheme.scheme=https\n";
        $labels .= "      - traefik.http.middlewares.webgui-redirect.redirectscheme.permanent=true\n";
        $labels .= "      - traefik.http.routers.webgui.middlewares=webgui-redirect\n";
    }
    
    // Remove existing labels if any
    $content = preg_replace('/(\s+web-gui:.*?)(\n\s+labels:.*?)(?=\n\s{4}[a-z]|\n[a-z]|$)/s', '$1', $content);
    
    // Add new labels before networks section
    $content = preg_replace(
        '/(web-gui:.*?)(networks:)/s',
        '$1' . $labels . '    $2',
        $content
    );
    
    file_put_contents($dockerComposePath, $content);
    
    return ['success' => true, 'message' => 'Please restart web-gui container: docker-compose restart web-gui'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Webbadeploy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-gear me-2"></i>System Settings</h2>
                    <a href="/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $successMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= $errorMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Domain Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-globe me-2"></i>Domain Configuration
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="custom_wildcard_domain" class="form-label">
                                    Custom Wildcard Domain
                                </label>
                                <input type="text" class="form-control" id="custom_wildcard_domain" name="custom_wildcard_domain" 
                                       value="<?= htmlspecialchars($customWildcardDomain) ?>" placeholder=".example.com">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Set a custom wildcard domain that will appear in the dropdown when creating new sites. 
                                    Format: <code>.example.com</code> or <code>.yourdomain.com</code>. Leave empty to disable.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Custom Domain
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Dashboard Domain Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-house-door me-2"></i>Dashboard Domain
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="dashboard_domain" class="form-label">
                                    Custom Dashboard Domain
                                </label>
                                <input type="text" class="form-control" id="dashboard_domain" name="dashboard_domain" 
                                       value="<?= htmlspecialchars($dashboardDomain) ?>" placeholder="dashboard.example.com">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Set a custom domain to access this dashboard. Make sure the domain points to your server's IP address.
                                    Leave empty to keep using IP:port access.
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="dashboard_ssl" name="dashboard_ssl" 
                                       <?= $dashboardSSL === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="dashboard_ssl">
                                    <i class="bi bi-shield-lock me-1"></i>Enable SSL (Let's Encrypt)
                                </label>
                                <div class="form-text">
                                    Automatically obtain and renew SSL certificate for the dashboard domain.
                                    <strong>Port 80 and 443 must be accessible from the internet.</strong>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Dashboard Settings
                            </button>
                        </form>
                        
                        <?php if (!empty($dashboardDomain)): ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Current Dashboard URL:</strong><br>
                            <a href="<?= $dashboardSSL === '1' ? 'https' : 'http' ?>://<?= htmlspecialchars($dashboardDomain) ?>" target="_blank">
                                <?= $dashboardSSL === '1' ? 'https' : 'http' ?>://<?= htmlspecialchars($dashboardDomain) ?>
                                <i class="bi bi-box-arrow-up-right ms-1"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SSL Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-shield-lock me-2"></i>SSL Configuration
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="letsencrypt_email" class="form-label">
                                    Let's Encrypt Email Address
                                </label>
                                <input type="email" class="form-control" id="letsencrypt_email" name="letsencrypt_email" 
                                       value="<?= htmlspecialchars($currentEmail) ?>" required>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This email is used for SSL certificate expiration notifications and important security notices.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save SSL Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- System Updates -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-arrow-up-circle me-2"></i>System Updates
                    </div>
                    <div class="card-body">
                        <div id="updateCheckSection">
                            <button class="btn btn-primary" onclick="checkForUpdates()">
                                <i class="bi bi-search me-2"></i>Check for Updates
                            </button>
                        </div>
                        <div id="updateInfoSection" style="display: none;">
                            <!-- Update info will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>System Information
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Version</div>
                            <div class="col-md-8">
                                <?php 
                                    $versionFile = '/var/www/html/../VERSION';
                                    echo file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
                                ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Docker Compose Path</div>
                            <div class="col-md-8"><code><?= $dockerComposePath ?></code></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 text-muted">Current User</div>
                            <div class="col-md-8"><?= htmlspecialchars($currentUser['username']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Restart Traefik -->
                <?php if ($successMessage): ?>
                <div class="card mt-4 border-warning">
                    <div class="card-body">
                        <h5 class="card-title text-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>Action Required
                        </h5>
                        <p class="card-text">
                            To apply the new Let's Encrypt email, you need to restart the Traefik container.
                        </p>
                        <button class="btn btn-warning" onclick="restartTraefik()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Restart Traefik
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function restartTraefik() {
            if (!confirm('Are you sure you want to restart Traefik? This may cause brief downtime for all sites.')) {
                return;
            }

            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Restarting...';
            btn.disabled = true;

            try {
                const response = await fetch('/api.php?action=restart_traefik', {
                    method: 'POST'
                });
                const result = await response.json();

                if (result.success) {
                    alert('Traefik restarted successfully!');
                    location.reload();
                } else {
                    alert('Failed to restart Traefik: ' + (result.error || 'Unknown error'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                alert('Error: ' + error.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        async function checkForUpdates() {
            const checkSection = document.getElementById('updateCheckSection');
            const infoSection = document.getElementById('updateInfoSection');
            
            checkSection.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Checking...</span></div> <span class="ms-2">Checking for updates...</span>';
            
            try {
                const response = await fetch('/api.php?action=get_update_info');
                const result = await response.json();
                
                if (result.success) {
                    displayUpdateInfo(result.info, result.changelog);
                } else {
                    infoSection.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Failed to check for updates: ${result.error || 'Unknown error'}
                        </div>
                    `;
                    infoSection.style.display = 'block';
                    checkSection.style.display = 'none';
                }
            } catch (error) {
                infoSection.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Network error: ${error.message}
                    </div>
                `;
                infoSection.style.display = 'block';
                checkSection.style.display = 'none';
            }
        }

        function displayUpdateInfo(info, changelog) {
            const checkSection = document.getElementById('updateCheckSection');
            const infoSection = document.getElementById('updateInfoSection');
            
            let html = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Current Version:</strong> ${info.current_version}
                    </div>
                    <div class="col-md-6">
                        <strong>Latest Version:</strong> ${info.remote_version || 'Unknown'}
                    </div>
                </div>
            `;
            
            if (info.update_available) {
                html += `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Update Available!</strong> A new version is ready to install.
                    </div>
                    <button class="btn btn-success mb-3" onclick="performUpdate()">
                        <i class="bi bi-download me-2"></i>Install Update Now
                    </button>
                `;
            } else {
                html += `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        You are running the latest version.
                    </div>
                `;
            }
            
            if (info.has_local_changes) {
                html += `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Local changes detected. They will be stashed during update.
                    </div>
                `;
            }
            
            if (changelog && changelog.length > 0) {
                html += `
                    <div class="mt-3">
                        <h6>Recent Changes:</h6>
                        <ul class="list-unstyled">
                `;
                changelog.slice(0, 5).forEach(line => {
                    html += `<li><code>${line}</code></li>`;
                });
                html += `
                        </ul>
                    </div>
                `;
            }
            
            html += `
                <button class="btn btn-secondary mt-2" onclick="resetUpdateSection()">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </button>
            `;
            
            infoSection.innerHTML = html;
            infoSection.style.display = 'block';
            checkSection.style.display = 'none';
        }

        async function performUpdate() {
            if (!confirm('Are you sure you want to update? This will pull the latest changes from Git and may restart services.')) {
                return;
            }
            
            const infoSection = document.getElementById('updateInfoSection');
            infoSection.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h5>Installing Update...</h5>
                    <p class="text-muted">This may take a minute...</p>
                </div>
            `;
            
            try {
                const response = await fetch('/api.php?action=perform_update', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    infoSection.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Update Successful!</strong><br>
                            Updated to version ${result.version || 'latest'}<br>
                            <small class="text-muted">Page will reload in 3 seconds...</small>
                        </div>
                    `;
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    infoSection.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Update Failed!</strong><br>
                            ${result.error || 'Unknown error'}
                        </div>
                        <button class="btn btn-secondary" onclick="resetUpdateSection()">
                            <i class="bi bi-arrow-left me-2"></i>Back
                        </button>
                    `;
                }
            } catch (error) {
                infoSection.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Network Error!</strong><br>
                        ${error.message}
                    </div>
                    <button class="btn btn-secondary" onclick="resetUpdateSection()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                `;
            }
        }

        function resetUpdateSection() {
            const checkSection = document.getElementById('updateCheckSection');
            const infoSection = document.getElementById('updateInfoSection');
            
            checkSection.innerHTML = `
                <button class="btn btn-primary" onclick="checkForUpdates()">
                    <i class="bi bi-search me-2"></i>Check for Updates
                </button>
            `;
            checkSection.style.display = 'block';
            infoSection.style.display = 'none';
        }
        
        async function restartWebGui() {
            if (!confirm('Restart the web-gui container? The page will reload after restart.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=restart_webgui', {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Web-GUI is restarting... The page will reload in 5 seconds.');
                    setTimeout(() => {
                        window.location.reload();
                    }, 5000);
                } else {
                    alert('Failed to restart: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>
