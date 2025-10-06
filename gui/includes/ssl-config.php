<?php

/**
 * SSL Configuration Management
 * Handles both HTTP and DNS challenge methods for Let's Encrypt
 */

function saveSSLConfig($db, $siteId, $sslConfig) {
    $stmt = $db->prepare("UPDATE sites SET ssl_config = ? WHERE id = ?");
    return $stmt->execute([json_encode($sslConfig), $siteId]);
}

function getSSLConfig($db, $siteId) {
    $stmt = $db->prepare("SELECT ssl_config FROM sites WHERE id = ?");
    $stmt->execute([$siteId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['ssl_config']) {
        return json_decode($result['ssl_config'], true);
    }
    
    return null;
}

function generateTraefikSSLLabels($site, $sslConfig) {
    $containerName = $site['container_name'];
    $domain = $site['domain'];
    
    $labels = [
        "traefik.enable=true",
        "traefik.http.routers.{$containerName}.rule=Host(`{$domain}`)",
        "traefik.http.routers.{$containerName}.entrypoints=web",
        "traefik.http.services.{$containerName}.loadbalancer.server.port=80"
    ];
    
    if ($site['ssl'] && $sslConfig) {
        // Add HTTPS router
        $labels[] = "traefik.http.routers.{$containerName}-secure.rule=Host(`{$domain}`)";
        $labels[] = "traefik.http.routers.{$containerName}-secure.entrypoints=websecure";
        $labels[] = "traefik.http.routers.{$containerName}-secure.tls=true";
        $labels[] = "traefik.http.routers.{$containerName}-secure.tls.certresolver=letsencrypt";
        
        // Add HTTP to HTTPS redirect
        $labels[] = "traefik.http.routers.{$containerName}.middlewares=redirect-to-https";
        $labels[] = "traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https";
        $labels[] = "traefik.http.middlewares.redirect-to-https.redirectscheme.permanent=true";
    }
    
    return $labels;
}

function updateTraefikDNSConfig($dnsProvider, $credentials) {
    $envVars = [];
    
    switch ($dnsProvider) {
        case 'cloudflare':
            $envVars['CF_API_EMAIL'] = $credentials['cf_email'] ?? '';
            $envVars['CF_API_KEY'] = $credentials['cf_api_key'] ?? '';
            break;
            
        case 'route53':
            $envVars['AWS_ACCESS_KEY_ID'] = $credentials['aws_access_key'] ?? '';
            $envVars['AWS_SECRET_ACCESS_KEY'] = $credentials['aws_secret_key'] ?? '';
            $envVars['AWS_REGION'] = $credentials['aws_region'] ?? 'us-east-1';
            break;
            
        case 'digitalocean':
            $envVars['DO_AUTH_TOKEN'] = $credentials['do_auth_token'] ?? '';
            break;
            
        case 'gcp':
            $envVars['GCE_PROJECT'] = $credentials['gcp_project'] ?? '';
            $envVars['GCE_SERVICE_ACCOUNT_FILE'] = '/run/secrets/gcp-service-account.json';
            break;
            
        case 'azure':
            $envVars['AZURE_CLIENT_ID'] = $credentials['azure_client_id'] ?? '';
            $envVars['AZURE_CLIENT_SECRET'] = $credentials['azure_client_secret'] ?? '';
            $envVars['AZURE_TENANT_ID'] = $credentials['azure_tenant_id'] ?? '';
            $envVars['AZURE_SUBSCRIPTION_ID'] = $credentials['azure_subscription_id'] ?? '';
            break;
    }
    
    return $envVars;
}

function getTraefikDNSChallengeCommand($dnsProvider) {
    $providerMap = [
        'cloudflare' => 'cloudflare',
        'route53' => 'route53',
        'digitalocean' => 'digitalocean',
        'gcp' => 'gcloud',
        'azure' => 'azure',
        'namecheap' => 'namecheap',
        'godaddy' => 'godaddy'
    ];
    
    $provider = $providerMap[$dnsProvider] ?? $dnsProvider;
    
    return [
        "--certificatesresolvers.letsencrypt.acme.dnschallenge=true",
        "--certificatesresolvers.letsencrypt.acme.dnschallenge.provider={$provider}",
        "--certificatesresolvers.letsencrypt.acme.dnschallenge.delaybeforecheck=0"
    ];
}

function saveGlobalDNSProvider($dnsProvider, $credentials) {
    $configPath = '/app/data/dns-config.json';
    $config = [
        'provider' => $dnsProvider,
        'credentials' => $credentials,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
    
    // Update Traefik configuration
    updateTraefikForDNSChallenge($dnsProvider, $credentials);
    
    return true;
}

function getGlobalDNSProvider() {
    $configPath = '/app/data/dns-config.json';
    
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
        return $config;
    }
    
    return null;
}

function updateTraefikForDNSChallenge($dnsProvider, $credentials) {
    // This function would update the Traefik docker-compose.yml
    // For now, we'll create an environment file that can be loaded
    
    $envFile = '/app/data/traefik-dns.env';
    $envVars = updateTraefikDNSConfig($dnsProvider, $credentials);
    
    $envContent = "";
    foreach ($envVars as $key => $value) {
        $envContent .= "{$key}={$value}\n";
    }
    
    file_put_contents($envFile, $envContent);
    
    return true;
}
