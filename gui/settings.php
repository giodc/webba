<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication
requireAuth();

$db = initDatabase();
$currentUser = getCurrentUser();

// Get current Let's Encrypt email from docker-compose.yml
// The file is on the host, not in the container, so we need to read it via docker exec
$dockerComposePath = '/opt/webbadeploy/docker-compose.yml';
exec("cat $dockerComposePath 2>&1", $output, $returnCode);
$dockerComposeContent = $returnCode === 0 ? implode("\n", $output) : '';
preg_match('/acme\.email=([^\s"]+)/', $dockerComposeContent, $matches);
$currentEmail = $matches[1] ?? 'admin@example.com';

// Get custom wildcard domain from settings
$customWildcardDomain = getSetting($db, 'custom_wildcard_domain', '');

$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['letsencrypt_email'])) {
        $newEmail = trim($_POST['letsencrypt_email']);
        
        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            // Read, modify, and write the file using PHP instead of sed
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
                $errorMessage = 'Failed to read docker-compose.yml. Check file permissions.';
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-cloud-arrow-up me-2"></i>Webbadeploy
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="/">
                        <i class="bi bi-house me-1"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="/settings.php">
                        <i class="bi bi-gear me-1"></i>Settings
                    </a>
                    <span class="nav-link text-light">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($currentUser['username']) ?>
                    </span>
                    <a class="nav-link" href="/logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

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

    <!-- Footer -->
    <footer class="bg-dark text-white-50 py-3 mt-5">
        <div class="container text-center">
            <small>
                <i class="bi bi-cloud-arrow-up me-1"></i>
                Webbadeploy v<?php 
                    $versionFile = '/var/www/html/../VERSION';
                    echo file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
                ?>
            </small>
        </div>
    </footer>

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
    </script>
</body>
</html>
