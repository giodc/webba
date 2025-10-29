<?php http_response_code(200); ?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{SITE_NAME}} - Ready</title>
    <style>
        body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
        .container{max-width:600px;padding:40px}
        .card{background:rgba(255,255,255,0.1);backdrop-filter:blur(10px);padding:40px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.2)}
        h1{margin:0 0 10px;font-size:32px;font-weight:700}
        .subtitle{font-size:18px;opacity:.9;margin-bottom:30px}
        .info{background:rgba(0,0,0,.2);padding:20px;border-radius:8px;margin-top:20px}
        .info-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.1)}
        .info-row:last-child{border:0}
        .label{opacity:.8}
        .value{font-weight:600}
        .badge{display:inline-block;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:14px;margin-top:10px}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🚀 {{SITE_NAME}}</h1>
            <div class="subtitle">Your PHP application is ready!</div>
            
            <div class="info">
                <div class="info-row">
                    <span class="label">PHP Version:</span>
                    <span class="value"><?php echo phpversion(); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Server:</span>
                    <span class="value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Document Root:</span>
                    <span class="value">/var/www/html</span>
                </div>
                <div class="info-row">
                    <span class="label">Memory Limit:</span>
                    <span class="value"><?php echo ini_get('memory_limit'); ?></span>
                </div>
            </div>
            
            <div style="margin-top:20px;opacity:.8;font-size:14px">
                <strong>Next steps:</strong><br>
                • Upload your PHP files via SFTP<br>
                • Or use the file manager in WharfTales<br>
                • Replace this index.php with your application
            </div>
            
            <div class="badge">Powered by WharfTales</div>
        </div>
    </div>
</body>
</html>
