<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication
requireAuth();

$db = initDatabase();
$currentUser = getCurrentUser();

// Get site ID from URL
$siteId = $_GET['id'] ?? null;
if (!$siteId) {
    header('Location: /');
    exit;
}

$site = getSiteById($db, $siteId);
if (!$site) {
    header('Location: /');
    exit;
}

// Get container status
$containerStatus = getDockerContainerStatus($site['container_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Site - <?= htmlspecialchars($site['name']) ?> - Webbadeploy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <style>
        .sidebar {
            background: #f8f9fa;
            padding: 1.5rem 0;
            min-height: calc(100vh - 56px);
        }
        .sidebar-nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav-item:hover {
            background: #f3f4f6;
            color: #1f2937;
            border-left-color: #9ca3af;
        }
        .sidebar-nav-item.active {
            background: #e5e7eb;
            color: #1f2937;
            border-left-color: #4b5563;
            font-weight: 500;
        }
        .sidebar-nav-item i {
            width: 20px;
            margin-right: 10px;
        }
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6b7280;
            width: 180px;
            flex-shrink: 0;
            font-size: 0.875rem;
        }
        .info-value {
            color: #1f2937;
            flex: 1;
        }
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.8125rem;
        }
        .status-running { background: #4b5563; color: #fff; }
        .status-stopped { background: #9ca3af; color: #fff; }
    </style>
</head>
<body>
    <!-- Navbar (same as dashboard) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-cloud-arrow-up me-2"></i>Webbadeploy
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/">
                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Site Name Header -->
    <div class="container mt-4">
        <h2 class="mb-0">
            <i class="bi bi-<?= getAppIcon($site['type']) ?> me-2"></i><?= htmlspecialchars($site['name']) ?>
        </h2>
    </div>

    <!-- Main Content with Two Columns -->
    <div class="container mt-4">
        <div class="row">
            <!-- Left Sidebar -->
            <div class="col-md-3 p-0">
                <div class="sidebar">
                    <nav>
                        <a href="#overview" class="sidebar-nav-item active" data-section="overview">
                            <i class="bi bi-speedometer2"></i>
                            <span>Overview</span>
                        </a>
                        <a href="#settings" class="sidebar-nav-item" data-section="settings">
                            <i class="bi bi-gear"></i>
                            <span>Settings</span>
                        </a>
                        <a href="#domain" class="sidebar-nav-item" data-section="domain">
                            <i class="bi bi-globe"></i>
                            <span>Domain & SSL</span>
                        </a>
                        <a href="#container" class="sidebar-nav-item" data-section="container">
                            <i class="bi bi-box"></i>
                            <span>Container</span>
                        </a>
                        <a href="#files" class="sidebar-nav-item" data-section="files">
                            <i class="bi bi-folder"></i>
                            <span>Files & Volumes</span>
                        </a>
                        <a href="#logs" class="sidebar-nav-item" data-section="logs">
                            <i class="bi bi-terminal"></i>
                            <span>Logs</span>
                        </a>
                        <a href="#sftp" class="sidebar-nav-item" data-section="sftp">
                            <i class="bi bi-hdd-network"></i>
                            <span>SFTP Access</span>
                        </a>
                        <a href="#backup" class="sidebar-nav-item" data-section="backup">
                            <i class="bi bi-cloud-download"></i>
                            <span>Backup & Restore</span>
                        </a>
                        <a href="#danger" class="sidebar-nav-item" data-section="danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span>Danger Zone</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Right Content Area -->
            <div class="col-md-9">
            <!-- Overview Section -->
            <div id="overview-section" class="content-section">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-info-circle me-2"></i>Site Information
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <div class="info-label">Site Name</div>
                                    <div class="info-value"><?= htmlspecialchars($site['name']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Application Type</div>
                                    <div class="info-value">
                                        <i class="bi bi-<?= getAppIcon($site['type']) ?> me-2"></i><?= ucfirst($site['type']) ?>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Domain</div>
                                    <div class="info-value">
                                        <a href="http://<?= htmlspecialchars($site['domain']) ?>" target="_blank">
                                            <?= htmlspecialchars($site['domain']) ?>
                                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">SSL Certificate</div>
                                    <div class="info-value">
                                        <?php if ($site['ssl']): ?>
                                            <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Enabled</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Container Name</div>
                                    <div class="info-value"><code><?= htmlspecialchars($site['container_name']) ?></code></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Created</div>
                                    <div class="info-value"><?= date('F j, Y g:i A', strtotime($site['created_at'])) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="status-badge status-<?= $containerStatus ?>">
                                            <i class="bi bi-circle-fill me-1"></i><?= ucfirst($containerStatus) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-lightning me-2"></i>Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-outline-primary w-100" onclick="viewSite('<?= $site['domain'] ?>', <?= $site['ssl'] ? 'true' : 'false' ?>)">
                                            <i class="bi bi-eye me-2"></i>Open Site
                                        </button>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-outline-success w-100" onclick="restartContainer()">
                                            <i class="bi bi-arrow-clockwise me-2"></i>Restart Container
                                        </button>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-outline-info w-100" onclick="viewLogs()">
                                            <i class="bi bi-terminal me-2"></i>View Logs
                                        </button>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-outline-warning w-100" onclick="backupSite()">
                                            <i class="bi bi-cloud-download me-2"></i>Backup Site
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-graph-up me-2"></i>Container Stats
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted">Status</small>
                                    <h5 id="containerStatus"><?= ucfirst($containerStatus) ?></h5>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Uptime</small>
                                    <h5 id="containerUptime">Loading...</h5>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Volume Size</small>
                                    <h5 id="volumeSize">Loading...</h5>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary w-100" onclick="refreshStats()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-link-45deg me-2"></i>Quick Links
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="http://<?= htmlspecialchars($site['domain']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-globe me-1"></i>Visit Site
                                    </a>
                                    <a href="http://192.168.64.4:8080/dashboard/#/http/routers" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-diagram-3 me-1"></i>Traefik Dashboard
                                    </a>
                                    <?php if ($site['type'] === 'wordpress'): ?>
                                    <a href="http://<?= htmlspecialchars($site['domain']) ?>/wp-admin" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-wordpress me-1"></i>WP Admin
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Section -->
            <div id="settings-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-gear me-2"></i>General Settings
                    </div>
                    <div class="card-body">
                        <form id="settingsForm">
                            <div class="mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-control" id="siteName" value="<?= htmlspecialchars($site['name']) ?>">
                                <div class="form-text">Display name for your application</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Application Type</label>
                                <input type="text" class="form-control" value="<?= ucfirst($site['type']) ?>" disabled>
                                <div class="form-text">Type cannot be changed after creation</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Environment Variables -->
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-code-square me-2"></i>Environment Variables
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> Changes to environment variables require a container restart to take effect.
                        </div>
                        
                        <div id="envVarsList">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading environment variables...</p>
                            </div>
                        </div>
                        
                        <button class="btn btn-success mt-3" onclick="showAddEnvVarModal()">
                            <i class="bi bi-plus-circle me-2"></i>Add Variable
                        </button>
                        <button class="btn btn-primary mt-3" onclick="saveEnvVars()">
                            <i class="bi bi-save me-2"></i>Save & Restart Container
                        </button>
                    </div>
                </div>
            </div>

            <!-- Domain Section -->
            <div id="domain-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-globe me-2"></i>Domain Configuration
                    </div>
                    <div class="card-body">
                        <form id="domainForm">
                            <div class="mb-3">
                                <label class="form-label">Domain</label>
                                <input type="text" class="form-control" id="siteDomain" value="<?= htmlspecialchars($site['domain']) ?>">
                                <div class="form-text">
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                    Changing the domain requires container restart and DNS/hosts file update
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sslEnabled" <?= $site['ssl'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sslEnabled">
                                        Enable HTTPS (Let's Encrypt)
                                    </label>
                                </div>
                                <div class="form-text">Requires custom domain with valid DNS pointing to your server</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Update Domain
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Container Section -->
            <div id="container-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-box me-2"></i>Container Management
                    </div>
                    <div class="card-body">
                        <p>Container actions and management</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-success" onclick="startContainer()">
                                <i class="bi bi-play-fill me-2"></i>Start Container
                            </button>
                            <button class="btn btn-warning" onclick="restartContainer()">
                                <i class="bi bi-arrow-clockwise me-2"></i>Restart Container
                            </button>
                            <button class="btn btn-danger" onclick="stopContainer()">
                                <i class="bi bi-stop-fill me-2"></i>Stop Container
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Files Section -->
            <div id="files-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-folder me-2"></i>Files & Volumes
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Volume:</strong> <code><?= $site['container_name'] ?>_data</code>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>File Manager</strong><br>
                            Browse and manage your site files directly in the container.
                        </div>
                        
                        <!-- File Browser -->
                        <div id="fileBrowser">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="navigateUp()">
                                        <i class="bi bi-arrow-up"></i> Up
                                    </button>
                                    <span class="ms-2" id="currentPath">/var/www/html</span>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-primary" onclick="showUploadModal()">
                                        <i class="bi bi-upload"></i> Upload
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="showNewFileModal()">
                                        <i class="bi bi-file-plus"></i> New File
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="showNewFolderModal()">
                                        <i class="bi bi-folder-plus"></i> New Folder
                                    </button>
                                </div>
                            </div>
                            
                            <!-- File List -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50"><i class="bi bi-file-earmark"></i></th>
                                            <th>Name</th>
                                            <th width="120">Size</th>
                                            <th width="180">Modified</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="fileList">
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                <p class="mt-2 text-muted">Loading files...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Be careful when editing or deleting files. Always backup before making changes.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Section -->
            <div id="logs-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-terminal me-2"></i>Container Logs
                    </div>
                    <div class="card-body">
                        <pre id="logOutput" style="background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 4px; max-height: 500px; overflow-y: auto;">Loading logs...</pre>
                        <button class="btn btn-secondary mt-2" onclick="refreshLogs()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh Logs
                        </button>
                    </div>
                </div>
            </div>

            <!-- SFTP Section -->
            <div id="sftp-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-hdd-network me-2"></i>SFTP Access
                    </div>
                    <div class="card-body">
                        <?php if ($site['sftp_enabled']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>SFTP is enabled</strong> - You can access your files via SFTP
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Host</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sftpHost" value="<?= $_SERVER['SERVER_ADDR'] ?? 'localhost' ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('sftpHost')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Port</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sftpPort" value="<?= $site['sftp_port'] ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('sftpPort')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Username</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sftpUsername" value="<?= htmlspecialchars($site['sftp_username']) ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('sftpUsername')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="sftpPassword" value="<?= htmlspecialchars($site['sftp_password']) ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="togglePasswordVisibility('sftpPassword')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('sftpPassword')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Connection String:</strong><br>
                                <code>sftp://<?= htmlspecialchars($site['sftp_username']) ?>@<?= $_SERVER['SERVER_ADDR'] ?? 'localhost' ?>:<?= $site['sftp_port'] ?></code>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-warning" onclick="regenerateSFTPPassword()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Regenerate Password
                                </button>
                                <button class="btn btn-danger" onclick="disableSFTP()">
                                    <i class="bi bi-x-circle me-2"></i>Disable SFTP Access
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>SFTP is disabled</strong> - Enable SFTP to access your files remotely
                            </div>
                            
                            <p>SFTP (SSH File Transfer Protocol) allows you to securely access and manage your site files using an SFTP client like FileZilla, WinSCP, or Cyberduck.</p>
                            
                            <h6 class="mt-4">Features:</h6>
                            <ul>
                                <li>Secure file transfer over SSH</li>
                                <li>Direct access to your site's files</li>
                                <li>Upload, download, and edit files</li>
                                <li>Automatic credentials generation</li>
                            </ul>
                            
                            <button class="btn btn-primary" onclick="enableSFTP()">
                                <i class="bi bi-check-circle me-2"></i>Enable SFTP Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Backup Section -->
            <div id="backup-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-cloud-download me-2"></i>Backup & Restore
                    </div>
                    <div class="card-body">
                        <p>Backup and restore functionality coming soon...</p>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div id="danger-section" class="content-section" style="display: none;">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                    </div>
                    <div class="card-body">
                        <h6>Delete This Site</h6>
                        <p class="text-muted">Once you delete a site, there is no going back. Please be certain.</p>
                        <button class="btn btn-danger" onclick="deleteSite(<?= $site['id'] ?>)">
                            <i class="bi bi-trash me-2"></i>Delete Site
                        </button>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- File Editor Modal -->
    <div class="modal fade" id="fileEditorModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>Edit File: <span id="editFileName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea id="fileEditorContent" class="form-control" rows="20" style="font-family: 'Courier New', monospace; font-size: 14px;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveFileContent()">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const siteId = <?= $siteId ?>;
        const siteName = '<?= addslashes($site['name']) ?>';
        const containerName = '<?= addslashes($site['container_name']) ?>';
        const siteDomain = '<?= addslashes($site['domain']) ?>';
        const siteSSL = <?= $site['ssl'] ? 'true' : 'false' ?>;

        // Navigation
        document.querySelectorAll('.sidebar-nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const section = this.dataset.section;
                
                // Update active state
                document.querySelectorAll('.sidebar-nav-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Show section
                document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
                document.getElementById(section + '-section').style.display = 'block';
            });
        });

        // Settings Form
        document.getElementById('settingsForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const name = document.getElementById('siteName').value;
            
            try {
                const response = await fetch('/api.php?action=update_site', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        site_id: siteId,
                        name: name,
                        domain: siteDomain,
                        ssl: siteSSL
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    alert('Settings updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        });

        // Domain Form
        document.getElementById('domainForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const domain = document.getElementById('siteDomain').value;
            const ssl = document.getElementById('sslEnabled').checked;
            
            if (!confirm('Changing the domain will require a container restart. Continue?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=update_site', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        site_id: siteId,
                        name: siteName,
                        domain: domain,
                        ssl: ssl
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        });

        // Container actions
        function viewSite(domain, ssl) {
            const protocol = ssl ? 'https' : 'http';
            window.open(protocol + '://' + domain, '_blank');
        }

        async function restartContainer() {
            if (!confirm('Restart container ' + containerName + '?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=restart_container&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }

        async function startContainer() {
            try {
                const response = await fetch('/api.php?action=start_container&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }

        async function stopContainer() {
            if (!confirm('Stop container ' + containerName + '?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=stop_container&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }

        function viewLogs() {
            document.querySelector('[data-section="logs"]').click();
            refreshLogs();
        }

        async function refreshLogs() {
            document.getElementById('logOutput').textContent = 'Loading logs...';
            
            try {
                const response = await fetch('/api.php?action=get_logs&id=' + siteId + '&lines=100');
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('logOutput').textContent = result.logs || 'No logs available';
                } else {
                    document.getElementById('logOutput').textContent = 'Error loading logs: ' + result.error;
                }
            } catch (error) {
                document.getElementById('logOutput').textContent = 'Network error: ' + error.message;
            }
        }

        function backupSite() {
            document.querySelector('[data-section="backup"]').click();
        }

        async function refreshStats() {
            try {
                const response = await fetch('/api.php?action=get_stats&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('containerStatus').textContent = result.stats.status.charAt(0).toUpperCase() + result.stats.status.slice(1);
                    document.getElementById('containerUptime').textContent = result.stats.uptime;
                    document.getElementById('volumeSize').textContent = result.stats.volume_size;
                } else {
                    console.error('Error loading stats:', result.error);
                }
            } catch (error) {
                console.error('Network error:', error.message);
            }
        }

        function deleteSite(id) {
            if (!confirm('Are you sure you want to delete this site? This action cannot be undone!')) {
                return;
            }
            
            if (!confirm('Really delete? All data will be lost!')) {
                return;
            }
            
            window.location.href = '/api.php?action=delete_site&id=' + id;
        }
        
        function showAlert(type, message) {
            const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            setTimeout(() => {
                const alert = document.querySelector('.alert:last-of-type');
                if (alert) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
        
        // Auto-refresh stats every 30 seconds
        setInterval(refreshStats, 30000);
        
        // Load stats on page load
        refreshStats();
        
        // SFTP Functions
        async function enableSFTP() {
            if (!confirm('Enable SFTP access for this site?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=enable_sftp&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function disableSFTP() {
            if (!confirm('Disable SFTP access? This will stop the SFTP container.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=disable_sftp&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function regenerateSFTPPassword() {
            if (!confirm('Regenerate SFTP password? The old password will no longer work.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=regenerate_sftp_password&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('sftpPassword').value = result.password;
                    showAlert('success', result.message + ' New password: ' + result.password);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            document.execCommand('copy');
            showAlert('success', 'Copied to clipboard!');
        }
        
        function togglePasswordVisibility(elementId) {
            const element = document.getElementById(elementId);
            const button = event.target.closest('button');
            const icon = button.querySelector('i');
            
            if (element.type === 'password') {
                element.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                element.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // File Manager Functions
        let currentPath = '/var/www/html';
        
        async function loadFiles(path = currentPath) {
            currentPath = path;
            document.getElementById('currentPath').textContent = path;
            
            // Show loading state
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading files...</p>
                    </td>
                </tr>
            `;
            
            try {
                console.log('Fetching files from:', path);
                const response = await fetch(`/api.php?action=list_files&id=${siteId}&path=${encodeURIComponent(path)}`);
                console.log('Response status:', response.status);
                
                const result = await response.json();
                console.log('API result:', result);
                
                if (result.success) {
                    displayFiles(result.files);
                } else {
                    fileList.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Error: ${result.error}</td></tr>`;
                    showAlert('danger', 'Error loading files: ' + result.error);
                }
            } catch (error) {
                console.error('Error loading files:', error);
                fileList.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Network error: ${error.message}</td></tr>`;
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        function displayFiles(files) {
            const fileList = document.getElementById('fileList');
            if (!files || files.length === 0) {
                fileList.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No files found</td></tr>';
                return;
            }
            
            fileList.innerHTML = files.map(file => {
                const icon = file.type === 'directory' ? 'bi-folder-fill text-warning' : 'bi-file-earmark text-primary';
                const size = file.type === 'directory' ? '-' : formatFileSize(file.size);
                
                return `
                    <tr>
                        <td><i class="bi ${icon}"></i></td>
                        <td>
                            ${file.type === 'directory' 
                                ? `<a href="#" onclick="loadFiles('${file.path}'); return false;">${file.name}</a>`
                                : file.name
                            }
                        </td>
                        <td>${size}</td>
                        <td><small>${file.modified}</small></td>
                        <td>
                            ${file.type === 'file' ? `
                                <button class="btn btn-sm btn-outline-info" onclick="editFile('${file.path}', '${file.name}')" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadFile('${file.path}')" title="Download">
                                    <i class="bi bi-download"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteFile('${file.path}')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            ` : `
                                <button class="btn btn-sm btn-outline-primary" onclick="loadFiles('${file.path}')" title="Open">
                                    <i class="bi bi-folder-open"></i>
                                </button>
                            `}
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        function navigateUp() {
            const parts = currentPath.split('/').filter(p => p);
            parts.pop();
            const newPath = '/' + parts.join('/');
            loadFiles(newPath || '/var/www/html');
        }
        
        async function downloadFile(path) {
            window.open(`/api.php?action=download_file&id=${siteId}&path=${encodeURIComponent(path)}`, '_blank');
        }
        
        let currentEditFilePath = '';
        
        async function editFile(path, filename) {
            currentEditFilePath = path;
            document.getElementById('editFileName').textContent = filename;
            document.getElementById('fileEditorContent').value = 'Loading...';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('fileEditorModal'));
            modal.show();
            
            try {
                const response = await fetch('/api.php?action=read_file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: siteId, path: path})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('fileEditorContent').value = result.content;
                } else {
                    document.getElementById('fileEditorContent').value = 'Error loading file: ' + result.error;
                    showAlert('danger', 'Error loading file: ' + result.error);
                }
            } catch (error) {
                document.getElementById('fileEditorContent').value = 'Network error: ' + error.message;
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function saveFileContent() {
            const content = document.getElementById('fileEditorContent').value;
            
            try {
                const response = await fetch('/api.php?action=save_file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id: siteId,
                        path: currentEditFilePath,
                        content: content
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'File saved successfully');
                    bootstrap.Modal.getInstance(document.getElementById('fileEditorModal')).hide();
                    loadFiles(currentPath);
                } else {
                    showAlert('danger', 'Error saving file: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function deleteFile(path) {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=delete_file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: siteId, path: path})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'File deleted successfully');
                    loadFiles(currentPath);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function showUploadModal() {
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.onchange = async (e) => {
                for (let file of e.target.files) {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('id', siteId);
                    formData.append('path', currentPath);
                    
                    try {
                        const response = await fetch('/api.php?action=upload_file', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            showAlert('success', `Uploaded: ${file.name}`);
                            loadFiles(currentPath);
                        } else {
                            showAlert('danger', 'Error: ' + result.error);
                        }
                    } catch (error) {
                        showAlert('danger', 'Error: ' + error.message);
                    }
                }
            };
            input.click();
        }
        
        async function showNewFileModal() {
            const filename = prompt('Enter file name (e.g., index.php):');
            if (!filename) return;
            const content = prompt('Enter file content (optional):') || '';
            
            try {
                const response = await fetch('/api.php?action=create_file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: siteId, path: currentPath, filename: filename, content: content})
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('success', 'File created successfully');
                    loadFiles(currentPath);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Error: ' + error.message);
            }
        }
        
        async function showNewFolderModal() {
            const foldername = prompt('Enter folder name:');
            if (!foldername) return;
            
            try {
                const response = await fetch('/api.php?action=create_folder', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: siteId, path: currentPath, foldername: foldername})
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('success', 'Folder created successfully');
                    loadFiles(currentPath);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Error: ' + error.message);
            }
        }
        
        // Load files when Files section is opened
        document.querySelector('[data-section="files"]')?.addEventListener('click', function() {
            console.log('Files section clicked, loading files...');
            loadFiles('/var/www/html');
        });
        
        // Environment Variables Functions
        let envVars = [];
        
        async function loadEnvVars() {
            try {
                const response = await fetch(`/api.php?action=get_env_vars&id=${siteId}`);
                const result = await response.json();
                
                if (result.success) {
                    envVars = result.env_vars;
                    console.log('Loaded environment variables:', envVars);
                    console.log('Total variables loaded:', envVars.length);
                    displayEnvVars();
                } else {
                    showAlert('danger', 'Error loading environment variables: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        function displayEnvVars() {
            const container = document.getElementById('envVarsList');
            
            if (envVars.length === 0) {
                container.innerHTML = '<p class="text-muted">No environment variables defined.</p>';
                return;
            }
            
            container.innerHTML = envVars.map((env, index) => {
                // Escape HTML entities
                const escapedKey = (env.key || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                const escapedValue = (env.value || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                
                return `
                    <div class="row mb-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" class="form-control" value="${escapedKey}" 
                                   onchange="updateEnvVar(${index}, 'key', this.value)" 
                                   placeholder="VARIABLE_NAME">
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" value="${escapedValue}" 
                                   onchange="updateEnvVar(${index}, 'value', this.value)" 
                                   placeholder="value">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-outline-danger" onclick="removeEnvVar(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function updateEnvVar(index, field, value) {
            envVars[index][field] = value;
        }
        
        function removeEnvVar(index) {
            if (confirm('Remove this environment variable?')) {
                envVars.splice(index, 1);
                displayEnvVars();
            }
        }
        
        function showAddEnvVarModal() {
            const key = prompt('Enter variable name (e.g., MY_VARIABLE):');
            if (!key) return;
            
            const value = prompt('Enter variable value:');
            if (value === null) return;
            
            envVars.push({key: key.toUpperCase(), value: value});
            displayEnvVars();
        }
        
        async function saveEnvVars() {
            if (!confirm('Save environment variables and restart container? This will cause brief downtime.')) {
                return;
            }
            
            // Filter out empty variables
            const validEnvVars = envVars.filter(env => env.key && env.key.trim() !== '');
            
            if (validEnvVars.length === 0) {
                showAlert('warning', 'No valid environment variables to save');
                return;
            }
            
            console.log('Saving env vars:', validEnvVars); // Debug
            
            try {
                const response = await fetch('/api.php?action=save_env_vars', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id: siteId,
                        env_vars: validEnvVars
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Environment variables saved and container restarted!');
                    setTimeout(() => loadEnvVars(), 2000);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        // Load env vars when Settings section is opened
        document.querySelector('[data-section="settings"]').addEventListener('click', function() {
            loadEnvVars();
        });
    </script>
</body>
</html>
