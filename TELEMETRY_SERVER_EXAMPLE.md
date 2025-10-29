# Telemetry Server Setup

## Overview
Simple endpoint to track WebbaDeploy installations anonymously.

## Server Requirements
- Any web server (Node.js, PHP, Python, etc.)
- Database (MySQL, PostgreSQL, or SQLite)
- HTTPS endpoint

## Database Schema

```sql
CREATE TABLE installations (
    id VARCHAR(36) PRIMARY KEY,
    version VARCHAR(20),
    php_version VARCHAR(20),
    site_count INT,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    total_pings INT DEFAULT 1
);
```

## Example Endpoint (PHP)

```php
<?php
// ping.php - Simple telemetry endpoint

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get JSON payload
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['installation_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Connect to database
$db = new PDO('mysql:host=localhost;dbname=telemetry', 'user', 'password');

// Check if installation exists
$stmt = $db->prepare("SELECT id FROM installations WHERE id = ?");
$stmt->execute([$data['installation_id']]);
$exists = $stmt->fetch();

if ($exists) {
    // Update existing installation
    $stmt = $db->prepare("
        UPDATE installations 
        SET version = ?, 
            php_version = ?, 
            site_count = ?, 
            last_ping = NOW(),
            total_pings = total_pings + 1
        WHERE id = ?
    ");
    $stmt->execute([
        $data['version'] ?? 'unknown',
        $data['php_version'] ?? 'unknown',
        $data['site_count'] ?? 0,
        $data['installation_id']
    ]);
} else {
    // Insert new installation
    $stmt = $db->prepare("
        INSERT INTO installations (id, version, php_version, site_count, first_seen, last_ping)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $data['installation_id'],
        $data['version'] ?? 'unknown',
        $data['php_version'] ?? 'unknown',
        $data['site_count'] ?? 0
    ]);
}

echo json_encode(['success' => true, 'message' => 'Ping received']);
?>
```

## Example Endpoint (Node.js/Express)

```javascript
const express = require('express');
const mysql = require('mysql2/promise');

const app = express();
app.use(express.json());

const pool = mysql.createPool({
    host: 'localhost',
    user: 'user',
    password: 'password',
    database: 'telemetry'
});

app.post('/ping', async (req, res) => {
    const { installation_id, version, php_version, site_count } = req.body;
    
    if (!installation_id) {
        return res.status(400).json({ success: false, error: 'Invalid data' });
    }
    
    try {
        // Check if exists
        const [rows] = await pool.query('SELECT id FROM installations WHERE id = ?', [installation_id]);
        
        if (rows.length > 0) {
            // Update
            await pool.query(`
                UPDATE installations 
                SET version = ?, php_version = ?, site_count = ?, 
                    last_ping = NOW(), total_pings = total_pings + 1
                WHERE id = ?
            `, [version, php_version, site_count, installation_id]);
        } else {
            // Insert
            await pool.query(`
                INSERT INTO installations (id, version, php_version, site_count)
                VALUES (?, ?, ?, ?)
            `, [installation_id, version, php_version, site_count]);
        }
        
        res.json({ success: true, message: 'Ping received' });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, error: 'Server error' });
    }
});

app.listen(3000, () => console.log('Telemetry server running on port 3000'));
```

## Dashboard Query Examples

```sql
-- Total active installations (pinged in last 30 days)
SELECT COUNT(*) as active_installations
FROM installations
WHERE last_ping > DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Version distribution
SELECT version, COUNT(*) as count
FROM installations
WHERE last_ping > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY version
ORDER BY count DESC;

-- Total sites across all installations
SELECT SUM(site_count) as total_sites
FROM installations
WHERE last_ping > DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Growth over time
SELECT DATE(first_seen) as date, COUNT(*) as new_installations
FROM installations
GROUP BY DATE(first_seen)
ORDER BY date DESC
LIMIT 30;
```

## Privacy Considerations

✅ **What we collect:**
- Anonymous UUID (not tied to user identity)
- Version numbers
- Site count (number)
- Timestamps

❌ **What we DON'T collect:**
- IP addresses (don't log them)
- Domain names
- User emails
- Server locations
- Any personal information

## Deployment

1. Deploy endpoint to your server (e.g., `https://telemetry.yourdomain.com/ping`)
2. Secure with HTTPS
3. Set up database
4. Configure WebbaDeploy with your endpoint URL
5. Monitor dashboard

## Rate Limiting

Consider adding rate limiting to prevent abuse:
- Max 1 ping per installation per hour
- Block suspicious patterns
