# Security Update Instructions

## Quick Fix for "Connection Refused" Error

The GUI container is trying to connect to `docker-proxy` which doesn't exist yet because your `docker-compose.yml` hasn't been updated with the security improvements.

### Option 1: Revert to Direct Socket Access (Quick Fix)

Temporarily revert the GUI Dockerfile to use direct socket access:

```bash
cd /opt/wharftales/gui
```

Edit `Dockerfile` and change these lines:

**FROM:**
```dockerfile
# Configure Apache to run on non-privileged port 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/:80/:8080/' /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

# Add www-data to docker group (use host's docker GID)
# Note: Docker socket access will be via docker-proxy, not direct mount
ARG DOCKER_GID=988
RUN groupadd -g ${DOCKER_GID} docker || groupmod -g ${DOCKER_GID} docker || true \
    && usermod -aG docker www-data

# Switch to non-root user
USER www-data:www-data

# Expose non-privileged port
EXPOSE 8080
```

**TO:**
```dockerfile
# Add www-data to docker group (use host's docker GID)
# The docker.sock will be mounted with proper permissions via docker-compose
ARG DOCKER_GID=988
RUN groupadd -g ${DOCKER_GID} docker || groupmod -g ${DOCKER_GID} docker || true \
    && usermod -aG docker www-data \
    && usermod -aG docker root

# Expose port
EXPOSE 80
```

Then rebuild:
```bash
cd /opt/wharftales
docker-compose build web-gui
docker-compose up -d web-gui
```

### Option 2: Apply Full Security Updates (Recommended)

Run these commands as root or with sudo:

```bash
cd /opt/wharftales

# 1. Backup current docker-compose.yml
sudo cp docker-compose.yml data/backups/docker-compose-backup-$(date +%Y%m%d).yml

# 2. Extract your current settings
EMAIL=$(grep -oP 'letsencrypt\.acme\.email=\K[^"]+' docker-compose.yml | head -1)
DOMAIN=$(grep -oP 'Host\(`\K[^`]+' docker-compose.yml | head -1)

echo "Your email: $EMAIL"
echo "Your domain: $DOMAIN"

# 3. Copy template to docker-compose.yml
sudo cp docker-compose.yml.template docker-compose.yml

# 4. Replace placeholders with your values
sudo sed -i "s/CHANGE_ME@example.com/$EMAIL/g" docker-compose.yml
sudo sed -i "s/CHANGE_ME.example.com/$DOMAIN/g" docker-compose.yml

# 5. Rebuild and restart
docker-compose build web-gui
docker-compose down
docker-compose up -d

# 6. Verify
docker ps | grep docker-proxy
docker exec wharftales_gui id
```

### Option 3: Manual Update (Most Control)

1. **Add docker-proxy service** to your `/opt/wharftales/docker-compose.yml`:

```yaml
services:
  docker-proxy:
    image: tecnativa/docker-socket-proxy
    container_name: wharftales_docker_proxy
    environment:
      - CONTAINERS=1
      - NETWORKS=1
      - SERVICES=1
      - IMAGES=1
      - INFO=1
      - VERSION=1
      - POST=1
      - BUILD=1
      - COMMIT=1
      - EXEC=1
      - VOLUMES=1
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
    networks:
      - wharftales
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
```

2. **Update web-gui service** in `docker-compose.yml`:

```yaml
  web-gui:
    # ... existing config ...
    ports:
      - "9000:8080"  # Change from 80 to 8080
    volumes:
      # REMOVE this line:
      # - /var/run/docker.sock:/var/run/docker.sock
      
      # KEEP these:
      - /usr/bin/docker:/usr/bin/docker:ro
      - /usr/local/bin/docker-compose:/usr/local/bin/docker-compose:ro
      # ... other volumes ...
    environment:
      - DB_PATH=/app/data/database.sqlite
      - DOCKER_HOST=tcp://docker-proxy:2375  # ADD THIS
    depends_on:
      - docker-proxy  # ADD THIS
    security_opt:
      - no-new-privileges:true  # ADD THIS
    cap_drop:
      - ALL  # ADD THIS
    cap_add:
      - CHOWN
      - SETUID
      - SETGID
      - DAC_OVERRIDE
    labels:
      # ... existing labels ...
      - traefik.http.services.webgui.loadbalancer.server.port=8080  # Change from 80
```

3. **Rebuild and restart:**

```bash
docker-compose build web-gui
docker-compose down
docker-compose up -d
```

## Verification

After applying updates, verify everything works:

```bash
# Check docker-proxy is running
docker ps | grep docker-proxy

# Check GUI is running as www-data
docker exec wharftales_gui id

# Check GUI can access Docker
docker exec wharftales_gui docker ps

# Check logs for errors
docker logs wharftales_gui
```

## Rollback

If something goes wrong:

```bash
# Restore backup
sudo cp data/backups/docker-compose-backup-YYYYMMDD.yml docker-compose.yml

# Rebuild original
docker-compose build web-gui
docker-compose up -d
```
