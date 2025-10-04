<?php
// Prevent any output before JSON
ob_start();

// Disable error display, log errors instead
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Set JSON header first
header('Content-Type: application/json');

// Set error handler to catch all errors and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $errstr . ' in ' . basename($errfile) . ' on line ' . $errline
    ]);
    exit;
});

// Set exception handler
set_exception_handler(function($exception) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $exception->getMessage()
    ]);
    exit;
});

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication for all API calls
if (!isLoggedIn()) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = initDatabase();
$action = $_GET["action"] ?? "";

switch ($action) {
    case "create_site":
        createSiteHandler($db);
        break;
        
    case "get_site":
        getSiteData($db, $_GET["id"]);
        break;

    case "update_site":
        updateSiteData($db);
        break;

    case "delete_site":
        deleteSiteById($db, $_GET["id"]);
        break;
        
    case "site_status":
        getSiteStatus($db, $_GET["id"]);
        break;
    
    case "restart_container":
        restartContainer($db, $_GET["id"]);
        break;
    
    case "start_container":
        startContainer($db, $_GET["id"]);
        break;
    
    case "stop_container":
        stopContainer($db, $_GET["id"]);
        break;
    
    case "change_password":
        changePasswordHandler($db);
        break;
    
    case "check_updates":
        checkForUpdates();
        break;
    
    case "perform_update":
        performSystemUpdate();
        break;
    
    case "get_update_info":
        getUpdateInformation();
        break;
    
    case "get_update_logs":
        getUpdateLogs();
        break;
    
    case "list_files":
        listContainerFiles($db, $_GET["id"], $_GET["path"] ?? '/var/www/html');
        break;
    
    case "download_file":
        downloadContainerFile($db, $_GET["id"], $_GET["path"]);
        break;
    
    case "delete_file":
        deleteContainerFile($db);
        break;
    
    case "create_file":
        createContainerFile($db);
        break;
    
    case "create_folder":
        createContainerFolder($db);
        break;
    
    case "upload_file":
        uploadContainerFile($db);
        break;
    
    case "read_file":
        readContainerFile($db);
        break;
    
    case "save_file":
        saveContainerFile($db);
        break;
    
    case "get_env_vars":
        getEnvironmentVariables($db, $_GET["id"]);
        break;
    
    case "save_env_vars":
        saveEnvironmentVariables($db);
        break;
    
    case "get_logs":
        getContainerLogs($db, $_GET["id"]);
        break;
    
    case "get_stats":
        getContainerStats($db, $_GET["id"]);
        break;
    
    case "enable_sftp":
        enableSFTPHandler($db, $_GET["id"]);
        break;
    
    case "disable_sftp":
        disableSFTPHandler($db, $_GET["id"]);
        break;
    
    case "regenerate_sftp_password":
        regenerateSFTPPassword($db, $_GET["id"]);
        break;
    
    case "get_dashboard_stats":
        getDashboardStats($db, $_GET["id"]);
        break;
    
    case "restart_traefik":
        restartTraefik();
        break;
    
    case "execute_docker_command":
        executeDockerCommandAPI();
        break;
    
    case "get_container_logs":
        getContainerLogs();
        break;
    
    case "export_database":
        exportDatabase($db);
        break;
    
    case "get_database_stats":
        getDatabaseStats($db);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(["error" => "Invalid action"]);
}

