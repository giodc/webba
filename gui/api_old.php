<?php
header('Content-Type: application/json');
require_once 'includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$db = initDatabase();

switch ($method) {
    case 'POST':
        handlePost($db, $action);
        break;
    case 'GET':
        handleGet($db, $action);
        break;
    case 'DELETE':
        handleDelete($db, $action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handlePost($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'create_site':
            createNewSite($db, $input);
            break;
        case 'manage_site':
            manageSite($db, $input);
            break;
        case 'ssl_certificate':
            requestSSL($db, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handleGet($db, $action) {
    switch ($action) {
        case 'sites':
            echo json_encode(['sites' => getAllSites($db)]);
            break;
        case 'site_status':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $site = getSiteById($db, $id);
                if ($site) {
                    $status = getDockerContainerStatus($site['container_name']);
                    updateSiteStatus($db, $id, $status);
                    echo json_encode(['status' => $status]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Site not found']);
                }
            }
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handleDelete($db, $action) {
    switch ($action) {
        case 'delete_site':
            $id = $_GET['id'] ?? null;
            if ($id) {
                deleteSiteById($db, $id);
            }
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function createNewSite($db, $data) {
    try {
        // Validate input
        if (empty($data['name']) || empty($data['type']) || empty($data['domain'])) {
            throw new Exception('Missing required fields');
        }

        // Generate site configuration
        $siteId = generateSiteId($data['name']);
        $containerName = $data['type'] . '_' . preg_replace("/[^a-z0-9]/", "", strtolower($data['name'])) . "_" . time();

        // Determine final domain
        $domain = $data['domain'];
        if ($data['domain_suffix'] !== 'custom') {
            $domain = $data['domain'] . $data['domain_suffix'];
        } else {
            $domain = $data['custom_domain'];
        }

        $siteConfig = [
            'name' => $data['name'],
            'type' => $data['type'],
            'domain' => $domain,
            'ssl' => $data['ssl'] ?? false,
            'container_name' => $containerName,
            'config' => $data
        ];

        // Create site record
        if (!createSite($db, $siteConfig)) {
            throw new Exception('Failed to create site record');
        }

        // Get the site ID
        $siteId = $db->lastInsertId();
        $site = getSiteById($db, $siteId);

        // Deploy the application based on type
        switch ($data['type']) {
            case 'wordpress':
                deployWordPress($site, $data);
                break;
            case 'php':
                deployPHP($site, $data);
                break;
            case 'laravel':
                deployLaravel($site, $data);
                break;
        }

        // Create Nginx configuration
        $nginxConfig = createNginxSiteConfig($site);
        file_put_contents("/app/nginx/sites/{$site['domain']}.conf", $nginxConfig);

        // Reload Nginx
        reloadNginx();

        // Request SSL if needed
        if ($site['ssl'] && $data['domain_suffix'] === 'custom') {
            requestSSLCertificate($site['domain'], $data['wp_email'] ?? 'admin@' . $site['domain']);
        }

        updateSiteStatus($db, $siteId, 'running');

        echo json_encode([
            'success' => true,
            'message' => 'Site created successfully',
            'site' => $site
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function deployWordPress($site, $config) {
    $wpConfig = createWordPressDockerCompose($site, $config);

    // Save docker-compose.yml
    $composePath = "/app/apps/wordpress/sites/{$site['container_name']}/docker-compose.yml";
    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($composePath, $wpConfig['compose']);

    // Create database for WordPress
    createWordPressDatabase($wpConfig['site_id'], $wpConfig['db_config']);

    // Start the containers
    $result = executeDockerCompose($composePath, 'up -d');
    if (!$result['success']) {
        throw new Exception('Failed to start WordPress containers: ' . $result['output']);
    }

    // Wait for WordPress to be ready then configure it
    sleep(10);
    configureWordPress($site, $config, $wpConfig['db_config']);
}

function createWordPressDatabase($siteId, $dbConfig) {
    $sql = "CREATE DATABASE IF NOT EXISTS wp_{$siteId};
            CREATE USER IF NOT EXISTS '{$dbConfig['user']}'@'%' IDENTIFIED BY '{$dbConfig['password']}';
            GRANT ALL PRIVILEGES ON wp_{$siteId}.* TO '{$dbConfig['user']}'@'%';
            FLUSH PRIVILEGES;";

    $result = executeDockerCommand("exec wharftales_db mysql -u root -pwharftales_root_pass -e \"$sql\"");
    if (!$result['success']) {
        throw new Exception('Failed to create database: ' . $result['output']);
    }
}

function configureWordPress($site, $config, $dbConfig) {
    // Install WordPress via WP-CLI
    $wpInstallCommand = "wp core install --url='{$site['domain']}' --title='{$config['name']}' --admin_user='{$config['wp_admin']}' --admin_password='{$config['wp_password']}' --admin_email='{$config['wp_email']}'";

    $result = executeDockerCommand("exec {$site['container_name']} $wpInstallCommand");
    if (!$result['success']) {
        error_log('WordPress install failed: ' . $result['output']);
    }

    // Install performance plugins if optimization is enabled
    if ($config['wp_optimize']) {
        $plugins = ['redis-cache', 'autoptimize', 'wp-fastest-cache'];
        foreach ($plugins as $plugin) {
            executeDockerCommand("exec {$site['container_name']} wp plugin install $plugin --activate");
        }

        // Configure Redis
        executeDockerCommand("exec {$site['container_name']} wp redis enable");
    }
}

function deployPHP($site, $config) {
    // Create PHP application container
    $composePath = "/app/apps/php/sites/{$site['container_name']}/docker-compose.yml";
    $phpCompose = createPHPDockerCompose($site, $config);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($composePath, $phpCompose);

    $result = executeDockerCompose($composePath, 'up -d');
    if (!$result['success']) {
        throw new Exception('Failed to start PHP application: ' . $result['output']);
    }
}

function deployLaravel($site, $config) {
    // Create Laravel application container
    $composePath = "/app/apps/laravel/sites/{$site['container_name']}/docker-compose.yml";
    $laravelCompose = createLaravelDockerCompose($site, $config);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($composePath, $laravelCompose);

    $result = executeDockerCompose($composePath, 'up -d');
    if (!$result['success']) {
        throw new Exception('Failed to start Laravel application: ' . $result['output']);
    }

    // Run Laravel setup commands
    sleep(5);
    executeDockerCommand("exec {$site['container_name']} php artisan key:generate");
    executeDockerCommand("exec {$site['container_name']} php artisan migrate");
}

function deleteSiteById($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception('Site not found');
        }

        // Stop and remove containers
        $composePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/docker-compose.yml";
        if (file_exists($composePath)) {
            executeDockerCompose($composePath, 'down -v');
            unlink($composePath);
            rmdir(dirname($composePath));
        }

        // Remove Nginx configuration
        $nginxConfigPath = "/app/nginx/sites/{$site['domain']}.conf";
        if (file_exists($nginxConfigPath)) {
            unlink($nginxConfigPath);
        }

        // Remove SSL certificates if they exist
        if ($site['ssl']) {
            executeDockerCommand("exec wharftales_nginx certbot delete --cert-name {$site['domain']} --non-interactive");
        }

        // Delete database record
        deleteSite($db, $id);

        // Reload Nginx
        reloadNginx();

        echo json_encode([
            'success' => true,
            'message' => 'Site deleted successfully'
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function createPHPDockerCompose($site, $config) {
    return "version: '3.8'
services:
  php_app:
  {$site['container_name']}:
    image: php:8.2-apache
    container_name: {$site['container_name']}
    volumes:
      - php_{$site['container_name']}_data:/var/www/html
    networks:
      - wharftales
    restart: unless-stopped

volumes:
  php_{$site['container_name']}_data:

networks:
  wharftales:
    external: true";
}

function createLaravelDockerCompose($site, $config) {
    return "version: '3.8'
services:
  php_app:
  {$site['container_name']}:
    image: php:8.2-fpm
    container_name: {$site['container_name']}
    volumes:
      - laravel_{$site['container_name']}_data:/var/www/html
    environment:
      - DB_HOST=wharftales_db
      - DB_DATABASE=laravel_{$site['container_name']}
      - DB_USERNAME=laravel_user
      - DB_PASSWORD=laravel_pass
    networks:
      - wharftales
    restart: unless-stopped

volumes:
  laravel_{$site['container_name']}_data:

networks:
  wharftales:
    external: true";
}
?>
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

        // Update status if changed
        if (isset($input["status"]) && $input["status"] !== $site["status"]) {
            updateSiteStatus($db, $siteId, $input["status"]);
            
            // Handle container start/stop
            if ($input["status"] === "running" && $site["status"] === "stopped") {
                $composePath = "/app/apps/{$site[type]}/sites/{$site[container_name]}/docker-compose.yml";
                if (file_exists($composePath)) {
                    executeDockerCompose($composePath, "up -d");
                }
            } elseif ($input["status"] === "stopped" && $site["status"] === "running") {
                $composePath = "/app/apps/{$site[type]}/sites/{$site[container_name]}/docker-compose.yml";
                if (file_exists($composePath)) {
                    executeDockerCompose($composePath, "stop");
                }
            }
        }

        // Update nginx config if domain changed
        if ($input["domain"] !== $site["domain"]) {
            // Remove old config
            $oldConfigPath = "/app/nginx/sites/{$site[domain]}.conf";
            if (file_exists($oldConfigPath)) {
                unlink($oldConfigPath);
            }
            
            // Create new config
            $updatedSite = getSiteById($db, $siteId);
            $nginxConfig = createNginxSiteConfig($updatedSite);
            file_put_contents("/app/nginx/sites/{$input[domain]}.conf", $nginxConfig);
            
            // Reload nginx
            reloadNginx();
        }

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
