<?php
// CRITICAL: Must be first - prevent any output before JSON
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Start output buffering immediately
ob_start();

// Set JSON header first
header('Content-Type: application/json');

// Set error handler to catch all errors and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $errstr . ' in ' . $errfile . ' on line ' . $errline
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

try {
    require_once 'includes/functions.php';
    require_once 'includes/auth.php';
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load dependencies: ' . $e->getMessage()]);
    exit;
}

// Require authentication for all API calls
if (!isLoggedIn()) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $db = initDatabase();
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database initialization failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET["action"] ?? "";

// Wrap entire switch in try-catch to ensure JSON responses
try {
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
        getSiteContainerLogs($db, $_GET["id"]);
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
    
    case "restart_webgui":
        restartWebGui();
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
    
    case "get_site_containers":
        getSiteContainers($db, $_GET["id"]);
        break;
    
    case "generate_db_token":
        generateDbToken($db);
        break;
    
    // User Management API endpoints
    case "create_user":
        createUserHandler();
        break;
    
    case "get_user":
        getUserHandler();
        break;
    
    case "update_user":
        updateUserHandler();
        break;
    
    case "delete_user":
        deleteUserHandler();
        break;
    
    case "grant_site_permission":
        grantSitePermissionHandler();
        break;
    
    case "revoke_site_permission":
        revokeSitePermissionHandler();
        break;
    
    case "get_user_permissions":
        getUserPermissionsHandler($db);
        break;
    
    // 2FA API endpoints
    case "setup_2fa":
        setup2FAHandler();
        break;
    
    case "enable_2fa":
        enable2FAHandler();
        break;
    
    case "disable_2fa":
        disable2FAHandler();
        break;
    
    // Redis Management
    case "enable_redis":
        enableRedisHandler($db);
        break;
    
    case "disable_redis":
        disableRedisHandler($db);
        break;
    
    case "flush_redis":
        flushRedisHandler($db);
        break;
    
    case "restart_redis":
        restartRedisHandler($db);
        break;
    
    case "update_setting":
        updateSettingHandler($db);
        break;
    
    case "verify_2fa_setup":
        verify2FASetupHandler();
        break;
    
    case "change_php_version":
        changePHPVersionHandler($db);
        break;
        
    default:
        ob_clean();
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid action: " . $action]);
    }
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

