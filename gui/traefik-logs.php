<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect to SSL URL if dashboard has SSL enabled and request is from :9000
redirectToSSLIfEnabled();

// Require authentication
requireAuth();

// Initialize database
$db = initDatabase();

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
    // Read container logs with timestamps
    $containerName = 'wharftales_traefik';
    $command = "docker logs --timestamps --tail " . intval($lines) . " " . escapeshellarg($containerName) . " 2>&1";
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
        .log-viewer .info {
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
                        
                        <div class="form-check form-switch d-inline-block ms-3">
                            <input class="form-check-input" type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh()">
                            <label class="form-check-label" for="autoRefresh">
                                Auto-refresh (<span id="refreshCountdown">5</span>s)
                            </label>
                        </div>
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

    <?php include 'includes/modals.php'; ?>
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js?v=3.0.<?= time() ?>"></script>
    <script>
        let autoRefreshInterval = null;
        let countdownInterval = null;
        let countdown = 5;
        const REFRESH_INTERVAL = 5000; // 5 seconds
        
        // Auto-scroll to bottom
        function scrollToBottom() {
            const logViewer = document.querySelector('.log-viewer');
            if (logViewer) {
                logViewer.scrollTop = logViewer.scrollHeight;
            }
        }
        
        // Initial scroll
        scrollToBottom();
        
        // Highlight error/warning lines
        function highlightLogs() {
            const logContent = document.getElementById('logContent');
            if (logContent) {
                const text = logContent.textContent;
                const lines = text.split('\n');
                let html = '';
                
                lines.forEach(line => {
                    // Extract timestamp if present (format: 2025-10-31T21:45:00.123456789Z)
                    const timestampMatch = line.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z)\s+(.*)$/);
                    let timestamp = '';
                    let logLine = line;
                    
                    if (timestampMatch) {
                        timestamp = '<span style="color: #858585;">' + escapeHtml(timestampMatch[1]) + '</span> ';
                        logLine = timestampMatch[2];
                    }
                    
                    if (logLine.toLowerCase().includes('error') || logLine.toLowerCase().includes('failed')) {
                        html += timestamp + '<span class="error">' + escapeHtml(logLine) + '</span>\n';
                    } else if (logLine.toLowerCase().includes('warn')) {
                        html += timestamp + '<span class="warning">' + escapeHtml(logLine) + '</span>\n';
                    } else if (logLine.toLowerCase().includes('certificate') || logLine.toLowerCase().includes('success')) {
                        html += timestamp + '<span class="success">' + escapeHtml(logLine) + '</span>\n';
                    } else if (logLine.toLowerCase().includes('acme')) {
                        html += timestamp + '<span class="info">' + escapeHtml(logLine) + '</span>\n';
                    } else {
                        html += timestamp + escapeHtml(logLine) + '\n';
                    }
                });
                
                logContent.innerHTML = html;
            }
        }
        
        // Initial highlight
        highlightLogs();
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function copyLogs() {
            const logContent = document.getElementById('logContent');
            const text = logContent.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('Logs copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy logs to clipboard');
            });
        }
        
        // Auto-refresh functionality
        function toggleAutoRefresh() {
            const checkbox = document.getElementById('autoRefresh');
            
            if (checkbox.checked) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        }
        
        function startAutoRefresh() {
            countdown = 5;
            updateCountdown();
            
            // Start countdown
            countdownInterval = setInterval(() => {
                countdown--;
                updateCountdown();
                
                if (countdown <= 0) {
                    refreshLogs();
                    countdown = 5;
                }
            }, 1000);
        }
        
        function stopAutoRefresh() {
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            document.getElementById('refreshCountdown').textContent = '5';
        }
        
        function updateCountdown() {
            document.getElementById('refreshCountdown').textContent = countdown;
        }
        
        async function refreshLogs() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const response = await fetch(window.location.pathname + '?' + urlParams.toString());
                const html = await response.text();
                
                // Extract log content from response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newLogContent = doc.getElementById('logContent');
                
                if (newLogContent) {
                    const currentLogContent = document.getElementById('logContent');
                    const wasAtBottom = isScrolledToBottom();
                    
                    currentLogContent.textContent = newLogContent.textContent;
                    highlightLogs();
                    
                    // Auto-scroll if user was at bottom
                    if (wasAtBottom) {
                        scrollToBottom();
                    }
                    
                    // Show refresh indicator
                    showRefreshIndicator();
                }
            } catch (error) {
                console.error('Failed to refresh logs:', error);
            }
        }
        
        function isScrolledToBottom() {
            const logViewer = document.querySelector('.log-viewer');
            if (!logViewer) return false;
            
            const threshold = 50; // pixels from bottom
            return (logViewer.scrollHeight - logViewer.scrollTop - logViewer.clientHeight) < threshold;
        }
        
        function showRefreshIndicator() {
            const btn = document.querySelector('.btn-success');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Updated';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-info');
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('btn-info');
                btn.classList.add('btn-success');
            }, 1000);
        }
        
        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            stopAutoRefresh();
        });
    </script>
</body>
</html>
