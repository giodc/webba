# Docker Volumes for Site Storage

WebBadeploy now uses **Docker volumes** instead of bind mounts for site data storage.

## Why Docker Volumes?

### Benefits

✅ **Better Performance**
- Native Docker storage driver
- Optimized for container I/O
- Especially faster on macOS/Windows (no filesystem translation)

✅ **No Permission Issues**
- Docker manages permissions automatically
- No need for `chown` or `chmod`
- Works consistently across different host systems

✅ **Easier Backups**
- Use `docker volume` commands
- Portable across hosts
- Simple backup/restore workflow

✅ **Automatic Cleanup**
- Volumes removed with `docker-compose down -v`
- No orphaned directories on host
- Clean uninstall process

✅ **Better Isolation**
- Site data isolated from host filesystem
- More secure
- Prevents accidental host file modifications

---

## How It Works

### Volume Naming Convention

Each site gets a dedicated Docker volume:
- **PHP sites**: `{container_name}_data`
- **Laravel sites**: `{container_name}_data`
- **WordPress sites**: `wp_{container_name}_data`

Example:
```
php_demo_1759272459_data
laravel_myapp_1759272500_data
wp_wordpress_myblog_1759272600_data
```

### Volume Configuration

```yaml
services:
  php_demo_1759272459:
    image: php:8.2-apache
    volumes:
      - php_demo_1759272459_data:/var/www/html

volumes:
  php_demo_1759272459_data:
```

---

## Managing Volumes

### List All Site Volumes

```bash
docker volume ls --filter "name=php_" --filter "name=laravel_" --filter "name=wordpress_"
```

Or use the helper script:
```bash
./scripts/manage-volumes.sh list
```

### Inspect Volume Details

```bash
docker volume inspect php_demo_1759272459_data
```

Or:
```bash
./scripts/manage-volumes.sh inspect php_demo_1759272459_data
```

### View Volume Contents

```bash
docker run --rm -v php_demo_1759272459_data:/data alpine ls -la /data
```

---

## Accessing Volume Data

### Method 1: Via Running Container

```bash
# Access files in running container
docker exec -it php_demo_1759272459 ls -la /var/www/html

# Edit a file
docker exec -it php_demo_1759272459 vi /var/www/html/index.php

# Copy file from container
docker cp php_demo_1759272459:/var/www/html/index.php ./index.php

# Copy file to container
docker cp ./index.php php_demo_1759272459:/var/www/html/
```

### Method 2: Via Temporary Container

```bash
# Copy files from volume to host
docker run --rm -v php_demo_1759272459_data:/data -v $(pwd):/backup alpine cp -r /data/. /backup/

# Copy files from host to volume
docker run --rm -v php_demo_1759272459_data:/data -v $(pwd):/src alpine cp -r /src/. /data/
```

### Method 3: Using Helper Script

```bash
# Copy from volume to host
./scripts/manage-volumes.sh copy php_demo_1759272459_data /tmp/mysite

# Upload from host to volume
./scripts/manage-volumes.sh upload /path/to/files php_demo_1759272459_data
```

---

## Backup & Restore

### Backup a Volume

```bash
# Manual backup
docker run --rm \
  -v php_demo_1759272459_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/php_demo_backup.tar.gz -C /data .
```

Or use the helper script:
```bash
./scripts/manage-volumes.sh backup php_demo_1759272459_data
# Creates: backups/php_demo_1759272459_YYYYMMDD_HHMMSS.tar.gz
```

### Restore a Volume

```bash
# Manual restore
docker volume create php_demo_1759272459_data
docker run --rm \
  -v php_demo_1759272459_data:/data \
  -v $(pwd):/backup \
  alpine tar xzf /backup/php_demo_backup.tar.gz -C /data
```

Or use the helper script:
```bash
./scripts/manage-volumes.sh restore backups/php_demo_1759272459_20250930_220000.tar.gz
```

---

## Development Workflow

### Deploying Your Application

#### Option 1: Copy Files After Deployment

```bash
# 1. Deploy site via WebBadeploy GUI
# 2. Copy your application files
docker cp /path/to/your/app/. php_demo_1759272459:/var/www/html/

# 3. Set permissions (if needed)
docker exec php_demo_1759272459 chown -R www-data:www-data /var/www/html
```

#### Option 2: Use Volume Upload Script

```bash
# 1. Deploy site via WebBadeploy GUI
# 2. Upload your files
./scripts/manage-volumes.sh upload /path/to/your/app php_demo_1759272459_data
```

#### Option 3: Git Clone Inside Container

```bash
# 1. Deploy site via WebBadeploy GUI
# 2. Install git and clone
docker exec php_demo_1759272459 sh -c "
  apt-get update && apt-get install -y git
  cd /var/www/html
  rm -f index.php
  git clone https://github.com/user/repo.git .
"
```