function createSiteHandler($db) {
    try {
        // Check if user has permission to create sites
        if (!canCreateSites($_SESSION['user_id'])) {
            http_response_code(403);
            throw new Exception("You don't have permission to create sites. Contact an administrator.");
        }
        
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
        
        // Determine database type based on site type
        $dbType = 'shared';
        if ($data["type"] === 'wordpress') {
            $dbType = $data["wp_db_type"] ?? 'shared';
        } elseif ($data["type"] === 'php') {
            $dbType = $data["php_db_type"] ?? 'none';
        } elseif ($data["type"] === 'laravel') {
            $dbType = $data["laravel_db_type"] ?? 'mysql';
        }
        
        $siteConfig = [
            "name" => $data["name"],
            "type" => $data["type"],
            "domain" => $domain,
            "ssl" => $data["ssl"] ?? false,
            "ssl_config" => $sslConfig,
            "container_name" => $containerName,
            "config" => $data,
            "db_type" => $dbType
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
                    deployWordPress($db, $site, $data);
                    break;
                case "php":
                    deployPHP($site, $data, $db);
                    break;
                case "laravel":
                    deployLaravel($site, $data, $db);
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

function deployPHP($site, $config, $db) {
    // Ensure container_name is set
    if (empty($site['container_name'])) {
        throw new Exception("CRITICAL: deployPHP received empty container_name for site: " . $site["name"]);
    }
    
    // Create PHP application container
    $composePath = "/app/apps/php/sites/{$site['container_name']}/docker-compose.yml";
    $generatedPassword = null;
    $phpCompose = createPHPDockerCompose($site, $config, $generatedPassword);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($composePath, $phpCompose);
    
    // Save to database
    saveComposeConfig($db, $phpCompose, $site['owner_id'] ?? 1, $site['id']);

    // Save database password if generated
    if ($generatedPassword) {
        $stmt = $db->prepare("UPDATE sites SET db_password = ? WHERE id = ?");
        $stmt->execute([$generatedPassword, $site['id']]);
    }

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
        $siteName = addslashes($site['name']);
        $createCmd = "docker exec {$containerName} sh -c 'cat > /var/www/html/index.php << \"PHPEOF\"
<?php http_response_code(200); ?>
<!doctype html>
<html>
<head>
    <meta charset=\"utf-8\">
    <title>{$siteName} - Ready</title>
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
    <div class=\"container\">
        <div class=\"card\">
            <h1>🚀 {$siteName}</h1>
            <div class=\"subtitle\">Your PHP application is ready!</div>
            
            <div class=\"info\">
                <div class=\"info-row\">
                    <span class=\"label\">PHP Version:</span>
                    <span class=\"value\"><?php echo phpversion(); ?></span>
                </div>
                <div class=\"info-row\">
                    <span class=\"label\">Server:</span>
                    <span class=\"value\"><?php echo \\\$_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></span>
                </div>
                <div class=\"info-row\">
                    <span class=\"label\">Document Root:</span>
                    <span class=\"value\">/var/www/html</span>
                </div>
                <div class=\"info-row\">
                    <span class=\"label\">Memory Limit:</span>
                    <span class=\"value\"><?php echo ini_get('memory_limit'); ?></span>
                </div>
            </div>
            
            <div style=\"margin-top:20px;opacity:.8;font-size:14px\">
                <strong>Next steps:</strong><br>
                • Upload your PHP files via SFTP<br>
                • Or use the file manager in Webbadeploy<br>
                • Replace this index.php with your application
            </div>
            
            <div class=\"badge\">Powered by Webbadeploy</div>
        </div>
    </div>
</body>
</html>
PHPEOF
'";
        exec($createCmd, $createOutput, $createReturn);
        
        // Set proper permissions
        exec("docker exec {$containerName} chown www-data:www-data /var/www/html/index.php");
    }
    
    // Create Redis container if requested
    if (isset($config['php_redis']) && $config['php_redis']) {
        createRedisContainer($site, $db);
    }
}

function createPHPDockerCompose($site, $config, &$generatedPassword = null) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    $phpVersion = $site["php_version"] ?? '8.3';
    
    // Ensure container name is not empty
    if (empty($containerName)) {
        $containerName = "php_" . preg_replace("/[^a-z0-9]/", "", strtolower($site["name"])) . "_" . time();
    }
    
    // Final safety check - if still empty, use a default
    if (empty($containerName)) {
        $containerName = "php_app_" . time();
    }
    
    // Check database type
    $dbType = $config['php_db_type'] ?? 'none';
    $useDedicatedDb = in_array($dbType, ['mysql', 'postgresql']);
    
    // Generate random database password
    $dbPassword = bin2hex(random_bytes(16));
    $generatedPassword = $dbPassword;
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: php:{$phpVersion}-apache
    container_name: {$containerName}
    volumes:
      - {$containerName}_data:/var/www/html
    environment:";
    
    if ($useDedicatedDb) {
        // Dedicated database
        $compose .= "
      - DB_HOST={$containerName}_db
      - DB_DATABASE=appdb
      - DB_USERNAME=appuser
      - DB_PASSWORD={$dbPassword}";
    } else {
        // No database
        $compose .= "
      - DB_HOST=
      - DB_DATABASE=
      - DB_USERNAME=
      - DB_PASSWORD=";
    }
    
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
    
    // Add database service if needed
    if ($useDedicatedDb) {
        if ($dbType === 'mysql') {
            $compose .= "
  {$containerName}_db:
    image: mariadb:latest
    container_name: {$containerName}_db
    environment:
      - MYSQL_ROOT_PASSWORD={$dbPassword}
      - MYSQL_DATABASE=appdb
      - MYSQL_USER=appuser
      - MYSQL_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/mysql
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped
";
        } elseif ($dbType === 'postgresql') {
            $compose .= "
  {$containerName}_db:
    image: postgres:15
    container_name: {$containerName}_db
    environment:
      - POSTGRES_DB=appdb
      - POSTGRES_USER=appuser
      - POSTGRES_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/postgresql/data
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped
";
        }
    }
    
    $compose .= "
volumes:
  {$containerName}_data:";
    
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

function deployLaravel($site, $config, $db) {
    // Create Laravel application container (use Apache HTTP to avoid FastCGI 502)
    $composePath = "/app/apps/laravel/sites/{$site['container_name']}/docker-compose.yml";
    $generatedPassword = null;
    $laravelCompose = createLaravelDockerCompose($site, $config, $generatedPassword);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($composePath, $laravelCompose);
    
    // Save to database
    saveComposeConfig($db, $laravelCompose, $site['owner_id'] ?? 1, $site['id']);

    // Save database password if generated
    if ($generatedPassword) {
        $stmt = $db->prepare("UPDATE sites SET db_password = ? WHERE id = ?");
        $stmt->execute([$generatedPassword, $site['id']]);
    }

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
        $siteName = addslashes($site['name']);
        $createCmd = "docker exec {$containerName} sh -c 'cat > /var/www/html/index.php << \"PHPEOF\"
<?php http_response_code(200); ?>
<!doctype html>
<html>
<head>
    <meta charset=\"utf-8\">
    <title>{$siteName} - Laravel</title>
    <style>
        body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:linear-gradient(135deg,#ff2d20 0%,#ff6b6b 100%);color:#fff}
        .container{max-width:650px;padding:40px}
        .card{background:rgba(255,255,255,0.1);backdrop-filter:blur(10px);padding:40px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.2)}
        h1{margin:0 0 10px;font-size:32px;font-weight:700;display:flex;align-items:center;gap:12px}
        .subtitle{font-size:18px;opacity:.9;margin-bottom:30px}
        .info{background:rgba(0,0,0,.2);padding:20px;border-radius:8px;margin-top:20px}
        .info-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.1)}
        .info-row:last-child{border:0}
        .label{opacity:.8}
        .value{font-weight:600;font-family:monospace}
        .badge{display:inline-block;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:14px;margin-top:10px}
        .steps{background:rgba(0,0,0,.15);padding:15px 20px;border-radius:8px;margin-top:20px;font-size:14px;line-height:1.8}
        .steps code{background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;font-size:13px}
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"card\">
            <h1>
                <svg width=\"40\" height=\"40\" viewBox=\"0 0 50 52\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M49.626 11.564a.809.809 0 0 1 .028.209v10.972a.8.8 0 0 1-.402.694l-9.209 5.302V39.25c0 .286-.152.55-.4.694L20.42 51.01c-.044.025-.092.041-.14.058-.018.006-.035.017-.054.022a.805.805 0 0 1-.41 0c-.022-.006-.042-.018-.063-.026-.044-.016-.09-.03-.132-.054L.402 39.944A.801.801 0 0 1 0 39.25V6.334c0-.072.01-.142.028-.21.006-.023.02-.044.028-.067.015-.042.029-.085.051-.124.015-.026.037-.047.055-.071.023-.032.044-.065.071-.093.023-.023.053-.04.079-.06.029-.024.055-.05.088-.069h.001l9.61-5.533a.802.802 0 0 1 .8 0l9.61 5.533h.002c.032.02.059.045.088.068.026.02.055.038.078.06.028.029.048.062.072.094.017.024.04.045.054.071.023.04.036.082.052.124.008.023.022.044.028.068a.809.809 0 0 1 .028.209v20.559l8.008-4.611v-10.51c0-.07.01-.141.028-.208.007-.024.02-.045.028-.068.016-.042.03-.085.052-.124.015-.026.037-.047.054-.071.024-.032.044-.065.072-.093.023-.023.052-.04.078-.06.03-.024.056-.05.088-.069h.001l9.611-5.533a.801.801 0 0 1 .8 0l9.61 5.533c.034.02.06.045.09.068.025.02.054.038.077.06.028.029.048.062.072.094.018.024.04.045.054.071.023.039.036.082.052.124.009.023.022.044.028.068zm-1.574 10.718v-9.124l-3.363 1.936-4.646 2.675v9.124l8.01-4.611zm-9.61 16.505v-9.13l-4.57 2.61-13.05 7.448v9.216l17.62-10.144zM1.602 7.719v31.068L19.22 48.93v-9.214l-9.204-5.209-.003-.002-.004-.002c-.031-.018-.057-.044-.086-.066-.025-.02-.054-.036-.076-.058l-.002-.003c-.026-.025-.044-.056-.066-.084-.02-.027-.044-.05-.06-.078l-.001-.003c-.018-.03-.029-.066-.042-.1-.013-.03-.03-.058-.038-.09v-.001c-.01-.038-.012-.078-.016-.117-.004-.03-.012-.06-.012-.09v-.002-21.481L4.965 9.654 1.602 7.72zm8.81-5.994L2.405 6.334l8.005 4.609 8.006-4.61-8.006-4.608zm4.164 28.764l4.645-2.674V7.719l-3.363 1.936-4.646 2.675v20.096l3.364-1.937zM39.243 7.164l-8.006 4.609 8.006 4.609 8.005-4.61-8.005-4.608zm-.801 10.605l-4.646-2.675-3.363-1.936v9.124l4.645 2.674 3.364 1.937v-9.124zM20.02 38.33l11.743-6.704 5.87-3.35-8-4.606-9.211 5.303-8.395 4.833 7.993 4.524z\" fill=\"#FFF\" fill-rule=\"evenodd\"/></svg>
                {$siteName}
            </h1>
            <div class=\"subtitle\">Laravel application container is ready!</div>
            
            <div class=\"info\">
                <div class=\"info-row\">
                    <span class=\"label\">PHP Version:</span>
                    <span class=\"value\"><?php echo phpversion(); ?></span>
                </div>
                <div class=\"info-row\">
                    <span class=\"label\">Laravel:</span>
                    <span class=\"value\">Ready to install</span>
                </div>
                <div class=\"info-row\">
                    <span class=\"label\">Server:</span>
                    <span class=\"value\"><?php echo \\\$_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></span>
                </div>
                <div class=\"info-row\">
                    <span class=\"label\">Document Root:</span>
                    <span class=\"value\">/var/www/html</span>
                </div>
                <div class=\"info-row\">
                    <span class=\"label\">Database:</span>
                    <span class=\"value\"><?php echo !empty(\\\$_ENV['DB_HOST']) ? 'Configured' : 'Not configured'; ?></span>
                </div>
            </div>
            
            <div class=\"steps\">
                <strong>🚀 Deploy Laravel:</strong><br>
                1. Upload your Laravel project via SFTP or File Manager<br>
                2. Point document root to <code>public</code> folder<br>
                3. Run <code>composer install</code> in the container<br>
                4. Configure <code>.env</code> file with database credentials<br>
                5. Run <code>php artisan key:generate</code>
            </div>
            
            <div class=\"badge\">Powered by Webbadeploy</div>
        </div>
    </div>
</body>
</html>
PHPEOF
'";
        exec($createCmd, $createOutput, $createReturn);
        
        // Set proper permissions
        exec("docker exec {$containerName} chown www-data:www-data /var/www/html/index.php");
    }
    
    // Create Redis container if requested
    if (isset($config['laravel_redis']) && $config['laravel_redis']) {
        createRedisContainer($site, $db);
    }
}

