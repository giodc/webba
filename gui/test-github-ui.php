<?php
// Simple test page to verify GitHub UI elements
?>
<!DOCTYPE html>
<html>
<head>
    <title>GitHub UI Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="p-5">
    <div class="container">
        <h1>GitHub UI Test Page</h1>
        <p>This page tests if the GitHub section HTML is rendering correctly.</p>
        
        <hr>
        
        <h3>Test 1: GitHub Section HTML</h3>
        <div class="mb-4" id="editGithubSection">
            <h6 class="text-muted mb-3"><i class="bi bi-github me-2"></i>GitHub Deployment</h6>
            
            <div class="mb-3">
                <label class="form-label">Repository</label>
                <input type="text" class="form-control" name="github_repo" id="editGithubRepo" placeholder="username/repo">
                <div class="form-text">Leave empty to disable GitHub deployment</div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Branch</label>
                    <input type="text" class="form-control" value="main">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Token</label>
                    <input type="password" class="form-control" placeholder="ghp_...">
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong>✅ If you can see this section, the HTML is working!</strong>
            </div>
        </div>
        
        <hr>
        
        <h3>Test 2: Check Files in Container</h3>
        <pre><?php
        echo "Checking files...\n\n";
        
        $files = [
            '/var/www/html/includes/encryption.php',
            '/var/www/html/includes/github-deploy.php',
            '/var/www/html/migrations/add_github_fields.php'
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                echo "✅ $file EXISTS\n";
            } else {
                echo "❌ $file MISSING\n";
            }
        }
        ?></pre>
        
        <hr>
        
        <h3>Test 3: Check Database</h3>
        <pre><?php
        require_once 'includes/functions.php';
        $db = initDatabase();
        $cols = $db->query('PRAGMA table_info(sites)')->fetchAll(PDO::FETCH_ASSOC);
        
        echo "GitHub columns in database:\n\n";
        foreach($cols as $col) {
            if(strpos($col['name'], 'github') !== false) {
                echo "✅ " . $col['name'] . "\n";
            }
        }
        ?></pre>
        
        <hr>
        
        <h3>Instructions</h3>
        <ol>
            <li>If you see the GitHub section above with input fields: <strong>HTML is working ✅</strong></li>
            <li>Go back to dashboard and try editing a PHP or Laravel site</li>
            <li>Press <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd> to hard refresh</li>
            <li>Scroll down in the edit modal to find "GitHub Deployment" section</li>
        </ol>
        
        <a href="/" class="btn btn-primary">← Back to Dashboard</a>
    </div>
</body>
</html>
