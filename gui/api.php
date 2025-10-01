<?php
require_once "includes/functions.php";

header("Content-Type: application/json");

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
            "config" => $data
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

        // Traefik will automatically discover the container via labels
        // No manual configuration needed!

        // Request SSL if needed
        if ($site["ssl"] && $data["domain_suffix"] === "custom") {
            requestSSLCertificate($site["domain"], $data["wp_email"] ?? "admin@" . $site["domain"]);
        }

        updateSiteStatus($db, $siteId, "running");

        echo json_encode([
            "success" => true,
            "message" => "Site created successfully",
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
        <p>PHP app created by WebBadeploy.</p>
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
    
    return "version: '3.8'
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
      - traefik.http.services.{$containerName}.loadbalancer.server.port=80
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped

volumes:
  {$containerName}_data:

networks:
  webbadeploy_webbadeploy:
    external: true";
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
    
    return "version: '3.8'
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
      - traefik.http.services.{$containerName}.loadbalancer.server.port=80
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped

volumes:
  {$containerName}_data:

networks:
  webbadeploy_webbadeploy:
    external: true";
}

function createWordPressDockerCompose($site, $config) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    
    // Generate database credentials
    $siteId = generateSiteId($site['name']);
    $dbUser = 'wp_' . substr(md5($site['name']), 0, 8);
    $dbPass = generateRandomString(16);
    $tablePrefix = 'wp_' . substr(md5($site['name']), 0, 4) . '_';
    
    return "version: '3.8'
services:
  {$containerName}:
    image: wordpress:latest
    container_name: {$containerName}
    environment:
      - WORDPRESS_DB_HOST=webbadeploy_db
      - WORDPRESS_DB_NAME=wp_{$siteId}
      - WORDPRESS_DB_USER={$dbUser}
      - WORDPRESS_DB_PASSWORD={$dbPass}
      - WORDPRESS_TABLE_PREFIX={$tablePrefix}
    volumes:
      - wp_{$containerName}_data:/var/www/html
    labels:
      - traefik.enable=true
      - traefik.http.routers.{$containerName}.rule=Host(`{$domain}`)
      - traefik.http.routers.{$containerName}.entrypoints=web
      - traefik.http.services.{$containerName}.loadbalancer.server.port=80
    networks:
      - webbadeploy_webbadeploy
    restart: unless-stopped

volumes:
  wp_{$containerName}_data:

networks:
  webbadeploy_webbadeploy:
    external: true";
}

function deployWordPress($site, $config) {
    // Ensure container_name is set
    if (empty($site['container_name'])) {
        throw new Exception("CRITICAL: deployWordPress received empty container_name for site: " . $site["name"]);
    }
    
    // Create WordPress application containers
    $composePath = "/app/apps/wordpress/sites/{$site['container_name']}/docker-compose.yml";
    $wpCompose = createWordPressDockerCompose($site, $config);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($composePath, $wpCompose);

    $result = executeDockerCompose($composePath, "up -d");
    if (!$result["success"]) {
        throw new Exception("Failed to start WordPress application: " . $result["output"]);
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

        // Update basic site information
        $stmt = $db->prepare("UPDATE sites SET name = ?, domain = ?, ssl = ? WHERE id = ?");
        $stmt->execute([
            $input["name"],
            $input["domain"], 
            $input["ssl"] ? 1 : 0,
            $siteId
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Site updated successfully"
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
?>
