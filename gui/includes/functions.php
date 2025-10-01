<?php

function initDatabase() {
    $dbPath = $_ENV['DB_PATH'] ?? '/app/data/database.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS sites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        type TEXT NOT NULL,
        domain TEXT UNIQUE NOT NULL,
        ssl INTEGER DEFAULT 0,
        ssl_config TEXT,
        status TEXT DEFAULT 'stopped',
        container_name TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        config TEXT
    )");

    return $pdo;
}

function getAllSites($pdo) {
    $stmt = $pdo->query("SELECT * FROM sites ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSiteById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createSite($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO sites (name, type, domain, ssl, ssl_config, container_name, config) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $containerName = $data['container_name'] ?? '';
    $config = json_encode($data['config'] ?? []);
    $sslConfig = isset($data['ssl_config']) ? json_encode($data['ssl_config']) : null;
    
    
    return $stmt->execute([
        $data['name'],
        $data['type'],
        $data['domain'],
        $data['ssl'] ? 1 : 0,
        $sslConfig,
        $containerName,
        $config
    ]);
}

function updateSiteStatus($pdo, $id, $status) {
    $stmt = $pdo->prepare("UPDATE sites SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $id]);
}

function deleteSite($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ?");
    return $stmt->execute([$id]);
}

function generateSiteId($name) {
    return preg_replace('/[^a-z0-9]/', '', strtolower($name)) . '_' . time();
}

function generateRandomString($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

function getAppIcon($type) {
    switch ($type) {
        case 'wordpress': return 'wordpress';
        case 'php': return 'code-slash';
        case 'laravel': return 'lightning';
        default: return 'app';
    }
}

function executeDockerCommand($command) {
    $output = [];
    $returnCode = 0;
    exec("docker $command 2>&1", $output, $returnCode);
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'code' => $returnCode
    ];
}

function executeDockerCompose($composePath, $command) {
    $output = [];
    $returnCode = 0;
    exec("cd " . dirname($composePath) . " && docker-compose -f " . basename($composePath) . " $command 2>&1", $output, $returnCode);
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'code' => $returnCode
    ];
}

function createNginxSiteConfig($site) {
    $sslConfig = '';
    if ($site['ssl']) {
        $sslConfig = "
    listen 443 ssl http2;
    ssl_certificate /etc/ssl/certs/{$site['domain']}/fullchain.pem;
    ssl_certificate_key /etc/ssl/certs/{$site['domain']}/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    add_header Strict-Transport-Security \"max-age=63072000\" always;";
    }

    $config = "server {
    listen 80;
    server_name {$site['domain']};

    {$sslConfig}

    location / {
        proxy_pass http://{$site['container_name']};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}";

    return $config;
}

function createWordPressDockerCompose($site, $config) {
    $siteId = generateSiteId($site['name']);
    $dbUser = 'wp_' . substr(md5($site['name']), 0, 8);
    $dbPass = generateRandomString(16);
    $tablePrefix = 'wp_' . substr(md5($site['name']), 0, 4) . '_';

    $template = file_get_contents('/app/apps/wordpress/docker-compose.template.yml');
    $template = str_replace('{SITE_ID}', $siteId, $template);
    $template = str_replace('{DB_USER}', $dbUser, $template);
    $template = str_replace('{DB_PASS}', $dbPass, $template);
    $template = str_replace('{TABLE_PREFIX}', $tablePrefix, $template);

    return [
        'compose' => $template,
        'site_id' => $siteId,
        'db_config' => [
            'user' => $dbUser,
            'password' => $dbPass,
            'prefix' => $tablePrefix
        ]
    ];
}

function requestSSLCertificate($domain, $email) {
    $command = "certbot certonly --webroot -w /var/www/html -d $domain --email $email --agree-tos --non-interactive";
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);

    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output)
    ];
}

function renewAllSSLCertificates() {
    $command = "certbot renew --quiet";
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);

    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output)
    ];
}

function getDockerContainerStatus($containerName) {
    $result = executeDockerCommand("ps -f name=$containerName --format \"{{.Status}}\"");
    if ($result['success'] && !empty($result['output'])) {
        return strpos($result['output'], 'Up') !== false ? 'running' : 'stopped';
    }
    return 'unknown';
}

function reloadNginx() {
    return executeDockerCommand("exec webbadeploy_nginx nginx -s reload");
}
?>