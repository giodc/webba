<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication
requireAuth();

$currentUser = getCurrentUser();

// Get log type from query parameter
$logType = $_GET['type'] ?? 'access';
$lines = $_GET['lines'] ?? 100;

// Read Traefik logs
$logFile = '';
$logContent = '';

if ($logType === 'acme') {
    // Check ACME JSON for certificate info
    $acmeFile = '/opt/wharftales/ssl/acme.json';
    if (file_exists($acmeFile)) {
        $acmeContent = file_get_contents($acmeFile);
        $acmeData = json_decode($acmeContent, true);
        
        if ($acmeData && isset($acmeData['letsencrypt'])) {
            $certificates = $acmeData['letsencrypt']['Certificates'] ?? [];
            $logContent = "Total Certificates: " . count($certificates) . "\n\n";
            
            foreach ($certificates as $cert) {
                $domain = $cert['domain']['main'] ?? 'Unknown';
                $logContent .= "Domain: " . $domain . "\n";
                $logContent .= "Certificate: " . (isset($cert['certificate']) ? 'Present' : 'Missing') . "\n";
                $logContent .= "Key: " . (isset($cert['key']) ? 'Present' : 'Missing') . "\n";
                $logContent .= "---\n\n";
            }
        } else {
            $logContent = "No certificates found in acme.json";
        }
    } else {
        $logContent = "ACME file not found at: $acmeFile";
    }
} else {
    // Read container logs
    $containerName = 'wharftales_traefik';
    $command = "docker logs --tail " . intval($lines) . " " . escapeshellarg($containerName) . " 2>&1";
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0) {
        $logContent = implode("\n", $output);
    } else {
        $logContent = "Failed to read Traefik logs";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traefik Logs - WharfTales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <style>
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 70vh;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-viewer .error {
            color: #f48771;
        }
        .log-viewer .warning {
            color: #dcdcaa;
        }
        .log-viewer .success {
            color: #4ec9b0;
        }
            color: #9cdcfe;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-file-text me-2"></i>Traefik Logs & SSL Status</h2>
                    <a href="/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <!-- Log Type Selector -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <a href="?type=container&lines=100" class="btn btn-<?= $logType === 'container' ? 'primary' : 'outline-primary' ?>">
                                <i class="bi bi-terminal"></i> Container Logs
                            </a>
                            <a href="?type=acme" class="btn btn-<?= $logType === 'acme' ? 'primary' : 'outline-primary' ?>">
                                <i class="bi bi-shield-check"></i> SSL Certificates
                            </a>
                        </div>
                        
                        <?php if ($logType === 'container'): ?>
                        <div class="btn-group ms-3" role="group">
                            <a href="?type=container&lines=50" class="btn btn-sm btn-<?= $lines == 50 ? 'secondary' : 'outline-secondary' ?>">50 lines</a>
                            <a href="?type=container&lines=100" class="btn btn-sm btn-<?= $lines == 100 ? 'secondary' : 'outline-secondary' ?>">100 lines</a>
                            <a href="?type=container&lines=500" class="btn btn-sm btn-<?= $lines == 500 ? 'secondary' : 'outline-secondary' ?>">500 lines</a>
                            <a href="?type=container&lines=1000" class="btn btn-sm btn-<?= $lines == 1000 ? 'secondary' : 'outline-secondary' ?>">1000 lines</a>
                        </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-success ms-3" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Log Viewer -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-file-earmark-text me-2"></i>
                            <?= $logType === 'acme' ? 'SSL Certificates Status' : 'Traefik Container Logs' ?>
                        </span>
                        <button class="btn btn-sm btn-outline-light" onclick="copyLogs()">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="log-viewer" id="logContent"><?= htmlspecialchars($logContent) ?></div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>Understanding SSL Certificate Issues
                    </div>
                    <div class="card-body">
                        <h6>Common SSL Error Messages:</h6>
                        <ul>
                            <li><strong>"acme: error: 403"</strong> - Domain not pointing to this server or port 80 blocked</li>
                            <li><strong>"timeout"</strong> - Firewall blocking Let's Encrypt validation</li>
                            <li><strong>"no such host"</strong> - DNS not configured correctly</li>
                            <li><strong>"rate limit"</strong> - Too many certificate requests (Let's Encrypt limit)</li>
                        </ul>
                        
                        <h6 class="mt-3">Requirements for SSL:</h6>
                        <ul>
                            <li>Domain must point to your server's IP address</li>
                            <li>Port 80 must be open and accessible from the internet</li>
                            <li>Traefik container must be running</li>
                            <li>Container must have SSL labels configured</li>
                        </ul>
                        
                        <h6 class="mt-3">Checking SSL Status:</h6>
                        <ul>
                            <li>Look for "SSL: Active" badge on dashboard</li>
                            <li>Check "SSL Certificates" tab to see if certificate was issued</li>
                            <li>Review container logs for ACME challenge errors</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom
        const logViewer = document.querySelector('.log-viewer');
        if (logViewer) {
            logViewer.scrollTop = logViewer.scrollHeight;
        }
        
        // Highlight error/warning lines
        const logContent = document.getElementById('logContent');
        if (logContent) {
            const text = logContent.textContent;
            const lines = text.split('\n');
            let html = '';
            
            lines.forEach(line => {
                if (line.toLowerCase().includes('error') || line.toLowerCase().includes('failed')) {
                    html += '<span class="error">' + escapeHtml(line) + '</span>\n';
                } else if (line.toLowerCase().includes('warn')) {
                    html += '<span class="warning">' + escapeHtml(line) + '</span>\n';
                } else if (line.toLowerCase().includes('certificate') || line.toLowerCase().includes('success')) {
                    html += '<span class="success">' + escapeHtml(line) + '</span>\n';
                } else if (line.toLowerCase().includes('acme')) {
                    html += '<span class="info">' + escapeHtml(line) + '</span>\n';
                } else {
                    html += escapeHtml(line) + '\n';
                }
            });
            
            logContent.innerHTML = html;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
