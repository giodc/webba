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
        config TEXT,
        sftp_enabled INTEGER DEFAULT 0,
        sftp_username TEXT,
        sftp_password TEXT,
        sftp_port INTEGER,
        db_password TEXT
    )");

    // Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrate existing databases - add columns if they don't exist
    try {
        $result = $pdo->query("PRAGMA table_info(sites)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');
        
        if (!in_array('db_password', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN db_password TEXT");
        }
        if (!in_array('db_type', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN db_type TEXT DEFAULT 'shared'");
        }
    } catch (Exception $e) {
        // Columns might already exist or other error, continue
    }

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

function getSetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['value'] : $default;
}

function setSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
    return $stmt->execute([$key, $value]);
}

function createSite($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO sites (name, type, domain, ssl, ssl_config, container_name, config, db_password, db_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $containerName = $data['container_name'] ?? '';
    $config = json_encode($data['config'] ?? []);
    $sslConfig = isset($data['ssl_config']) ? json_encode($data['ssl_config']) : null;
    $dbPassword = $data['db_password'] ?? null;
    $dbType = $data['db_type'] ?? 'shared';
    
    
    return $stmt->execute([
        $data['name'],
        $data['type'],
        $data['domain'],
        $data['ssl'] ? 1 : 0,
        $sslConfig,
        $containerName,
        $config,
        $dbPassword,
        $dbType
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
    // Use docker inspect for exact container name match
    $output = [];
    $returnCode = 0;
    
    // Try with full path first (more reliable)
    $dockerPaths = ['/usr/bin/docker', '/usr/local/bin/docker', 'docker'];
    
    foreach ($dockerPaths as $dockerCmd) {
        exec("$dockerCmd inspect -f '{{.State.Status}}' " . escapeshellarg($containerName) . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $status = trim($output[0]);
            if ($status === 'running') {
                return 'running';
            } elseif ($status === 'exited' || $status === 'created') {
                return 'stopped';
            }
            return $status;
        }
        
        // Reset for next attempt
        $output = [];
        $returnCode = 0;
    }
    
    return 'unknown';
}

function checkContainerSSLLabels($containerName) {
    // Check if container has SSL Traefik labels configured
    $output = [];
    $returnCode = 0;
    
    $dockerPaths = ['/usr/bin/docker', '/usr/local/bin/docker', 'docker'];
    
    foreach ($dockerPaths as $dockerCmd) {
        // Check for the secure router label which indicates SSL is configured
        exec("$dockerCmd inspect -f '{{index .Config.Labels \"traefik.http.routers." . $containerName . "-secure.tls\"}}' " . escapeshellarg($containerName) . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $hasSSL = trim($output[0]) === 'true';
            return $hasSSL;
        }
        
        // Reset for next attempt
        $output = [];
        $returnCode = 0;
    }
    
    return false;
}

function reloadNginx() {
    return executeDockerCommand("exec webbadeploy_nginx nginx -s reload");
}

function generateSFTPCredentials($siteName) {
    $username = 'sftp_' . preg_replace('/[^a-z0-9]/', '', strtolower($siteName));
    $password = bin2hex(random_bytes(12)); // 24 character password
    return [
        'username' => substr($username, 0, 32), // Limit username length
        'password' => $password
    ];
}

function getNextAvailableSFTPPort($pdo) {
    $stmt = $pdo->query("SELECT MAX(sftp_port) as max_port FROM sites WHERE sftp_enabled = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $maxPort = $result['max_port'] ?? 2221;
    return max(2222, $maxPort + 1);
}

function enableSFTP($pdo, $siteId) {
    $site = getSiteById($pdo, $siteId);
    if (!$site) {
        throw new Exception("Site not found");
    }
    
    // Generate credentials if not exists
    if (empty($site['sftp_username'])) {
        $credentials = generateSFTPCredentials($site['name']);
        $port = getNextAvailableSFTPPort($pdo);
        
        $stmt = $pdo->prepare("UPDATE sites SET sftp_enabled = 1, sftp_username = ?, sftp_password = ?, sftp_port = ? WHERE id = ?");
        $stmt->execute([$credentials['username'], $credentials['password'], $port, $siteId]);
        
        // Reload site data with new credentials
        $site = getSiteById($pdo, $siteId);
    } else {
        $stmt = $pdo->prepare("UPDATE sites SET sftp_enabled = 1 WHERE id = ?");
        $stmt->execute([$siteId]);
        
        // Reload site data
        $site = getSiteById($pdo, $siteId);
    }
    
    // Deploy SFTP container with updated site data
    deploySFTPContainer($site);
    
    return $site;
}

function disableSFTP($pdo, $siteId) {
    $site = getSiteById($pdo, $siteId);
    if (!$site) {
        throw new Exception("Site not found");
    }
    
    // Stop SFTP container
    stopSFTPContainer($site);
    
    $stmt = $pdo->prepare("UPDATE sites SET sftp_enabled = 0 WHERE id = ?");
    $stmt->execute([$siteId]);
    
    return getSiteById($pdo, $siteId);
}

function deploySFTPContainer($site) {
    $containerName = $site['container_name'] . '_sftp';
    
    // Try to find the actual volume name by listing docker volumes
    $volumeSearchPattern = $site['container_name'];
    $result = executeDockerCommand("volume ls --format '{{.Name}}'");
    
    $volumeName = null;
    $useBindMount = false;
    
    if ($result['success'] && !empty($result['output'])) {
        $allVolumes = explode("\n", trim($result['output']));
        foreach ($allVolumes as $vol) {
            if (strpos($vol, $volumeSearchPattern) !== false && strpos($vol, '_data') !== false) {
                $volumeName = $vol;
                break;
            }
        }
    }
    
    // If no volume found, use bind mount to container's /var/www/html
    if (empty($volumeName)) {
        $useBindMount = true;
        // Create a directory for this site's files
        $bindPath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/html";
        if (!is_dir($bindPath)) {
            if (!mkdir($bindPath, 0777, true)) {
                throw new Exception("Failed to create SFTP directory: {$bindPath}");
            }
            // Set proper permissions using chmod instead of chown (which may fail in container)
            chmod($bindPath, 0777);
        }
    }
    
    // Create SFTP docker-compose file
    $composePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/sftp-compose.yml";
    $composeContent = createSFTPDockerCompose($site, $containerName, $volumeName, $useBindMount);
    
    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($composePath, $composeContent);
    
    // Start SFTP container
    $result = executeDockerCompose($composePath, "up -d");
    if (!$result['success']) {
        throw new Exception("Failed to start SFTP container: " . $result['output']);
    }
    
    return true;
}

function stopSFTPContainer($site) {
    $composePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/sftp-compose.yml";
    if (file_exists($composePath)) {
        executeDockerCompose($composePath, "down");
        unlink($composePath);
    }
}

function createSFTPDockerCompose($site, $containerName, $volumeName, $useBindMount = false) {
    $username = $site['sftp_username'];
    $password = $site['sftp_password'];
    $port = $site['sftp_port'];
    $puid = 33; // www-data UID
    $pgid = 33; // www-data GID
    
    // Determine volume mount
    if ($useBindMount) {
        $bindPath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/html";
        $volumeMount = "      - {$bindPath}:/config/files";
        $volumeSection = "";
    } else {
        $volumeMount = "      - {$volumeName}:/config/files";
        $volumeSection = "\nvolumes:\n  {$volumeName}:\n    external: true\n";
    }
    
    return "services:
  {$containerName}:
    image: linuxserver/openssh-server:latest
    container_name: {$containerName}
    hostname: {$containerName}
    ports:
      - \"{$port}:2222\"
    volumes:
{$volumeMount}
    environment:
      - PUID={$puid}
      - PGID={$pgid}
      - TZ=UTC
      - USER_NAME={$username}
      - USER_PASSWORD={$password}
      - PASSWORD_ACCESS=true
    restart: unless-stopped
    networks:
      - webbadeploy_webbadeploy
{$volumeSection}
networks:
  webbadeploy_webbadeploy:
    external: true";
}
?>