<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication
requireAuth();

$db = initDatabase();
$currentUser = getCurrentUser();

// Check if setup wizard should be shown (for admins on fresh install)
$setupCompleted = getSetting($db, 'setup_completed', '0');
$skipSetup = isset($_GET['skip_setup']) && $_GET['skip_setup'] === '1';

// If user skipped setup, mark it as completed
if ($skipSetup && isAdmin()) {
    setSetting($db, 'setup_completed', '1');
    $setupCompleted = '1'; // Update local variable
}

// Redirect to setup wizard if not completed (check for '0', null, or empty)
if (($setupCompleted === '0' || $setupCompleted === null || $setupCompleted === '') && isAdmin() && !$skipSetup) {
    header('Location: /setup-wizard.php');
    exit;
}

// Get sites based on user role and permissions
if (isAdmin()) {
    $sites = getAllSites($db);
} else {
    $sites = getUserSites($_SESSION['user_id']);
}

$customWildcardDomain = getSetting($db, 'custom_wildcard_domain', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Webbadeploy - Easy App Deployment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <?php if (empty($sites)): ?>

    <?php endif; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-grid me-2"></i>Your Applications</h2>
                    <?php if (canCreateSites($_SESSION['user_id'])): ?>
                    <button class="btn btn-primary" onclick="showCreateModal()">
                        <i class="bi bi-plus me-2"></i>New App
                    </button>
                    <?php endif; ?>
                </div>

                <div class="row" id="apps">
                    <?php if (empty($sites)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-cloud text-muted" style="font-size: 4rem;"></i>
                        <h3 class="mt-3">No applications yet</h3>
                        <?php if (canCreateSites($_SESSION['user_id'])): ?>
                        <p class="">Deploy your first application to get started</p>
                        <button class="btn btn-primary btn-lg" onclick="showCreateModal()">
                            <i class="bi bi-plus-circle me-2"></i>Deploy Your First App
                        </button>
                        <?php else: ?>
                        <p class="text-muted">You don't have access to any applications yet.<br>Contact an administrator to grant you access.</p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <?php foreach ($sites as $site): 
                        $containerStatus = getDockerContainerStatus($site['container_name']);
                        $sslConfigured = checkContainerSSLLabels($site['container_name']);
                    ?>
                    <div class="col-md-4 mb-4" data-site-id="<?= $site['id'] ?>">
                        <div class="card app-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title" onclick="window.location.href='/edit/<?= $site['id'] ?>/'" style="cursor: pointer;" title="Settings & Management">
                                        <?= htmlspecialchars($site['name']) ?>
                                    </h5>
                                    <span class="badge <?= $containerStatus == 'running' ? 'bg-success' : 'bg-warning' ?> status-badge">
                                        <i class="bi bi-circle-fill me-1"></i><?= ucfirst($containerStatus) ?>
                                    </span>
                                </div>
                                <p class="card-text text-muted">
                                    <i class="bi bi-<?= getAppIcon($site['type']) ?> me-2"></i><?= ucfirst($site['type']) ?>
                                    <?php 
                                    // Show database badge for any site with a database
                                    $dbType = $site['db_type'] ?? 'none';
                                    $showDbBadge = false;
                                    $dbLabel = '';
                                    
                                    if ($site['type'] === 'wordpress' && $dbType === 'dedicated') {
                                        $showDbBadge = true;
                                        $dbLabel = 'Dedicated DB';
                                    } elseif ($site['type'] === 'php' && in_array($dbType, ['mysql', 'postgresql'])) {
                                        $showDbBadge = true;
                                        $dbLabel = strtoupper($dbType) . ' DB';
                                    } elseif ($site['type'] === 'laravel' && in_array($dbType, ['mysql', 'postgresql'])) {
                                        $showDbBadge = true;
                                        $dbLabel = strtoupper($dbType) . ' DB';
                                    }
                                    ?>
                                    <?php if ($showDbBadge): ?>
                                        <span class="badge bg-info ms-2" title="Database: <?= htmlspecialchars($dbLabel) ?>">
                                            <i class="bi bi-database"></i> <?= htmlspecialchars($dbLabel) ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <div class="mb-3">
                                    <small class="text-muted">Domain:</small><br>
                                    <a href="<?= ($site['ssl'] ? 'https://' : 'http://') . $site['domain'] ?>" target="_blank" class="text-decoration-none" title="Open site in new tab">
                                        <?= $site['domain'] ?>
                                        <?php if ($site['ssl']): ?><i class="bi bi-shield-check text-success ms-1"></i><?php endif; ?>
                                    </a>
                                    <br>
                                    <?php if ($site['ssl'] && $sslConfigured): ?>
                                        <small class="badge bg-success mt-1" title="SSL is enabled and configured in container">
                                            <i class="bi bi-shield-lock-fill"></i> SSL: Active
                                        </small>
                                    <?php elseif ($site['ssl'] && !$sslConfigured): ?>
                                        <small class="badge bg-warning mt-1" title="SSL enabled in database but not configured in container - recreate site">
                                            <i class="bi bi-exclamation-triangle"></i> SSL: Not Configured
                                        </small>
                                    <?php else: ?>
                                        <small class="badge bg-secondary mt-1" title="SSL is disabled">
                                            <i class="bi bi-shield-slash"></i> SSL: Disabled
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="btn-group w-100" role="group">
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewSite('<?= $site['domain'] ?>', <?= $site['ssl'] ? 'true' : 'false' ?>)" title="View Site">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="window.location.href='/edit/<?= $site['id'] ?>/'" title="Settings & Management">
                                        <i class="bi bi-gear"></i>
                                    </button>
                                    <?php if (canAccessSite($_SESSION['user_id'], $site['id'], 'manage')): ?>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteSite(<?= $site['id'] ?>)" title="Delete Site">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled title="Requires 'Manage' permission">
                                        <i class="bi bi-lock"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create App Modal -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Deploy New Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createForm" onsubmit="createSite(event)">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Application Name</label>
                                <input type="text" class="form-control" name="name" required placeholder="My Awesome Site">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Application Type</label>
                                <select class="form-select" name="type" required onchange="toggleTypeOptions(this.value)">
                                    <option value="">Choose type...</option>
                                    <option value="wordpress">WordPress (Optimized)</option>
                                    <option value="php">PHP Application</option>
                                    <option value="laravel">Laravel</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3" id="phpVersionRow" style="display:none;">
                            <div class="col-md-6">
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
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Domain</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="domain" required placeholder="mysite">
                                    <select class="form-select" name="domain_suffix" style="max-width: 200px;" onchange="toggleSSLOptions(this.value)">
                                        <option value=".test.local">.test.local (Local)</option>
                                        <option value=".localhost">.localhost (Local)</option>
                                        <?php if (!empty($customWildcardDomain)): ?>
                                        <option value="<?= htmlspecialchars($customWildcardDomain) ?>"><?= htmlspecialchars($customWildcardDomain) ?> (Custom)</option>
                                        <?php endif; ?>
                                        <option value=":8080">:8080 (Port-based)</option>
                                        <option value=":8081">:8081 (Port-based)</option>
                                        <option value=":8082">:8082 (Port-based)</option>
                                        <option value="custom">Custom Domain</option>
                                    </select>
                                </div>
                                <div class="form-text">For virtual servers, use port-based or IP access</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SSL Certificate</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ssl" id="sslCheck" onchange="toggleSSLChallengeOptions()">
                                    <label class="form-check-label" for="sslCheck">
                                        Enable SSL (Let's Encrypt)
                                    </label>
                                </div>
                                <div class="form-text">Only available for custom domains</div>
                            </div>
                        </div>
                        
                        <div id="sslChallengeOptions" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">SSL Challenge Method</label>
                                <select class="form-select" name="ssl_challenge" id="sslChallengeMethod" onchange="toggleDNSProviderOptions(this.value)">
                                    <option value="http">HTTP Challenge (Port 80 must be accessible)</option>
                                    <option value="dns">DNS Challenge (Works behind firewall, supports wildcards)</option>
                                </select>
                                <div class="form-text">
                                    <strong>HTTP:</strong> Simple, requires port 80 open to internet<br>
                                    <strong>DNS:</strong> Works anywhere, requires DNS provider API access
                                </div>
                            </div>
                            
                            <div id="dnsProviderOptions" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>DNS Challenge Setup:</strong> You'll need API credentials from your DNS provider.
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">DNS Provider</label>
                                        <select class="form-select" name="dns_provider" id="dnsProvider" onchange="showDNSProviderFields(this.value)">
                                            <option value="">Choose provider...</option>
                                            <option value="cloudflare">Cloudflare</option>
                                            <option value="route53">AWS Route53</option>
                                            <option value="digitalocean">DigitalOcean</option>
                                            <option value="gcp">Google Cloud DNS</option>
                                            <option value="azure">Azure DNS</option>
                                            <option value="namecheap">Namecheap</option>
                                            <option value="godaddy">GoDaddy</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Cloudflare Fields -->
                                <div id="cloudflareFields" class="dns-provider-fields" style="display: none;">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Cloudflare Email</label>
                                            <input type="email" class="form-control" name="cf_email" placeholder="your@email.com">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Cloudflare API Key</label>
                                            <input type="password" class="form-control" name="cf_api_key" placeholder="Global API Key">
                                        </div>
                                    </div>
                                    <div class="form-text mb-3">
                                        Get your API key from: <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare Dashboard</a>
                                    </div>
                                </div>
                                
                                <!-- Route53 Fields -->
                                <div id="route53Fields" class="dns-provider-fields" style="display: none;">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">AWS Access Key ID</label>
                                            <input type="text" class="form-control" name="aws_access_key" placeholder="AKIAIOSFODNN7EXAMPLE">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">AWS Secret Access Key</label>
                                            <input type="password" class="form-control" name="aws_secret_key" placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">AWS Region</label>
                                        <input type="text" class="form-control" name="aws_region" placeholder="us-east-1" value="us-east-1">
                                    </div>
                                </div>
                                
                                <!-- DigitalOcean Fields -->
                                <div id="digitaloceanFields" class="dns-provider-fields" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">DigitalOcean API Token</label>
                                        <input type="password" class="form-control" name="do_auth_token" placeholder="dop_v1_...">
                                    </div>
                                    <div class="form-text mb-3">
                                        Generate token at: <a href="https://cloud.digitalocean.com/account/api/tokens" target="_blank">DigitalOcean API</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="customDomainField" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Custom Domain</label>
                                <input type="text" class="form-control" name="custom_domain" placeholder="example.com">
                                <div class="form-text">Make sure this domain points to your server's IP address</div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Testing on Virtual Server:</strong> Use port-based domains (e.g., :8080) or access via IP address. 
                            You can also add entries to your local hosts file for .test.local domains.
                        </div>

                        <div id="wordpressOptions" style="display: none;">
                            <hr>
                            <h6><i class="bi bi-wordpress text-primary me-2"></i>WordPress Configuration</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Admin Username</label>
                                    <input type="text" class="form-control" name="wp_admin" placeholder="admin">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Admin Password</label>
                                    <input type="password" class="form-control" name="wp_password" placeholder="Generate strong password">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin Email</label>
                                <input type="email" class="form-control" name="wp_email" placeholder="admin@example.com">
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="wp_optimize" id="wpOptimize" checked>
                                <label class="form-check-label" for="wpOptimize">
                                    Enable performance optimizations (Redis, OpCache, CDN-ready)
                                </label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Database Configuration</label>
                                <select class="form-select" name="wp_db_type" id="wpDbType">
                                    <option value="shared" selected>Shared Database (Recommended - uses global MariaDB)</option>
                                    <option value="dedicated">Dedicated Database (Separate MariaDB container per site)</option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Shared:</strong> Faster, uses less resources, easier to manage<br>
                                    <strong>Dedicated:</strong> Complete isolation, better for high-traffic sites
                                </div>
                            </div>
                        </div>

                        <div id="phpOptions" style="display: none;">
                            <hr>
                            <h6><i class="bi bi-code-slash text-primary me-2"></i>PHP Configuration</h6>
                            <div class="mb-3">
                                <label class="form-label">Database</label>
                                <select class="form-select" name="php_db_type" id="phpDbType">
                                    <option value="none" selected>No Database</option>
                                    <option value="mysql">MySQL/MariaDB</option>
                                    <option value="postgres">PostgreSQL</option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Select a database if your PHP app needs one
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="php_redis" id="phpRedis">
                                <label class="form-check-label" for="phpRedis">
                                    <i class="bi bi-lightning-charge me-1"></i>Enable Redis Cache
                                </label>
                                <div class="form-text">Redis for session storage and data caching</div>
                            </div>
                        </div>

                        <div id="laravelOptions" style="display: none;">
                            <hr>
                            <h6><i class="bi bi-lightning text-primary me-2"></i>Laravel Configuration</h6>
                            <div class="mb-3">
                                <label class="form-label">Database</label>
                                <select class="form-select" name="laravel_db_type" id="laravelDbType">
                                    <option value="mysql" selected>MySQL/MariaDB (Recommended)</option>
                                    <option value="postgres">PostgreSQL</option>
                                    <option value="none">No Database</option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Laravel works best with MySQL or PostgreSQL
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="laravel_redis" id="laravelRedis" checked>
                                <label class="form-check-label" for="laravelRedis">
                                    <i class="bi bi-lightning-charge me-1"></i>Enable Redis Cache
                                </label>
                                <div class="form-text">Redis for cache, sessions, and queues (Recommended)</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-rocket me-2"></i>Deploy Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit App Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm" onsubmit="updateSite(event)">
                    <input type="hidden" name="site_id" id="editSiteId">
                    <input type="hidden" name="type" id="editType">
                    <input type="hidden" name="container_name" id="editContainerName">
                    
                    <div class="modal-body">
                        <!-- Site Information -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-info-circle me-2"></i>Site Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Application Name</label>
                                    <input type="text" class="form-control" name="name" id="editName" required>
                                    <div class="form-text">Display name for your application</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Application Type</label>
                                    <input type="text" class="form-control" id="editTypeDisplay" disabled>
                                    <div class="form-text">Type cannot be changed after creation</div>
                                </div>
                            </div>
                        </div>

                        <!-- Domain Configuration -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-globe me-2"></i>Domain Configuration</h6>
                            <div class="mb-3">
                                <label class="form-label">Domain</label>
                                <input type="text" class="form-control" name="domain" id="editDomain" required>
                                <div class="form-text">
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                    <strong>Warning:</strong> Changing the domain will update Traefik routing. Make sure to update your DNS/hosts file.
                                </div>
                            </div>
                        </div>

                        <!-- SSL & Status -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-shield-check me-2"></i>Security & Status</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">SSL Certificate</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="ssl" id="editSsl" role="switch">
                                        <label class="form-check-label" for="editSsl">
                                            Enable HTTPS (Let's Encrypt)
                                        </label>
                                    </div>
                                    <div class="form-text">Requires custom domain with valid DNS</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Container Status</label>
                                    <select class="form-select" name="status" id="editStatus" disabled>
                                        <option value="running">Running</option>
                                        <option value="stopped">Stopped</option>
                                    </select>
                                    <div class="form-text">Status is managed automatically</div>
                                </div>
                            </div>
                        </div>

                        <!-- Container Info -->
                        <div class="alert alert-secondary">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">Container Name:</small><br>
                                    <code id="editContainerNameDisplay" class="text-dark"></code>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Created:</small><br>
                                    <span id="editCreatedAt"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="passwordForm" onsubmit="changePassword(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('current_password')">
                                    <i class="bi bi-eye" id="current_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('new_password')">
                                    <i class="bi bi-eye" id="new_password_icon"></i>
                                </button>
                                <button class="btn btn-outline-primary" type="button" onclick="generateRandomPassword()" title="Generate random password">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimum 8 characters. Click <i class="bi bi-arrow-clockwise"></i> to generate a secure password.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="bi bi-eye" id="confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        <div id="password_strength" class="mb-3" style="display: none;">
                            <div class="progress" style="height: 5px;">
                                <div id="password_strength_bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="password_strength_text" class="text-muted"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Two-Factor Authentication Modal -->
    <div class="modal fade" id="twoFactorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-check me-2"></i>Two-Factor Authentication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="twoFactorContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading 2FA settings...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>System Update</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="updateContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Checking for updates...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="performUpdateBtn" style="display: none;" onclick="performUpdate()">
                        <i class="bi bi-download me-2"></i>Install Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js?v=3.0.<?= time() ?>"></script>
</body>
</html>