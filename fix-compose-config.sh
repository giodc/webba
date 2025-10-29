#!/bin/bash

# Quick fix to manually import docker-compose.yml into database

echo "Importing docker-compose.yml into database..."

# Check if docker-compose.yml exists
if [ ! -f "/opt/wharftales/docker-compose.yml" ]; then
    echo "ERROR: docker-compose.yml not found!"
    exit 1
fi

# Import into database
docker exec wharftales_gui bash -c "
php << 'EOPHP'
<?php
require_once '/var/www/html/includes/functions.php';
require_once '/var/www/html/includes/auth.php';

\$db = initDatabase();

// Read docker-compose.yml
\$composeFile = '/opt/wharftales/docker-compose.yml';
if (!file_exists(\$composeFile)) {
    echo \"ERROR: docker-compose.yml not found at \$composeFile\n\";
    exit(1);
}

\$yaml = file_get_contents(\$composeFile);

// Check if config already exists
\$stmt = \$db->prepare(\"SELECT * FROM compose_configs WHERE config_type = 'main' LIMIT 1\");
\$stmt->execute();
\$existing = \$stmt->fetch(PDO::FETCH_ASSOC);

if (\$existing) {
    // Update existing
    \$stmt = \$db->prepare(\"UPDATE compose_configs SET compose_yaml = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?\");
    \$stmt->execute([\$yaml, \$existing['id']]);
    echo \"✓ Updated existing compose configuration\n\";
} else {
    // Insert new
    \$stmt = \$db->prepare(\"INSERT INTO compose_configs (config_type, compose_yaml, created_at, updated_at) VALUES ('main', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)\");
    \$stmt->execute([\$yaml]);
    echo \"✓ Created new compose configuration\n\";
}

echo \"✓ docker-compose.yml successfully imported into database\n\";
?>
EOPHP
"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Success! You can now save settings in the dashboard."
else
    echo ""
    echo "❌ Failed to import. Check the error above."
fi
