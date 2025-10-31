<?php
/**
 * Test JSON responses from API - delete after testing
 * Usage: http://your-server:9000/test-json-response.php
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    die('Please login first');
}

$db = initDatabase();
$userId = $_SESSION['user_id'];

// Get a test site ID
$stmt = $db->query("SELECT id FROM sites LIMIT 1");
$testSite = $stmt->fetch(PDO::FETCH_ASSOC);
$siteId = $testSite['id'] ?? null;

?>
<!DOCTYPE html>
<html>
<head>
    <title>API JSON Response Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; max-height: 200px; overflow: auto; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h2>API JSON Response Tester</h2>
        <p>Testing all API endpoints for valid JSON responses...</p>
        
        <div id="results"></div>
        
        <div class="mt-4">
            <button class="btn btn-primary" onclick="runTests()">Run Tests</button>
            <button class="btn btn-secondary" onclick="location.reload()">Refresh</button>
        </div>
        
        <div class="alert alert-warning mt-4">
            <strong>Delete this file after testing:</strong><br>
            <code>rm /opt/wharftales/gui/test-json-response.php</code>
        </div>
    </div>

    <script>
        const tests = [
            { name: 'Check Updates', url: '/api.php?action=check_updates' },
            { name: 'Get Update Info', url: '/api.php?action=get_update_info' },
            { name: 'Check Update Status', url: '/api.php?action=check_update_status' },
            <?php if ($siteId): ?>
            { name: 'Get Site', url: '/api.php?action=get_site&id=<?= $siteId ?>' },
            { name: 'Site Status', url: '/api.php?action=site_status&id=<?= $siteId ?>' },
            { name: 'Get Stats', url: '/api.php?action=get_stats&id=<?= $siteId ?>' },
            { name: 'Get Site Containers', url: '/api.php?action=get_site_containers&id=<?= $siteId ?>' },
            <?php endif; ?>
        ];

        async function testEndpoint(test) {
            const resultDiv = document.createElement('div');
            resultDiv.className = 'test-result';
            
            try {
                const response = await fetch(test.url);
                const text = await response.text();
                
                // Try to parse as JSON
                let json;
                let isValidJson = false;
                try {
                    json = JSON.parse(text);
                    isValidJson = true;
                } catch (e) {
                    // Not valid JSON
                }
                
                if (isValidJson) {
                    resultDiv.className += ' success';
                    resultDiv.innerHTML = `
                        <strong>✓ ${test.name}</strong> - Valid JSON
                        <pre>${JSON.stringify(json, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.className += ' error';
                    resultDiv.innerHTML = `
                        <strong>✗ ${test.name}</strong> - Invalid JSON
                        <p>HTTP Status: ${response.status}</p>
                        <pre>${text.substring(0, 500)}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.className += ' error';
                resultDiv.innerHTML = `
                    <strong>✗ ${test.name}</strong> - Network Error
                    <p>${error.message}</p>
                `;
            }
            
            return resultDiv;
        }

        async function runTests() {
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<p>Running tests...</p>';
            
            for (const test of tests) {
                const result = await testEndpoint(test);
                resultsDiv.appendChild(result);
            }
        }

        // Auto-run on load
        window.addEventListener('load', runTests);
    </script>
</body>
</html>