function createLaravelDockerCompose($site, $config, &$generatedPassword = null) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    $phpVersion = $site["php_version"] ?? '8.3';
    
    // Check database type
    $dbType = $config['laravel_db_type'] ?? 'mysql';
    $useDedicatedDb = in_array($dbType, ['mysql', 'postgresql']);
    
    // Generate random database password
    $dbPassword = bin2hex(random_bytes(16));
    $generatedPassword = $dbPassword;
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: php:{$phpVersion}-apache
    container_name: {$containerName}
    volumes:
      - {$containerName}_data:/var/www/html
    environment:";
    
    if ($useDedicatedDb) {
        // Dedicated database
        $compose .= "
      - DB_HOST={$containerName}_db
      - DB_DATABASE=appdb
      - DB_USERNAME=appuser
      - DB_PASSWORD={$dbPassword}";
    } else {
        // No database
        $compose .= "
      - DB_HOST=
      - DB_DATABASE=
      - DB_USERNAME=
      - DB_PASSWORD=";
    }
    
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
    
    // Add database service if needed
    if ($useDedicatedDb) {
        if ($dbType === 'mysql') {
            $compose .= "
  {$containerName}_db:
    image: mariadb:latest
    container_name: {$containerName}_db
    environment:
      - MYSQL_ROOT_PASSWORD={$dbPassword}
      - MYSQL_DATABASE=appdb
      - MYSQL_USER=appuser
      - MYSQL_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/mysql
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped
";
        } elseif ($dbType === 'postgresql') {
            $compose .= "
  {$containerName}_db:
    image: postgres:15
    container_name: {$containerName}_db
    environment:
      - POSTGRES_DB=appdb
      - POSTGRES_USER=appuser
      - POSTGRES_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/postgresql/data
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped
";
        }
    }
    
    $compose .= "
volumes:
  {$containerName}_data:";
    
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