### Live Development

For active development, you can temporarily mount a local directory:

```bash
# Stop the site
docker-compose -f /opt/webbadeploy/apps/php/sites/php_demo_1759272459/docker-compose.yml down

# Edit docker-compose.yml to add bind mount:
volumes:
  - php_demo_1759272459_data:/var/www/html
  - /path/to/local/dev:/var/www/html  # Add this

# Restart
docker-compose -f /opt/webbadeploy/apps/php/sites/php_demo_1759272459/docker-compose.yml up -d
```

---

## Volume Maintenance

### Check Volume Sizes

```bash
./scripts/manage-volumes.sh size
```

Or manually:
```bash
docker system df -v
```

### Remove Unused Volumes

```bash
# Remove all unused volumes
docker volume prune

# Or use the helper script
./scripts/manage-volumes.sh clean
```

### Remove Specific Volume

```bash
# Stop the container first
docker stop php_demo_1759272459

# Remove volume
docker volume rm php_demo_1759272459_data
```

---

## Migration from Bind Mounts

If you have existing sites using bind mounts (`./html:/var/www/html`):

### Step 1: Backup Current Data

```bash
cd /opt/webbadeploy/apps/php/sites/OLD_SITE
tar czf ~/old_site_backup.tar.gz html/
```

### Step 2: Update docker-compose.yml

Change:
```yaml
volumes:
  - ./html:/var/www/html
```

To:
```yaml
volumes:
  - php_oldsite_data:/var/www/html

volumes:
  php_oldsite_data:
```

### Step 3: Restore Data to Volume

```bash
# Recreate container with volume
docker-compose up -d

# Restore data
docker run --rm \
  -v php_oldsite_data:/data \
  -v ~/old_site_backup.tar.gz:/backup.tar.gz \
  alpine tar xzf /backup.tar.gz -C /data --strip-components=1
```

---

## Troubleshooting

### Volume Not Found

```bash
# List all volumes
docker volume ls

# Create volume manually
docker volume create php_demo_1759272459_data
```

### Permission Denied

```bash
# Fix permissions inside container
docker exec php_demo_1759272459 chown -R www-data:www-data /var/www/html
docker exec php_demo_1759272459 chmod -R 755 /var/www/html
```

### Volume Full

```bash
# Check volume size
docker run --rm -v php_demo_1759272459_data:/data alpine du -sh /data

# Clean up old files
docker exec php_demo_1759272459 sh -c "cd /var/www/html && rm -rf cache/* logs/*"
```

### Can't Access Files

```bash
# Verify volume is mounted
docker inspect php_demo_1759272459 | grep -A 10 Mounts

# Check if files exist
docker exec php_demo_1759272459 ls -la /var/www/html
```

---

## Best Practices

1. **Regular Backups**
   - Backup volumes before major updates
   - Store backups off-server
   - Test restore process

2. **Volume Naming**
   - Use consistent naming convention
   - Include timestamp in container names
   - Makes identification easier

3. **Cleanup**
   - Remove unused volumes regularly
   - Monitor disk space
   - Use `docker system prune` carefully

4. **Security**
   - Don't store sensitive data in volumes
   - Use environment variables for secrets
   - Encrypt backups

5. **Development**
   - Use volumes for production
   - Use bind mounts for development
   - Keep them separate

---

## Advanced Usage

### Sharing Volumes Between Containers

```yaml
services:
  app:
    volumes:
      - shared_data:/var/www/html
  
  backup:
    volumes:
      - shared_data:/data:ro  # Read-only

volumes:
  shared_data:
```

### Volume Drivers

```yaml
volumes:
  php_demo_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /mnt/storage/php_demo
```

### External Volumes

```yaml
volumes:
  php_demo_data:
    external: true
    name: production_php_demo_data
```

---

## Helper Script Reference

```bash
# List all commands
./scripts/manage-volumes.sh help

# Common operations
./scripts/manage-volumes.sh list                    # List volumes
./scripts/manage-volumes.sh inspect VOLUME         # Inspect volume
./scripts/manage-volumes.sh backup VOLUME          # Backup volume
./scripts/manage-volumes.sh restore FILE           # Restore from backup
./scripts/manage-volumes.sh copy VOLUME DEST       # Copy to host
./scripts/manage-volumes.sh upload SRC VOLUME      # Upload to volume
./scripts/manage-volumes.sh size                   # Show sizes
./scripts/manage-volumes.sh clean                  # Remove unused
```

---

## Resources

- [Docker Volumes Documentation](https://docs.docker.com/storage/volumes/)
- [Docker Storage Best Practices](https://docs.docker.com/storage/)
- [Volume Backup Strategies](https://docs.docker.com/storage/volumes/#backup-restore-or-migrate-data-volumes)