function createSiteHandler($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            throw new Exception("Invalid JSON data");
        }

        $data = $input;

        // Validate required fields
        if (empty($data["name"]) || empty($data["type"]) || empty($data["domain"])) {
            throw new Exception("Missing required fields");
        }

        // Generate site configuration
        $containerName = $data["type"] . "_" . preg_replace("/[^a-z0-9]/", "", strtolower($data["name"])) . "_" . time();

        // Determine final domain
        $domain = $data["domain"];
        if ($data["domain_suffix"] !== "custom") {
            $domain = $data["domain"] . $data["domain_suffix"];
        } else {
            $domain = $data["custom_domain"];
        }

        // Prepare SSL configuration
        $sslConfig = null;
        if ($data["ssl"]) {
            $sslConfig = [
                "challenge" => $data["ssl_challenge"] ?? "http",
                "provider" => $data["dns_provider"] ?? null,
                "credentials" => []
            ];
            
            // Store DNS provider credentials if using DNS challenge
            if ($sslConfig["challenge"] === "dns" && !empty($sslConfig["provider"])) {
                switch ($sslConfig["provider"]) {
                    case "cloudflare":
                        $sslConfig["credentials"] = [
                            "cf_email" => $data["cf_email"] ?? "",
                            "cf_api_key" => $data["cf_api_key"] ?? ""
                        ];
                        break;
                    case "route53":
                        $sslConfig["credentials"] = [
                            "aws_access_key" => $data["aws_access_key"] ?? "",
                            "aws_secret_key" => $data["aws_secret_key"] ?? "",
                            "aws_region" => $data["aws_region"] ?? "us-east-1"
                        ];
                        break;
                    case "digitalocean":
                        $sslConfig["credentials"] = [
                            "do_auth_token" => $data["do_auth_token"] ?? ""
                        ];
                        break;
                }
            }
        }
        
        $siteConfig = [
            "name" => $data["name"],
            "type" => $data["type"],
            "domain" => $domain,
            "ssl" => $data["ssl"] ?? false,
            "ssl_config" => $sslConfig,
            "container_name" => $containerName,
            "config" => $data,
            "db_type" => $data["wp_db_type"] ?? 'shared'
        ];

        // Create site record
        $createResult = createSite($db, $siteConfig);
        if (!$createResult) {
            throw new Exception("Failed to create site record");
        }

        // Get the site ID
        $siteId = $db->lastInsertId();
        
        // Get the site record
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Failed to retrieve created site");
        }
        
        // Ensure container_name is set in the site array (bypass database issues)
        $site['container_name'] = $containerName;
        
        // Also update the database with the container_name
        $stmt = $db->prepare("UPDATE sites SET container_name = ? WHERE id = ?");
        $stmt->execute([$containerName, $siteId]);
        
        // Verify the container_name is set - this should always pass now
        if (empty($site['container_name'])) {
            throw new Exception("IMPOSSIBLE: Container name is empty after setting it to: [$containerName]");
        }
        
        
        // Deploy the application based on type
        $deploymentSuccess = false;
        $deploymentError = null;
        
        try {
            switch ($data["type"]) {
                case "wordpress":
                    deployWordPress($site, $data);
                    break;
                case "php":
                    deployPHP($site, $data);
                    break;
                case "laravel":
                    deployLaravel($site, $data);
                    break;
            }
            $deploymentSuccess = true;
        } catch (Exception $deployError) {
            $deploymentError = $deployError->getMessage();
            // Don't throw, we'll report it but keep the site record
        }

        // Traefik will automatically discover the container via labels
        // SSL certificates are automatically requested by Traefik when the container starts
        // No manual configuration needed!

        if ($deploymentSuccess) {
            updateSiteStatus($db, $siteId, "running");
            echo json_encode([
                "success" => true,
                "message" => "Site created and deployed successfully",
                "site" => $site
            ]);
        } else {
            updateSiteStatus($db, $siteId, "stopped");
            echo json_encode([
                "success" => true,
                "warning" => true,
                "message" => "Site created but deployment failed. You can try redeploying from the dashboard.",
                "error_details" => $deploymentError,
                "site" => $site
            ]);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function deployPHP($site, $config) {
    // Ensure container_name is set
    if (empty($site['container_name'])) {
        throw new Exception("CRITICAL: deployPHP received empty container_name for site: " . $site["name"]);
    }
    
    // Create PHP application container
    $composePath = "/app/apps/php/sites/{$site['container_name']}/docker-compose.yml";
    $phpCompose = createPHPDockerCompose($site, $config);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($composePath, $phpCompose);

    // Start the container first to create the volume
    $result = executeDockerCompose($composePath, "up -d");
    if (!$result["success"]) {
        throw new Exception("Failed to start PHP application: " . $result["output"]);
    }
    
    // Add default index.php to the Docker volume
    $containerName = $site['container_name'];
    
    // Wait a moment for container to be fully ready
    sleep(2);
    
    // Check if index.php already exists
    $checkCmd = "docker exec {$containerName} test -f /var/www/html/index.php 2>/dev/null";
    exec($checkCmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        // File doesn't exist, create it using a heredoc to avoid escaping issues
        $createCmd = "docker exec {$containerName} sh -c 'cat > /var/www/html/index.php << \"PHPEOF\"
<?php http_response_code(200); ?>
<!doctype html>
<html>
<head>
    <meta charset=\"utf-8\">
    <title>Site Ready</title>
    <style>
        body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f172a;color:#e2e8f0}
        .card{background:#111827;padding:32px 40px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.4)}
        h1{margin:0 0 8px;font-size:28px}
        p{margin:0;color:#94a3b8}
    </style>
</head>
<body>
    <div class=\"card\">
        <h1>Site is Ready</h1>
        <p>PHP app created by Webbadeploy.</p>
    </div>
</body>
</html>
PHPEOF
'";
        exec($createCmd, $createOutput, $createReturn);
        
        // Set proper permissions
        exec("docker exec {$containerName} chown www-data:www-data /var/www/html/index.php");
    }
}

function createPHPDockerCompose($site, $config) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    
    // Ensure container name is not empty
    if (empty($containerName)) {
        $containerName = "php_" . preg_replace("/[^a-z0-9]/", "", strtolower($site["name"])) . "_" . time();
    }
    
    // Final safety check - if still empty, use a default
    if (empty($containerName)) {
        $containerName = "php_app_" . time();
    }
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: php:8.2-apache
    container_name: {$containerName}
    volumes:
      - {$containerName}_data:/var/www/html
    labels:
      - traefik.enable=true
      - traefik.http.routers.{$containerName}.rule=Host(`{$domain}`)
      - traefik.http.routers.{$containerName}.entrypoints=web
      - traefik.http.services.{$containerName}.loadbalancer.server.port=80";
    
    // Add SSL labels if SSL is enabled
    if ($site['ssl']) {
        $compose .= "
      - traefik.http.routers.{$containerName}-secure.rule=Host(`{$domain}`)
      - traefik.http.routers.{$containerName}-secure.entrypoints=websecure
      - traefik.http.routers.{$containerName}-secure.tls=true
      - traefik.http.routers.{$containerName}-secure.tls.certresolver=letsencrypt
      - traefik.http.routers.{$containerName}.middlewares=redirect-to-https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.permanent=true";
    }
    
    $compose .= "
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped

volumes:
  {$containerName}_data:

networks:
  webbadeploy_webbadeploy:
    external: true";
    
    return $compose;
}

function deployLaravel($site, $config) {
    // Create Laravel application container (use Apache HTTP to avoid FastCGI 502)
    $composePath = "/app/apps/laravel/sites/{$site['container_name']}/docker-compose.yml";
    $laravelCompose = createLaravelDockerCompose($site, $config);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($composePath, $laravelCompose);

    // Start the container first to create the volume
    $result = executeDockerCompose($composePath, "up -d");
    if (!$result["success"]) {
        throw new Exception("Failed to start Laravel application: " . $result["output"]);
    }
    
    // Add default index.php to the Docker volume
    $containerName = $site['container_name'];
    
    // Wait a moment for container to be fully ready
    sleep(2);
    
    // Check if index.php already exists
    $checkCmd = "docker exec {$containerName} test -f /var/www/html/index.php 2>/dev/null";
    exec($checkCmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        // File doesn't exist, create it using a heredoc
        $createCmd = "docker exec {$containerName} sh -c 'cat > /var/www/html/index.php << \"PHPEOF\"
<?php http_response_code(200); ?>
<!doctype html>
<html>
<head>
    <meta charset=\"utf-8\">
    <title>Laravel Site</title>
    <style>
        body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#111827;color:#e5e7eb}
        .card{background:#0b1020;padding:32px 40px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.4)}
        h1{margin:0 0 8px;font-size:28px}
        p{margin:0;color:#9ca3af}
    </style>
</head>
<body>
    <div class=\"card\">
        <h1>Laravel Site</h1>
        <p>Container ready. Deploy your Laravel app.</p>
    </div>
</body>
</html>
PHPEOF
'";
        exec($createCmd, $createOutput, $createReturn);
        
        // Set proper permissions
        exec("docker exec {$containerName} chown www-data:www-data /var/www/html/index.php");
    }
}

function createLaravelDockerCompose($site, $config) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: php:8.2-apache
    container_name: {$containerName}
    volumes:
      - {$containerName}_data:/var/www/html
    environment:
      - DB_HOST=webbadeploy_db
      - DB_DATABASE=laravel_{$containerName}
      - DB_USERNAME=laravel_user
      - DB_PASSWORD=laravel_pass
    labels:
      - traefik.enable=true
      - traefik.http.routers.{$containerName}.rule=Host(`{$domain}`)
      - traefik.http.routers.{$containerName}.entrypoints=web
      - traefik.http.services.{$containerName}.loadbalancer.server.port=80";
    
    // Add SSL labels if SSL is enabled
    if ($site['ssl']) {
        $compose .= "
      - traefik.http.routers.{$containerName}-secure.rule=Host(`{$domain}`)
      - traefik.http.routers.{$containerName}-secure.entrypoints=websecure
      - traefik.http.routers.{$containerName}-secure.tls=true
      - traefik.http.routers.{$containerName}-secure.tls.certresolver=letsencrypt
      - traefik.http.routers.{$containerName}.middlewares=redirect-to-https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.permanent=true";
    }
    
    $compose .= "
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped

volumes:
  {$containerName}_data:

networks:
  webbadeploy_webbadeploy:
    external: true";
    
    return $compose;
}

function createWordPressDockerCompose($site, $config) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    
    // Check database type (shared or dedicated)
    $dbType = $config['wp_db_type'] ?? 'shared';
    $useDedicatedDb = ($dbType === 'dedicated');
    
    // Generate random database password for this site
    $dbPassword = bin2hex(random_bytes(16)); // 32 character random password
    
    // Store password in site config for reference
    $site['db_password'] = $dbPassword;
    
    // Check if optimizations are enabled
    $wpOptimize = $config['wp_optimize'] ?? false;
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: wordpress:latest
    container_name: {$containerName}
    environment:";
    
    if ($useDedicatedDb) {
        // Dedicated database configuration
        $dbName = 'wordpress';
        $dbUser = 'wordpress';
        $compose .= "
      - WORDPRESS_DB_HOST={$containerName}_db
      - WORDPRESS_DB_NAME={$dbName}
      - WORDPRESS_DB_USER={$dbUser}
      - WORDPRESS_DB_PASSWORD={$dbPassword}";
    } else {
        // Shared database configuration with unique table prefix
        $tablePrefix = 'wp_' . substr(md5($site['name']), 0, 8) . '_';
        $compose .= "
      - WORDPRESS_DB_HOST=webbadeploy_db
      - WORDPRESS_DB_NAME=webbadeploy
      - WORDPRESS_DB_USER=webbadeploy
      - WORDPRESS_DB_PASSWORD=webbadeploy_pass
      - WORDPRESS_TABLE_PREFIX={$tablePrefix}";
    }
    
    // Add Redis configuration if optimizations are enabled
    if ($wpOptimize) {
        $compose .= "
      - WORDPRESS_CONFIG_EXTRA=
          define('WP_REDIS_HOST', '{$containerName}_redis');
          define('WP_REDIS_PORT', 6379);
          define('WP_CACHE', true);
          define('WP_CACHE_KEY_SALT', '{$containerName}');";
    }
    
    $compose .= "
    volumes:
      - wp_{$containerName}_data:/var/www/html";
    
    // Add PHP ini customizations for performance if optimizations enabled
    if ($wpOptimize) {
        $compose .= "
      - ./php-custom.ini:/usr/local/etc/php/conf.d/custom.ini:ro";
    }
    
    // Generate Traefik labels with SSL support
    $compose .= "
    labels:
      - traefik.enable=true
      - traefik.http.routers.{$containerName}.rule=Host(`{$domain}`)
      - traefik.http.routers.{$containerName}.entrypoints=web
      - traefik.http.services.{$containerName}.loadbalancer.server.port=80";
    
    // Add SSL labels if SSL is enabled
    if ($site['ssl']) {
        $compose .= "
      - traefik.http.routers.{$containerName}-secure.rule=Host(`{$domain}`)
      - traefik.http.routers.{$containerName}-secure.entrypoints=websecure
      - traefik.http.routers.{$containerName}-secure.tls=true
      - traefik.http.routers.{$containerName}-secure.tls.certresolver=letsencrypt
      - traefik.http.routers.{$containerName}.middlewares=redirect-to-https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.permanent=true";
    }
    
    $compose .= "
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped
";
    
    // Add dedicated database service if selected
    if ($useDedicatedDb) {
        $dbRootPassword = bin2hex(random_bytes(16));
        $compose .= "
  {$containerName}_db:
    image: mariadb:latest
    container_name: {$containerName}_db
    environment:
      - MYSQL_ROOT_PASSWORD={$dbRootPassword}
      - MYSQL_DATABASE=wordpress
      - MYSQL_USER=wordpress
      - MYSQL_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/mysql
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped
";
    }
    
    // Add Redis service if optimizations are enabled
    if ($wpOptimize) {
        $compose .= "
  {$containerName}_redis:
    image: redis:7-alpine
    container_name: {$containerName}_redis
    command: redis-server --maxmemory 256mb --maxmemory-policy allkeys-lru
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped
";
    }
    
    $compose .= "
volumes:
  wp_{$containerName}_data:";
    
    // Add database volume if using dedicated database
    if ($useDedicatedDb) {
        $compose .= "
  {$containerName}_db_data:";
    }
    
    $compose .= "

networks:
  webbadeploy_webbadeploy:
    external: true";
    
    return $compose;
}

function deployWordPress($site, $config) {
    // Ensure container_name is set
    if (empty($site['container_name'])) {
        throw new Exception("CRITICAL: deployWordPress received empty container_name for site: " . $site["name"]);
    }
    
    // Generate database credentials
    $siteId = generateSiteId($site['name']);
    $dbName = 'wp_' . $siteId;
    $dbUser = 'wp_' . substr(md5($site['name']), 0, 8);
    $dbPass = generateRandomString(16);
    
    // Create database and user in MariaDB
    // WordPress will use the shared webbadeploy database with a unique table prefix
    // This avoids the root password authentication issue
    
    // We'll use the existing webbadeploy database and user
    // WordPress supports table prefixes, so multiple sites can share one database
    $dbName = 'webbadeploy';  // Use the existing database
    $dbUser = 'webbadeploy';  // Use the existing user
    $dbPass = 'webbadeploy_pass';  // Use the existing password
    $tablePrefix = 'wp_' . substr(md5($site['name']), 0, 8) . '_';
    
    // Update the WordPress docker-compose to use these credentials
    // No need to create new database - WordPress will create tables with prefix
    
    // Create WordPress application containers
    $composePath = "/app/apps/wordpress/sites/{$site['container_name']}/docker-compose.yml";
    $wpCompose = createWordPressDockerCompose($site, $config);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($composePath, $wpCompose);
    
    // Create PHP configuration file if optimizations are enabled
    $wpOptimize = $config['wp_optimize'] ?? false;
    if ($wpOptimize) {
        $phpIniPath = $dir . '/php-custom.ini';
        $phpIniContent = "; PHP Performance Optimizations
; OpCache settings
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1

; Memory and execution limits
memory_limit=256M
max_execution_time=300
max_input_time=300
post_max_size=64M
upload_max_filesize=64M

; Performance tuning
max_input_vars=3000
realpath_cache_size=4096K
realpath_cache_ttl=600
";
        file_put_contents($phpIniPath, $phpIniContent);
    }

    $result = executeDockerCompose($composePath, "up -d");
    if (!$result["success"]) {
        throw new Exception("Failed to start WordPress application: " . $result["output"]);
    }
    
    // Install and activate Redis plugin if optimizations are enabled
    if ($wpOptimize) {
        // Wait a few seconds for WordPress to initialize
        sleep(5);
        
        // Install WP-CLI if not already available, then install Redis plugin
        $containerName = $site['container_name'];
        
        // Install Redis Object Cache plugin
        exec("docker exec $containerName wp plugin install redis-cache --activate --allow-root 2>&1", $pluginOutput, $pluginReturn);
        
        // Enable Redis object cache
        if ($pluginReturn === 0) {
            exec("docker exec $containerName wp redis enable --allow-root 2>&1");
        }
    }
}

function getSiteStatus($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $status = getDockerContainerStatus($site["container_name"]);
        
        echo json_encode([
            "success" => true,
            "status" => $status
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getSiteData($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        echo json_encode([
            "success" => true,
            "site" => $site
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function updateSiteData($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            throw new Exception("Invalid JSON data");
        }

        $siteId = $input["site_id"];
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $domainChanged = ($site['domain'] !== $input["domain"]);
        $sslChanged = ($site['ssl'] != ($input["ssl"] ? 1 : 0));
        
        // Update basic site information
        $stmt = $db->prepare("UPDATE sites SET name = ?, domain = ?, ssl = ? WHERE id = ?");
        $stmt->execute([
            $input["name"],
            $input["domain"], 
            $input["ssl"] ? 1 : 0,
            $siteId
        ]);

        $message = "Site updated successfully";
        $needsRestart = false;
        
        // If domain or SSL changed, we need to regenerate docker-compose and redeploy
        if ($domainChanged || $sslChanged) {
            $message .= ". ";
            if ($domainChanged) {
                $message .= "Domain changed. ";
            }
            if ($sslChanged) {
                $message .= "SSL " . ($input["ssl"] ? "enabled" : "disabled") . ". ";
            }
            $message .= "Regenerating container configuration...";
            $needsRestart = true;
            
            // Get updated site data
            $updatedSite = getSiteById($db, $siteId);
            
            // Regenerate docker-compose.yml with new settings
            $composePath = "/app/apps/{$updatedSite['type']}/sites/{$updatedSite['container_name']}/docker-compose.yml";
            
            if (file_exists($composePath)) {
                // Generate new compose file based on site type
                $newCompose = '';
                if ($updatedSite['type'] === 'wordpress') {
                    $newCompose = createWordPressDockerCompose($updatedSite, []);
                } elseif ($updatedSite['type'] === 'php') {
                    $newCompose = createPHPDockerCompose($updatedSite);
                } elseif ($updatedSite['type'] === 'laravel') {
                    $newCompose = createLaravelDockerCompose($updatedSite);
                }
                
                if ($newCompose) {
                    file_put_contents($composePath, $newCompose);
                    
                    // Recreate the container with new configuration
                    executeDockerCompose($composePath, "up -d --force-recreate");
                    
                    $message = "Site updated and redeployed successfully with new configuration!";
                }
            }
        }

        echo json_encode([
            "success" => true,
            "message" => $message,
            "needs_restart" => $needsRestart,
            "domain_changed" => $domainChanged,
            "ssl_changed" => $sslChanged
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function deleteSiteById($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        // Check if user wants to keep data
        $keepData = $_GET['keep_data'] ?? false;
        
        // Stop and remove containers
        $composePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/docker-compose.yml";
        if (file_exists($composePath)) {
            // Use 'down' without -v to preserve volumes, or 'down -v' to delete them
            $command = $keepData ? "down" : "down -v";
            executeDockerCompose($composePath, $command);
            unlink($composePath);
            if (is_dir(dirname($composePath))) {
                rmdir(dirname($composePath));
            }
        }

        // Delete database record
        deleteSite($db, $id);
        
        // With Traefik, no manual configuration cleanup needed
        // Traefik will automatically remove routes when containers stop

        $message = $keepData 
            ? "Site deleted successfully. Data volume preserved for backup/restore."
            : "Site and data deleted successfully.";

        echo json_encode([
            "success" => true,
            "message" => $message,
            "volume_kept" => $keepData
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function restartContainer($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $result = executeDockerCommand("restart {$site['container_name']}");
        
        if ($result['success']) {
            updateSiteStatus($db, $id, "running");
            echo json_encode([
                "success" => true,
                "message" => "Container restarted successfully"
            ]);
        } else {
            throw new Exception("Failed to restart container: " . $result['output']);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function startContainer($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $result = executeDockerCommand("start {$site['container_name']}");
        
        if ($result['success']) {
            updateSiteStatus($db, $id, "running");
            echo json_encode([
                "success" => true,
                "message" => "Container started successfully"
            ]);
        } else {
            throw new Exception("Failed to start container: " . $result['output']);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function stopContainer($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $result = executeDockerCommand("stop {$site['container_name']}");
        
        if ($result['success']) {
            updateSiteStatus($db, $id, "stopped");
            echo json_encode([
                "success" => true,
                "message" => "Container stopped successfully"
            ]);
        } else {
            throw new Exception("Failed to stop container: " . $result['output']);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getContainerLogs($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $lines = $_GET['lines'] ?? 100;
        $result = executeDockerCommand("logs --tail {$lines} {$site['container_name']}");
        
        echo json_encode([
            "success" => true,
            "logs" => $result['output']
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getContainerStats($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        // Get container uptime
        $uptimeResult = executeDockerCommand("inspect --format='{{.State.StartedAt}}' {$site['container_name']}");
        $startedAt = trim($uptimeResult['output']);
        
        // Get volume size
        $volumeName = $site['type'] === 'wordpress' ? "wp_{$site['container_name']}_data" : "{$site['container_name']}_data";
        $sizeResult = executeDockerCommand("system df -v | grep {$volumeName} || echo 'N/A'");
        
        // Calculate uptime
        $uptime = 'N/A';
        if (!empty($startedAt) && $startedAt !== 'N/A') {
            try {
                $start = new DateTime($startedAt);
                $now = new DateTime();
                $diff = $now->diff($start);
                
                if ($diff->days > 0) {
                    $uptime = $diff->days . 'd ' . $diff->h . 'h';
                } else if ($diff->h > 0) {
                    $uptime = $diff->h . 'h ' . $diff->i . 'm';
                } else {
                    $uptime = $diff->i . 'm ' . $diff->s . 's';
                }
            } catch (Exception $e) {
                $uptime = 'N/A';
            }
        }
        
        echo json_encode([
            "success" => true,
            "stats" => [
                "uptime" => $uptime,
                "volume_size" => trim($sizeResult['output']) ?: 'N/A',
                "status" => getDockerContainerStatus($site['container_name'])
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function enableSFTPHandler($db, $id) {
    try {
        $site = enableSFTP($db, $id);
        
        echo json_encode([
            "success" => true,
            "message" => "SFTP enabled successfully",
            "sftp" => [
                "username" => $site['sftp_username'],
                "password" => $site['sftp_password'],
                "port" => $site['sftp_port'],
                "host" => $_SERVER['SERVER_ADDR'] ?? 'localhost'
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function disableSFTPHandler($db, $id) {
    try {
        disableSFTP($db, $id);
        
        echo json_encode([
            "success" => true,
            "message" => "SFTP disabled successfully"
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function regenerateSFTPPassword($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Generate new password
        $newPassword = bin2hex(random_bytes(12));
        
        $stmt = $db->prepare("UPDATE sites SET sftp_password = ? WHERE id = ?");
        $stmt->execute([$newPassword, $id]);
        
        // Redeploy SFTP container with new password
        if ($site['sftp_enabled']) {
            $site['sftp_password'] = $newPassword;
            deploySFTPContainer($site);
        }
        
        echo json_encode([
            "success" => true,
            "message" => "SFTP password regenerated successfully",
            "password" => $newPassword
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function changePasswordHandler($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword)) {
            throw new Exception("Current password and new password are required");
        }
        
        if (strlen($newPassword) < 6) {
            throw new Exception("New password must be at least 6 characters long");
        }
        
        // Get current user
        $currentUser = getCurrentUser();
        if (!$currentUser) {
            throw new Exception("User not found");
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $currentUser['password_hash'])) {
            throw new Exception("Current password is incorrect");
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password in database
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $currentUser['id']]);
        
        echo json_encode([
            "success" => true,
            "message" => "Password changed successfully"
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

// ============================================
// UPDATE SYSTEM HANDLERS
// ============================================

function checkForUpdates() {
    try {
        require_once 'includes/updater.php';
        $updater = new Updater();
        $info = $updater->getUpdateInfo();
        
        // Update last check time
        setLastUpdateCheck(time());
        
        echo json_encode([
            'success' => true,
            'data' => $info
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function performSystemUpdate() {
    try {
        require_once 'includes/updater.php';
        $updater = new Updater();
        $result = $updater->performUpdate();
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getUpdateInformation() {
    try {
        require_once 'includes/updater.php';
        $updater = new Updater();
        $info = $updater->getUpdateInfo();
        $changelog = $updater->getChangelog(10);
        
        echo json_encode([
            'success' => true,
            'info' => $info,
            'changelog' => $changelog
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getUpdateLogs() {
    try {
        require_once 'includes/updater.php';
        $updater = new Updater();
        $logs = $updater->getUpdateLogs(100);
        
        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// ============================================
// FILE MANAGER HANDLERS
// ============================================

function listContainerFiles($db, $siteId, $path) {
    try {
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Check if container_name exists
        if (empty($site['container_name'])) {
            throw new Exception("Container name is empty in database for site ID: $siteId");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        if (empty($path)) $path = '/var/www/html';
        
        // List files in container using a more reliable format
        $containerName = $site['container_name'];
        
        // Verify container exists using docker inspect (most reliable)
        exec("docker inspect --format='{{.State.Status}}' " . escapeshellarg($containerName) . " 2>&1", $inspectOutput, $inspectCode);
        
        if ($inspectCode !== 0) {
            // Container doesn't exist - show available containers for debugging
            exec("docker ps -a --format '{{.Names}}' 2>&1", $allContainers);
            $containerList = implode(", ", $allContainers);
            throw new Exception("Container '$containerName' not found. Available containers: " . ($containerList ?: "none"));
        }
        
        $containerStatus = trim($inspectOutput[0] ?? '');
        if ($containerStatus !== 'running') {
            throw new Exception("Container '$containerName' is not running (status: $containerStatus). Please start the container first.");
        }
        
        $output = [];
        
        // Use ls with full path - simpler and more reliable
        $cmd = "docker exec $containerName ls -1A " . escapeshellarg($path) . " 2>&1";
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            
            // Check for specific error types
            if (strpos($errorMsg, 'No such file or directory') !== false) {
                throw new Exception("Directory not found: $path");
            } elseif (strpos($errorMsg, 'is not running') !== false || strpos($errorMsg, 'not found') !== false) {
                throw new Exception("Container '$containerName' is not running or not found");
            } else {
                throw new Exception("Failed to list files: " . $errorMsg);
            }
        }
        
        $files = [];
        foreach ($output as $filename) {
            $filename = trim($filename);
            if (empty($filename) || $filename === '.' || $filename === '..') continue;
            
            // Get file details
            $fullPath = rtrim($path, '/') . '/' . $filename;
            $statCmd = "docker exec $containerName stat -c '%F|%s|%y' " . escapeshellarg($fullPath) . " 2>&1";
            $statOutput = [];
            exec($statCmd, $statOutput, $statReturn);
            
            if ($statReturn === 0 && !empty($statOutput[0])) {
                $parts = explode('|', $statOutput[0]);
                $fileType = $parts[0] ?? '';
                $size = (int)($parts[1] ?? 0);
                $modified = isset($parts[2]) ? date('M d H:i', strtotime($parts[2])) : '';
                
                $isDir = (strpos($fileType, 'directory') !== false);
                
                $files[] = [
                    'name' => $filename,
                    'type' => $isDir ? 'directory' : 'file',
                    'size' => $size,
                    'modified' => $modified,
                    'path' => $fullPath
                ];
            }
        }
        
        // Sort: directories first, then files
        usort($files, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        
        echo json_encode([
            'success' => true,
            'files' => $files,
            'path' => $path
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function downloadContainerFile($db, $siteId, $path) {
    try {
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        
        $containerName = $site['container_name'];
        $tempFile = tempnam(sys_get_temp_dir(), 'download_');
        
        // Copy file from container
        exec("docker cp $containerName:$path $tempFile 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to download file");
        }
        
        // Send file to browser
        $filename = basename($path);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        unlink($tempFile);
        exit;
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function deleteContainerFile($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        
        if (!$siteId || !$path) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        
        // Don't allow deleting critical files
        $criticalFiles = ['/var/www/html/index.php', '/var/www/html'];
        if (in_array($path, $criticalFiles)) {
            throw new Exception("Cannot delete critical file");
        }
        
        $containerName = $site['container_name'];
        exec("docker exec $containerName rm -rf " . escapeshellarg($path) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to delete file: " . implode("\n", $output));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function createContainerFile($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        $filename = $input['filename'] ?? null;
        $content = $input['content'] ?? '';
        
        if (!$siteId || !$path || !$filename) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path and filename
        $path = str_replace(['..', '~'], '', $path);
        $filename = basename($filename); // Remove any path components
        $fullPath = rtrim($path, '/') . '/' . $filename;
        
        $containerName = $site['container_name'];
        
        // Create temp file with content
        $tempFile = tempnam(sys_get_temp_dir(), 'newfile_');
        file_put_contents($tempFile, $content);
        
        // Copy to container
        exec("docker cp $tempFile $containerName:$fullPath 2>&1", $output, $returnCode);
        unlink($tempFile);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to create file: " . implode("\n", $output));
        }
        
        // Set proper permissions
        exec("docker exec $containerName chmod 644 " . escapeshellarg($fullPath) . " 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'File created successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function createContainerFolder($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        $foldername = $input['foldername'] ?? null;
        
        if (!$siteId || !$path || !$foldername) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path and foldername
        $path = str_replace(['..', '~'], '', $path);
        $foldername = basename($foldername); // Remove any path components
        $fullPath = rtrim($path, '/') . '/' . $foldername;
        
        $containerName = $site['container_name'];
        
        // Create directory
        exec("docker exec $containerName mkdir -p " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to create folder: " . implode("\n", $output));
        }
        
        // Set proper permissions
        exec("docker exec $containerName chmod 755 " . escapeshellarg($fullPath) . " 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'Folder created successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function uploadContainerFile($db) {
    try {
        $siteId = $_POST['id'] ?? null;
        $path = $_POST['path'] ?? null;
        
        if (!$siteId || !$path) {
            throw new Exception("Missing parameters");
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("No file uploaded or upload error");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        $filename = basename($_FILES['file']['name']);
        $fullPath = rtrim($path, '/') . '/' . $filename;
        
        $containerName = $site['container_name'];
        $tempFile = $_FILES['file']['tmp_name'];
        
        // Copy to container
        exec("docker cp $tempFile $containerName:$fullPath 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to upload file: " . implode("\n", $output));
        }
        
        // Set proper permissions
        exec("docker exec $containerName chmod 644 " . escapeshellarg($fullPath) . " 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function readContainerFile($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        
        if (!$siteId || !$path) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        
        $containerName = $site['container_name'];
        
        // Read file from container
        $output = [];
        exec("docker exec $containerName cat " . escapeshellarg($path) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to read file: " . implode("\n", $output));
        }
        
        $content = implode("\n", $output);
        
        echo json_encode([
            'success' => true,
            'content' => $content,
            'filename' => basename($path)
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function saveContainerFile($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        $content = $input['content'] ?? '';
        
        if (!$siteId || !$path) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        
        $containerName = $site['container_name'];
        
        // Create temp file with content
        $tempFile = tempnam(sys_get_temp_dir(), 'editfile_');
        file_put_contents($tempFile, $content);
        
        // Copy to container
        exec("docker cp $tempFile $containerName:$path 2>&1", $output, $returnCode);
        unlink($tempFile);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to save file: " . implode("\n", $output));
        }
        
        // Set proper permissions
        exec("docker exec $containerName chmod 644 " . escapeshellarg($path) . " 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'File saved successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// ============================================
// ENVIRONMENT VARIABLES HANDLERS
// ============================================

function getEnvironmentVariables($db, $siteId) {
    try {
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        
        // Try both container path and host path
        $possiblePaths = [
            "/app/apps/{$site['type']}/sites/$containerName/docker-compose.yml",
            "/opt/webbadeploy/apps/{$site['type']}/sites/$containerName/docker-compose.yml"
        ];
        
        $composeFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $composeFile = $path;
                break;
            }
        }
        
        if (!$composeFile) {
            throw new Exception("Docker compose file not found in any location");
        }
        
        $content = file_get_contents($composeFile);
        
        // Parse environment variables from YAML
        $envVars = [];
        
        // Match environment section - handle both proper and malformed YAML
        // This regex matches "environment:" followed by lines starting with "      -"
        if (preg_match('/environment:\s*\n((?:\s+-[^\n]+\n?)+)/m', $content, $matches)) {
            $envBlock = $matches[1];
            // Split by newlines and process each line
            $lines = explode("\n", $envBlock);
            
            foreach ($lines as $line) {
                // Match lines like "      - KEY=value" or "- KEY=value"
                if (preg_match('/^\s*-\s*([^=]+)=(.*)$/', trim($line), $envMatch)) {
                    $key = trim($envMatch[1]);
                    $value = trim($envMatch[2]);
                    
                    if (!empty($key)) {
                        $envVars[] = [
                            'key' => $key,
                            'value' => $value
                        ];
                    }
                }
            }
        }
        
        // Log what we loaded
        error_log("Loaded " . count($envVars) . " environment variables from $composeFile");
        
        echo json_encode([
            'success' => true,
            'env_vars' => $envVars
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function saveEnvironmentVariables($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $envVars = $input['env_vars'] ?? [];
        
        if (!$siteId) {
            throw new Exception("Missing site ID");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        
        // Try both container path and host path
        $possiblePaths = [
            "/app/apps/{$site['type']}/sites/$containerName/docker-compose.yml",
            "/opt/webbadeploy/apps/{$site['type']}/sites/$containerName/docker-compose.yml"
        ];
        
        $composeFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $composeFile = $path;
                break;
            }
        }
        
        if (!$composeFile) {
            throw new Exception("Docker compose file not found");
        }
        
        // Read current docker-compose.yml
        $content = file_get_contents($composeFile);
        
        // Create backup before modifying
        $backupFile = $composeFile . '.backup.' . time();
        file_put_contents($backupFile, $content);
        error_log("Created backup: $backupFile");
        
        // Build new environment section with proper indentation
        $envLines = [];
        foreach ($envVars as $env) {
            $key = trim($env['key'] ?? '');
            $value = trim($env['value'] ?? '');
            
            // Skip empty keys
            if (empty($key)) {
                continue;
            }
            
            // Sanitize key - remove spaces and special chars that break YAML
            $key = preg_replace('/[^A-Z0-9_]/', '_', strtoupper($key));
            
            // Escape value if it contains special characters
            // Quote the value if it contains spaces, colons, or special chars
            if (preg_match('/[\s:{}[\]&*#?|<>=!%@`]/', $value)) {
                // Escape quotes in value
                $value = str_replace('"', '\\"', $value);
                $value = '"' . $value . '"';
            }
            
            $envLines[] = "      - $key=$value";
        }
        
        if (empty($envLines)) {
            throw new Exception("No valid environment variables to save. Received: " . json_encode($envVars));
        }
        
        // Log what we're about to write
        error_log("Saving " . count($envLines) . " environment variables for site $siteId");
        
        $newEnvSection = "    environment:\n" . implode("\n", $envLines) . "\n";
        
        // Replace environment section - handle both proper and malformed YAML
        // Pattern 1: Proper formatting with newline before environment
        $pattern1 = '/    environment:\s*\n(?:      - [^\n]+\n?)+/';
        // Pattern 2: No newline before environment (malformed but common)
        $pattern2 = '/environment:\s*\n(?:      - [^\n]+\n?)+/';
        
        $replaced = false;
        
        if (preg_match($pattern1, $content)) {
            $content = preg_replace($pattern1, $newEnvSection, $content, 1);
            $replaced = true;
        } elseif (preg_match($pattern2, $content)) {
            // For malformed YAML, add proper spacing
            $content = preg_replace($pattern2, "    " . $newEnvSection, $content, 1);
            $replaced = true;
        }
        
        if (!$replaced) {
            throw new Exception("Could not find environment section in docker-compose.yml. Content: " . substr($content, 0, 500));
        }
        
        // Write back to file
        file_put_contents($composeFile, $content);
        
        // Restart container using the directory where the compose file is
        $composeDir = dirname($composeFile);
        exec("cd $composeDir && docker-compose restart 2>&1", $output, $returnCode);
        
        // Check if container actually restarted (ignore YAML warnings)
        $outputStr = implode("\n", $output);
        $hasError = $returnCode !== 0 && !preg_match('/Started|Restarted/', $outputStr);
        
        if ($hasError) {
            // Check if container is actually running despite the error
            exec("docker ps -f name=$containerName --format '{{.Status}}'", $statusOutput);
            $isRunning = !empty($statusOutput) && strpos($statusOutput[0], 'Up') !== false;
            
            if (!$isRunning) {
                throw new Exception("Failed to restart container: " . $outputStr);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Environment variables saved and container restarted',
            'warning' => $returnCode !== 0 ? 'Container restarted but with warnings' : null
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getDashboardStats($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        // Check if container is running
        $status = getDockerContainerStatus($site['container_name']);
        if ($status !== 'running') {
            echo json_encode([
                "success" => true,
                "stats" => [
                    "cpu" => "0%",
                    "memory" => "0 MB",
                    "cpu_percent" => 0,
                    "mem_percent" => 0,
                    "status" => $status
                ]
            ]);
            return;
        }

        // Cache stats for 5 seconds to avoid hammering docker stats
        $cacheFile = "/tmp/stats_cache_{$site['id']}";
        $cacheTime = 5; // seconds
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
            $cachedStats = json_decode(file_get_contents($cacheFile), true);
            echo json_encode([
                "success" => true,
                "stats" => $cachedStats,
                "cached" => true
            ]);
            return;
        }

        // Get container stats
        $stats = ['cpu' => '0%', 'memory' => '0 MB', 'cpu_percent' => 0, 'mem_percent' => 0, 'status' => 'running'];
        
        exec("docker stats --no-stream --format '{{.CPUPerc}}|{{.MemUsage}}' {$site['container_name']} 2>&1", $output);
        if (!empty($output[0]) && strpos($output[0], 'Error') === false) {
            $parts = explode('|', $output[0]);
            $stats['cpu'] = $parts[0] ?? '0%';
            $stats['memory'] = $parts[1] ?? '0 MB';
            
            // Extract percentages for graphs
            $stats['cpu_percent'] = (float)str_replace('%', '', $stats['cpu']);
            
            // Parse memory usage (e.g., "45.5MiB / 1.944GiB")
            if (isset($parts[1])) {
                preg_match('/(\d+\.?\d*)\w+\s*\/\s*(\d+\.?\d*)(\w+)/', $parts[1], $memMatch);
                if (count($memMatch) >= 4) {
                    $used = (float)$memMatch[1];
                    $total = (float)$memMatch[2];
                    
                    // Convert to same unit if needed
                    if (strpos($parts[1], 'MiB') !== false && strpos($parts[1], 'GiB') !== false) {
                        $used = $used; // Keep in MiB
                        $total = $total * 1024; // Convert GiB to MiB
                    }
                    
                    $stats['mem_percent'] = $total > 0 ? ($used / $total) * 100 : 0;
                }
            }
        }

        // Cache the results
        file_put_contents($cacheFile, json_encode($stats));

        echo json_encode([
            "success" => true,
            "stats" => $stats
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function restartTraefik() {
    try {
        exec("docker restart webbadeploy_traefik 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode([
                "success" => true,
                "message" => "Traefik restarted successfully"
            ]);
        } else {
            throw new Exception("Failed to restart Traefik: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function executeDockerCommandAPI() {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['command'])) {
            throw new Exception("Command is required");
        }
        
        $command = $input['command'];
        
        // Security: Only allow specific docker commands
        $allowedCommands = ['restart', 'start', 'stop', 'logs'];
        $commandParts = explode(' ', $command);
        
        if (!in_array($commandParts[0], $allowedCommands)) {
            throw new Exception("Command not allowed");
        }
        
        exec("docker " . escapeshellcmd($command) . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode([
                "success" => true,
                "message" => "Command executed successfully",
                "output" => implode("\n", $output)
            ]);
        } else {
            throw new Exception("Command failed: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getContainerLogs() {
    try {
        $container = $_GET['container'] ?? '';
        $lines = $_GET['lines'] ?? 100;
        
        if (empty($container)) {
            throw new Exception("Container name is required");
        }
        
        exec("docker logs --tail " . intval($lines) . " " . escapeshellarg($container) . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode([
                "success" => true,
                "logs" => implode("\n", $output)
            ]);
        } else {
            throw new Exception("Failed to get logs: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function exportDatabase($db) {
    try {
        $siteId = $_GET['site_id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if (($site['db_type'] ?? 'shared') !== 'dedicated') {
            throw new Exception("This site uses a shared database. Export not available.");
        }
        
        $containerName = escapeshellarg($site['container_name'] . '_db');
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$site['name']}_{$timestamp}.sql";
        $backupPath = "/app/data/backups/{$filename}";
        
        // Create backups directory if it doesn't exist
        if (!is_dir('/app/data/backups')) {
            mkdir('/app/data/backups', 0755, true);
        }
        
        // Export database using environment variable for password
        $password = $site['db_password'] ?? '';
        $command = "docker exec {$containerName} sh -c 'MYSQL_PWD=" . escapeshellarg($password) . " mysqldump -u wordpress wordpress' > {$backupPath} 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupPath)) {
            echo json_encode([
                "success" => true,
                "message" => "Database exported successfully",
                "file" => $filename,
                "download_url" => "/download.php?file=" . urlencode($filename)
            ]);
        } else {
            throw new Exception("Failed to export database: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getDatabaseStats($db) {
    try {
        $siteId = $_GET['site_id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if (($site['db_type'] ?? 'shared') !== 'dedicated') {
            throw new Exception("This site uses a shared database. Stats not available.");
        }
        
        $containerName = escapeshellarg($site['container_name'] . '_db');
        
        // Get database size and table count
        $sqlCommand = "SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB',
            COUNT(*) AS 'Tables'
            FROM information_schema.TABLES 
            WHERE table_schema = 'wordpress'";
        
        $password = $site['db_password'] ?? '';
        $command = "docker exec {$containerName} sh -c 'MYSQL_PWD=" . escapeshellarg($password) . " mysql -u wordpress -e " . escapeshellarg($sqlCommand) . "' 2>&1";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $stats = "Database Statistics:\n\n";
            $stats .= implode("\n", $output);
            
            echo json_encode([
                "success" => true,
                "stats" => $stats
            ]);
        } else {
            throw new Exception("Failed to get database stats: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}
?>