function createWordPressDockerCompose($site, $config, &$generatedPassword = null) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    $phpVersion = $site["php_version"] ?? '8.3';
    
    // Check database type (shared or dedicated)
    $dbType = $config['wp_db_type'] ?? 'shared';
    $useDedicatedDb = ($dbType === 'dedicated');
    
    // Generate random database password for this site
    $dbPassword = bin2hex(random_bytes(16)); // 32 character random password
    
    // Return the password via reference parameter
    $generatedPassword = $dbPassword;
    
    // Check if optimizations are enabled
    $wpOptimize = $config['wp_optimize'] ?? false;
    
    // WordPress image with specific PHP version
    $wpImage = "wordpress:php{$phpVersion}-apache";
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: {$wpImage}
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

function deployWordPress($db, $site, $config) {
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
    
    // Generate the docker-compose and get the generated password
    $dbPassword = null;
    $wpCompose = createWordPressDockerCompose($site, $config, $dbPassword);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($composePath, $wpCompose);
    
    // Save to database
    saveComposeConfig($db, $wpCompose, $site['owner_id'] ?? 1, $site['id']);
    
    // Save the database password to the database if it was generated
    if ($dbPassword && ($config['wp_db_type'] ?? 'shared') === 'dedicated') {
        $stmt = $db->prepare("UPDATE sites SET db_password = ? WHERE container_name = ?");
        $stmt->execute([$dbPassword, $site['container_name']]);
    }
    
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
                    $newCompose = createPHPDockerCompose($updatedSite, []);
                } elseif ($updatedSite['type'] === 'laravel') {
                    $newCompose = createLaravelDockerCompose($updatedSite, []);
                }
                
                if ($newCompose) {
                    file_put_contents($composePath, $newCompose);
                    
                    // Save to database
                    saveComposeConfig($db, $newCompose, $updatedSite['owner_id'] ?? 1, $updatedSite['id']);
                    
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
        
        $containerName = $site['container_name'];
        
        // Stop and remove containers using docker-compose
        $composePath = "/app/apps/{$site['type']}/sites/{$containerName}/docker-compose.yml";
        if (file_exists($composePath)) {
            // Use 'down' without -v to preserve volumes, or 'down -v' to delete them
            $command = $keepData ? "down" : "down -v";
            executeDockerCompose($composePath, $command);
        }
        
        // Remove standalone Redis container if it exists (created separately from docker-compose)
        $redisContainerName = $containerName . '_redis';
        exec("docker ps -a --filter name=" . escapeshellarg($redisContainerName) . " --format '{{.Names}}' 2>&1", $redisCheck, $redisCode);
        if ($redisCode === 0 && !empty($redisCheck) && trim($redisCheck[0]) === $redisContainerName) {
            exec("docker rm -f " . escapeshellarg($redisContainerName) . " 2>&1");
        }
        
        // Remove database container if it exists (might be created separately)
        $dbContainerName = $containerName . '_db';
        exec("docker ps -a --filter name=" . escapeshellarg($dbContainerName) . " --format '{{.Names}}' 2>&1", $dbCheck, $dbCode);
        if ($dbCode === 0 && !empty($dbCheck) && trim($dbCheck[0]) === $dbContainerName) {
            exec("docker rm -f " . escapeshellarg($dbContainerName) . " 2>&1");
        }
        
        // Remove main container if it still exists
        exec("docker ps -a --filter name=" . escapeshellarg($containerName) . " --format '{{.Names}}' 2>&1", $mainCheck, $mainCode);
        if ($mainCode === 0 && !empty($mainCheck) && trim($mainCheck[0]) === $containerName) {
            exec("docker rm -f " . escapeshellarg($containerName) . " 2>&1");
        }
        
        // Remove volumes if not keeping data
        if (!$keepData) {
            // Remove all volumes associated with this site
            $volumePatterns = [
                "wp_{$containerName}_data",
                "{$containerName}_data",
                "{$containerName}_db_data"
            ];
            
            foreach ($volumePatterns as $volumeName) {
                exec("docker volume ls --format '{{.Name}}' | grep -x " . escapeshellarg($volumeName) . " 2>&1", $volCheck, $volCode);
                if ($volCode === 0 && !empty($volCheck)) {
                    exec("docker volume rm " . escapeshellarg($volumeName) . " 2>&1");
                }
            }
        }
        
        // Delete the entire site directory
        $siteDir = "/app/apps/{$site['type']}/sites/{$containerName}";
        if (is_dir($siteDir)) {
            // Recursively delete the directory
            exec("rm -rf " . escapeshellarg($siteDir) . " 2>&1");
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

function getSiteContainerLogs($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $lines = $_GET['lines'] ?? 100;
        $containerName = escapeshellarg($site['container_name']);
        
        exec("docker logs --tail " . intval($lines) . " {$containerName} 2>&1", $output, $returnCode);
        
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

function getContainerStats($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        // Get container uptime
        $uptimeResult = executeDockerCommand("inspect --format='{{.State.StartedAt}}' {$site['container_name']}");
        $startedAt = trim($uptimeResult['output']);
        
        // Get volume size - use a simpler approach
        $volumeName = $site['type'] === 'wordpress' ? "wp_{$site['container_name']}_data" : "{$site['container_name']}_data";
        $volumeSize = 'N/A';
        
        // Try to get volume size using docker volume inspect
        try {
            $volumeInspect = executeDockerCommand("volume inspect {$volumeName} --format '{{.Mountpoint}}'");
            
            if ($volumeInspect['success'] && !empty($volumeInspect['output'])) {
                $mountpoint = trim($volumeInspect['output']);
                // Get directory size
                exec("du -sh {$mountpoint} 2>/dev/null | awk '{print $1}'", $sizeOutput, $sizeReturnCode);
                if ($sizeReturnCode === 0 && !empty($sizeOutput)) {
                    $volumeSize = trim($sizeOutput[0]);
                }
            }
        } catch (Exception $e) {
            // Volume size will remain N/A
        }
        
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
        
        // Get CPU and Memory stats for all related containers
        $cpu = 'N/A';
        $cpuPercent = 0;
        $memory = 'N/A';
        $memPercent = 0;
        $totalCpuPercent = 0;
        $totalMemMB = 0;
        $totalMemPercent = 0;
        $containerCount = 0;
        
        // List of potential containers for this site
        $containers = [$site['container_name']];
        
        // Add database container if it exists
        $dbContainer = $site['container_name'] . '_db';
        $dbCheck = executeDockerCommand("ps -a --filter name=^{$dbContainer}$ --format '{{.Names}}'");
        if ($dbCheck['success'] && trim($dbCheck['output']) === $dbContainer) {
            $containers[] = $dbContainer;
        }
        
        // Add Redis container if it exists
        $redisContainer = $site['container_name'] . '_redis';
        $redisCheck = executeDockerCommand("ps -a --filter name=^{$redisContainer}$ --format '{{.Names}}'");
        if ($redisCheck['success'] && trim($redisCheck['output']) === $redisContainer) {
            $containers[] = $redisContainer;
        }
        
        // Add SFTP container if it exists
        $sftpContainer = $site['container_name'] . '_sftp';
        $sftpCheck = executeDockerCommand("ps -a --filter name=^{$sftpContainer}$ --format '{{.Names}}'");
        if ($sftpCheck['success'] && trim($sftpCheck['output']) === $sftpContainer) {
            $containers[] = $sftpContainer;
        }
        
        // Get stats for all containers (only running ones)
        foreach ($containers as $containerName) {
            // Check if container is running first
            $statusCheck = executeDockerCommand("inspect --format='{{.State.Running}}' {$containerName}");
            if (!$statusCheck['success'] || trim($statusCheck['output']) !== 'true') {
                continue; // Skip stopped containers
            }
            
            $statsResult = executeDockerCommand("stats {$containerName} --no-stream --format '{{.CPUPerc}}|{{.MemUsage}}|{{.MemPerc}}'");
            if ($statsResult['success'] && !empty($statsResult['output'])) {
                $parts = explode('|', trim($statsResult['output']));
                if (count($parts) >= 3) {
                    $containerCpu = floatval(str_replace('%', '', trim($parts[0])));
                    $containerMem = trim($parts[1]);
                    $containerMemPercent = floatval(str_replace('%', '', trim($parts[2])));
                    
                    $totalCpuPercent += $containerCpu;
                    $totalMemPercent += $containerMemPercent;
                    
                    // Parse memory (e.g., "45.5MiB / 1.5GiB")
                    if (preg_match('/([0-9.]+)([A-Za-z]+)/', $containerMem, $matches)) {
                        $memValue = floatval($matches[1]);
                        $memUnit = strtoupper($matches[2]);
                        
                        // Convert to MB
                        if ($memUnit === 'GIB' || $memUnit === 'GB') {
                            $totalMemMB += $memValue * 1024;
                        } else if ($memUnit === 'MIB' || $memUnit === 'MB') {
                            $totalMemMB += $memValue;
                        } else if ($memUnit === 'KIB' || $memUnit === 'KB') {
                            $totalMemMB += $memValue / 1024;
                        }
                    }
                    
                    $containerCount++;
                }
            }
        }
        
        // Format the combined stats
        if ($containerCount > 0) {
            $cpuPercent = round($totalCpuPercent, 2);
            $cpu = $cpuPercent . '%';
            
            // Format memory
            if ($totalMemMB >= 1024) {
                $memory = round($totalMemMB / 1024, 2) . ' GiB';
            } else {
                $memory = round($totalMemMB, 2) . ' MiB';
            }
            
            $memPercent = round($totalMemPercent, 2);
        }
        
        echo json_encode([
            "success" => true,
            "stats" => [
                "uptime" => $uptime,
                "volume_size" => $volumeSize,
                "status" => getDockerContainerStatus($site['container_name']),
                "cpu" => $cpu,
                "cpu_percent" => $cpuPercent,
                "memory" => $memory,
                "mem_percent" => $memPercent
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
        
        // Get current user with password hash
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("User not logged in");
        }
        
        $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
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

function restartWebGui() {
    try {
        // Use docker-compose up with --force-recreate to apply new labels
        exec("cd /opt/webbadeploy && docker-compose up -d --force-recreate web-gui 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode([
                "success" => true,
                "message" => "Web-GUI recreated successfully with new configuration"
            ]);
        } else {
            throw new Exception("Failed to restart Web-GUI: " . implode("\n", $output));
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
    // Immediate error logging to debug
    error_log("exportDatabase called with site_id: " . ($_GET['site_id'] ?? 'NONE'));
    
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
        
        $containerName = $site['container_name'] . '_db';
        $timestamp = date('Y-m-d_H-i-s');
        $siteName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $site['name']);
        $filename = "backup_{$siteName}_{$timestamp}.sql";
        $backupPath = "/app/data/backups/{$filename}";
        
        // Create backups directory if it doesn't exist
        if (!is_dir('/app/data/backups')) {
            mkdir('/app/data/backups', 0755, true);
        }
        
        // Export database using mysqldump
        $password = $site['db_password'] ?? '';
        
        // Build command with proper escaping (use full path to docker)
        // Note: Don't use escapeshellarg on password inside sh -c quotes
        // Note: MariaDB uses 'mariadb-dump' not 'mysqldump'
        $cmd = sprintf(
            "/usr/bin/docker exec %s sh -c \"MYSQL_PWD=%s mariadb-dump -u wordpress wordpress\" > %s 2>&1",
            escapeshellarg($containerName),
            $password, // Don't escape - it's inside double quotes
            escapeshellarg($backupPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        // Debug info (log to server and include in response)
        error_log("Export command: " . $cmd);
        error_log("Return code: " . $returnCode);
        error_log("Output: " . implode("\n", $output));
        error_log("Backup path: " . $backupPath);
        error_log("File exists: " . (file_exists($backupPath) ? 'yes' : 'no'));
        
        $debugCommand = str_replace(escapeshellarg($password), '[PASSWORD]', $cmd);
        
        if ($returnCode === 0 && file_exists($backupPath) && filesize($backupPath) > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Database exported successfully",
                "file" => $filename,
                "size" => filesize($backupPath),
                "download_url" => "/download.php?file=" . urlencode($filename)
            ]);
        } else {
            $errorMsg = "Export failed. ";
            $debugInfo = [
                'return_code' => $returnCode,
                'container' => $containerName,
                'backup_path' => $backupPath,
                'file_exists' => file_exists($backupPath),
                'file_size' => file_exists($backupPath) ? filesize($backupPath) : 0,
                'output' => implode("\n", $output),
                'command' => $debugCommand,
                'has_password' => !empty($password)
            ];
            
            if (!empty($output)) {
                $errorMsg .= "Error: " . implode("\n", $output);
            }
            if (!file_exists($backupPath)) {
                $errorMsg .= " Backup file was not created.";
            } elseif (filesize($backupPath) === 0) {
                $errorMsg .= " Backup file is empty.";
            }
            
            // Return detailed error
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => $errorMsg,
                "debug" => $debugInfo
            ]);
            return;
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
        
        $containerName = $site['container_name'] . '_db';
        
        // Get database size and table count
        $sqlCommand = "SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB',
            COUNT(*) AS 'Tables'
            FROM information_schema.TABLES 
            WHERE table_schema = 'wordpress'";
        
        $password = $site['db_password'] ?? '';
        // Use mariadb instead of mysql, and full path to docker
        $command = sprintf(
            "/usr/bin/docker exec %s sh -c \"MYSQL_PWD=%s mariadb -u wordpress -e %s\" 2>&1",
            escapeshellarg($containerName),
            $password,
            escapeshellarg($sqlCommand)
        );
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

function getSiteContainers($db, $siteId) {
    try {
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        
        // Get all containers related to this site
        $cmd = "docker ps -a --filter name=" . escapeshellarg($containerName) . " --format '{{.Names}}|{{.Image}}|{{.Status}}|{{.State}}' 2>&1";
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to get containers: " . implode("\n", $output));
        }
        
        $containers = [];
        foreach ($output as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                // Extract uptime from status (e.g., "Up 6 minutes")
                $uptime = '';
                if (preg_match('/Up (.+?)(?:\s+\(|$)/', $parts[2], $matches)) {
                    $uptime = $matches[1];
                }
                
                $containers[] = [
                    'name' => $parts[0],
                    'image' => $parts[1],
                    'status' => strtolower($parts[3]),
                    'uptime' => $uptime
                ];
            }
        }
        
        echo json_encode([
            "success" => true,
            "containers" => $containers
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function generateDbToken($db) {
    try {
        require_once 'includes/db-token.php';
        
        $siteId = $_GET['site_id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Check if site has a database
        $dbType = $site['db_type'] ?? 'none';
        $siteType = $site['type'];
        
        // Check if site has database access
        $hasDatabase = false;
        if ($siteType === 'wordpress' && $dbType === 'dedicated') {
            $hasDatabase = true;
        } elseif ($siteType === 'php' && in_array($dbType, ['mysql', 'postgresql'])) {
            $hasDatabase = true;
        } elseif ($siteType === 'laravel' && in_array($dbType, ['mysql', 'postgresql'])) {
            $hasDatabase = true;
        }
        
        if (!$hasDatabase) {
            throw new Exception("This site does not have database access");
        }
        
        // Create tables if they don't exist
        createDatabaseTokenTables($db);
        
        // Clean up old tokens
        cleanupExpiredTokens($db);
        
        // Generate token
        $currentUser = getCurrentUser();
        $token = generateDatabaseToken($db, $siteId, $currentUser['id']);
        
        echo json_encode([
            "success" => true,
            "token" => $token,
            "expires_in" => DB_TOKEN_EXPIRY
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

// ============================================
// User Management Handlers
// ============================================

function createUserHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $canCreateSites = isset($_POST['can_create_sites']) ? 1 : 0;
        
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required");
        }
        
        $result = createUser($username, $password, $email);
        
        if ($result['success']) {
            // Update role and permissions
            updateUser($result['user_id'], [
                'role' => $role,
                'can_create_sites' => $canCreateSites
            ]);
            
            logAudit('user_created', 'user', $result['user_id'], ['username' => $username]);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getUserHandler() {
    try {
        $userId = $_GET['id'] ?? '';
        
        // If no ID provided, return current user
        if (empty($userId)) {
            $user = getCurrentUser();
            if (!$user) {
                throw new Exception("Not logged in");
            }
            echo json_encode(['success' => true, 'user' => $user]);
            return;
        }
        
        // Admin only for viewing other users
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
            return;
        }
        
        $user = getUserById($userId);
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        echo json_encode(['success' => true, 'user' => $user]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateUserHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_POST['user_id'] ?? '';
        $email = $_POST['email'] ?? null;
        $role = $_POST['role'] ?? null;
        $canCreateSites = isset($_POST['can_create_sites']) ? 1 : 0;
        
        if (empty($userId)) {
            throw new Exception("User ID is required");
        }
        
        $data = [];
        if ($email !== null) $data['email'] = $email;
        if ($role !== null) $data['role'] = $role;
        $data['can_create_sites'] = $canCreateSites;
        
        $result = updateUser($userId, $data);
        
        if ($result['success']) {
            logAudit('user_updated', 'user', $userId, $data);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteUserHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_GET['id'] ?? '';
        
        if (empty($userId)) {
            throw new Exception("User ID is required");
        }
        
        $result = deleteUser($userId);
        
        if ($result['success']) {
            logAudit('user_deleted', 'user', $userId);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function grantSitePermissionHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_POST['user_id'] ?? '';
        $siteId = $_POST['site_id'] ?? '';
        $permission = $_POST['permission'] ?? 'view';
        
        if (empty($userId) || empty($siteId)) {
            throw new Exception("User ID and Site ID are required");
        }
        
        $result = grantSitePermission($userId, $siteId, $permission);
        
        if ($result['success']) {
            logAudit('permission_granted', 'site', $siteId, ['user_id' => $userId, 'permission' => $permission]);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function revokeSitePermissionHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_POST['user_id'] ?? '';
        $siteId = $_POST['site_id'] ?? '';
        
        if (empty($userId) || empty($siteId)) {
            throw new Exception("User ID and Site ID are required");
        }
        
        $result = revokeSitePermission($userId, $siteId);
        
        if ($result['success']) {
            logAudit('permission_revoked', 'site', $siteId, ['user_id' => $userId]);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getUserPermissionsHandler($db) {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_GET['user_id'] ?? '';
        
        if (empty($userId)) {
            throw new Exception("User ID is required");
        }
        
        // Get all sites with user permissions
        $authDb = initAuthDatabase();
        $stmt = $authDb->prepare("
            SELECT sp.site_id, sp.permission, s.name as site_name, s.domain
            FROM site_permissions sp
            JOIN sites s ON sp.site_id = s.id
            WHERE sp.user_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'permissions' => $permissions]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// 2FA Handlers
// ============================================

function setup2FAHandler() {
    try {
        $totp = new TOTP();
        $secret = $totp->generateSecret();
        $currentUser = getCurrentUser();
        
        $provisioningUri = $totp->getProvisioningUri($currentUser['username'], $secret);
        
        // Generate QR code directly and return as data URL
        $qrCodeDataUrl = generateQRCodeDataUrl($provisioningUri);
        
        // Store secret temporarily in session
        $_SESSION['2fa_setup_secret'] = $secret;
        
        echo json_encode([
            'success' => true,
            'secret' => $secret,
            'qr_code_url' => $qrCodeDataUrl,
            'provisioning_uri' => $provisioningUri
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generateQRCodeDataUrl($data) {
    $size = 250;
    $encodedData = urlencode($data);
    
    // Try multiple APIs
    $apis = [
        "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}",
        "https://quickchart.io/qr?text={$encodedData}&size={$size}",
        "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encodedData}"
    ];
    
    foreach ($apis as $url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $imageData = @file_get_contents($url, false, $context);
        
        if ($imageData && strlen($imageData) > 100) {
            // Convert to base64 data URL
            $base64 = base64_encode($imageData);
            return 'data:image/png;base64,' . $base64;
        }
    }
    
    // Fallback: return empty data URL
    return '';
}

function verify2FASetupHandler() {
    try {
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['2fa_setup_secret'] ?? '';
        
        if (empty($secret)) {
            throw new Exception("No 2FA setup in progress");
        }
        
        $totp = new TOTP();
        if (!$totp->verifyCode($secret, $code)) {
            throw new Exception("Invalid verification code");
        }
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function enable2FAHandler() {
    try {
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['2fa_setup_secret'] ?? '';
        $currentUser = getCurrentUser();
        
        if (empty($secret)) {
            throw new Exception("No 2FA setup in progress");
        }
        
        $totp = new TOTP();
        if (!$totp->verifyCode($secret, $code)) {
            throw new Exception("Invalid verification code");
        }
        
        // Generate backup codes
        $backupCodes = generateBackupCodes();
        
        // Enable 2FA
        $result = enable2FA($currentUser['id'], $secret, $backupCodes);
        
        if ($result['success']) {
            unset($_SESSION['2fa_setup_secret']);
            echo json_encode([
                'success' => true,
                'backup_codes' => $backupCodes
            ]);
        } else {
            throw new Exception($result['error']);
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function disable2FAHandler() {
    try {
        $password = $_POST['password'] ?? '';
        $currentUser = getCurrentUser();
        
        if (empty($password)) {
            throw new Exception("Password is required to disable 2FA");
        }
        
        // Verify password
        $result = authenticateUser($currentUser['username'], $password);
        if (!$result['success']) {
            throw new Exception("Invalid password");
        }
        
        $result = disable2FA($currentUser['id']);
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// Redis Management Handlers
// ============================================

function createRedisContainer($site, $db) {
    $containerName = $site['container_name'];
    $redisContainerName = $containerName . '_redis';
    $networkName = 'webbadeploy_webbadeploy';
    
    // Check if Redis container already exists
    exec("docker ps -a --filter name=" . escapeshellarg($redisContainerName) . " --format '{{.Names}}' 2>&1", $checkOutput, $checkCode);
    
    if ($checkCode === 0 && !empty($checkOutput) && trim($checkOutput[0]) === $redisContainerName) {
        // Already exists, just update database
        $stmt = $db->prepare("UPDATE sites SET redis_enabled = 1, redis_host = ?, redis_port = 6379 WHERE id = ?");
        $stmt->execute([$redisContainerName, $site['id']]);
        return;
    }
    
    // Create Redis container
    $createCommand = sprintf(
        "docker run -d --name %s --network %s --restart unless-stopped redis:alpine",
        escapeshellarg($redisContainerName),
        escapeshellarg($networkName)
    );
    
    exec($createCommand . " 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        // Update database
        $stmt = $db->prepare("UPDATE sites SET redis_enabled = 1, redis_host = ?, redis_port = 6379 WHERE id = ?");
        $stmt->execute([$redisContainerName, $site['id']]);
        
        logAudit('redis_enabled', 'site', $site['id']);
    }
}

function enableRedisHandler($db) {
    try {
        $siteId = $_GET['id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        // Check if user has access to this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $site = getSiteById($db, $siteId);
        
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        $redisContainerName = $containerName . '_redis';
        
        // Check if Redis container already exists
        exec("docker ps -a --filter name=" . escapeshellarg($redisContainerName) . " --format '{{.Names}}' 2>&1", $checkOutput, $checkCode);
        
        if ($checkCode === 0 && !empty($checkOutput) && trim($checkOutput[0]) === $redisContainerName) {
            echo json_encode(['success' => true, 'message' => 'Redis is already enabled']);
            return;
        }
        
        // Create Redis container
        $networkName = 'webbadeploy_webbadeploy';
        $createCommand = sprintf(
            "docker run -d --name %s --network %s --restart unless-stopped redis:alpine",
            escapeshellarg($redisContainerName),
            escapeshellarg($networkName)
        );
        
        exec($createCommand . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to create Redis container: " . implode("\n", $output));
        }
        
        // Update database
        $stmt = $db->prepare("UPDATE sites SET redis_enabled = 1, redis_host = ?, redis_port = 6379 WHERE id = ?");
        $stmt->execute([$redisContainerName, $siteId]);
        
        logAudit('redis_enabled', 'site', $siteId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Redis enabled successfully',
            'redis_host' => $redisContainerName
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function disableRedisHandler($db) {
    try {
        $siteId = $_GET['id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        // Check if user has access to this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $site = getSiteById($db, $siteId);
        
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        $redisContainerName = $containerName . '_redis';
        
        // Stop and remove Redis container
        exec("docker stop " . escapeshellarg($redisContainerName) . " 2>&1", $stopOutput, $stopCode);
        exec("docker rm " . escapeshellarg($redisContainerName) . " 2>&1", $rmOutput, $rmCode);
        
        // Update database
        $stmt = $db->prepare("UPDATE sites SET redis_enabled = 0, redis_host = NULL WHERE id = ?");
        $stmt->execute([$siteId]);
        
        logAudit('redis_disabled', 'site', $siteId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Redis disabled successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function flushRedisHandler($db) {
    try {
        $siteId = $_GET['id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        // Check if user has access to this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $site = getSiteById($db, $siteId);
        
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        $redisContainerName = $containerName . '_redis';
        
        // Flush Redis cache
        exec("docker exec " . escapeshellarg($redisContainerName) . " redis-cli FLUSHALL 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to flush Redis: " . implode("\n", $output));
        }
        
        logAudit('redis_flushed', 'site', $siteId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Redis cache flushed successfully',
            'output' => implode("\n", $output)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function restartRedisHandler($db) {
    try {
        $siteId = $_GET['id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        // Check if user has access to this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $site = getSiteById($db, $siteId);
        
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        $redisContainerName = $containerName . '_redis';
        
        // Restart Redis container
        exec("docker restart " . escapeshellarg($redisContainerName) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to restart Redis: " . implode("\n", $output));
        }
        
        logAudit('redis_restarted', 'site', $siteId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Redis restarted successfully',
            'output' => implode("\n", $output)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateSettingHandler($db) {
    try {
        // Only admins can update settings
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
            return;
        }
        
        $input = json_decode(file_get_contents("php://input"), true);
        $key = $input['key'] ?? '';
        $value = $input['value'] ?? '';
        
        if (empty($key)) {
            throw new Exception("Setting key is required");
        }
        
        $result = setSetting($db, $key, $value);
        
        if ($result) {
            logAudit('setting_updated', 'setting', null, ['key' => $key]);
            echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
        } else {
            throw new Exception("Failed to update setting");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function changePHPVersionHandler($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            throw new Exception("Invalid JSON data");
        }
        
        $siteId = $input['site_id'] ?? null;
        $newVersion = $input['php_version'] ?? null;
        
        if (!$siteId || !$newVersion) {
            throw new Exception("Site ID and PHP version are required");
        }
        
        // Validate PHP version
        $validVersions = ['7.4', '8.0', '8.1', '8.2', '8.3'];
        if (!in_array($newVersion, $validVersions)) {
            throw new Exception("Invalid PHP version. Supported: " . implode(', ', $validVersions));
        }
        
        // Get site
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Check permission
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            throw new Exception("You don't have permission to manage this site");
        }
        
        // Update database
        $stmt = $db->prepare("UPDATE sites SET php_version = ? WHERE id = ?");
        $stmt->execute([$newVersion, $siteId]);
        
        // Get updated site
        $site = getSiteById($db, $siteId);
        $site['php_version'] = $newVersion; // Ensure it's set
        
        // Regenerate docker-compose with new PHP version
        $composePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/docker-compose.yml";
        
        $newCompose = '';
        if ($site['type'] === 'wordpress') {
            $newCompose = createWordPressDockerCompose($site, []);
        } elseif ($site['type'] === 'php') {
            $newCompose = createPHPDockerCompose($site, []);
        } elseif ($site['type'] === 'laravel') {
            $newCompose = createLaravelDockerCompose($site, []);
        } else {
            throw new Exception("PHP version switching not supported for this site type");
        }
        
        if ($newCompose) {
            // Save to database
            saveComposeConfig($db, $newCompose, $_SESSION['user_id'], $siteId);
            
            // Write to file
            file_put_contents($composePath, $newCompose);
            
            // Recreate container with new PHP version
            $result = executeDockerCompose($composePath, "up -d --force-recreate");
            
            if ($result['success']) {
                logAudit('php_version_changed', 'site', $siteId, [
                    'new_version' => $newVersion,
                    'site_name' => $site['name']
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => "PHP version changed to {$newVersion} successfully!"
                ]);
            } else {
                throw new Exception("Failed to recreate container: " . $result['output']);
            }
        } else {
            throw new Exception("Failed to generate docker-compose configuration");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
