# Production Quick Reference Card

## 🚀 Essential Commands

### Security Check
```bash
# Check security status (no changes)
sudo bash production-readiness-check.sh --dry-run

# Check and auto-fix issues
sudo bash production-readiness-check.sh

# Run security audit
sudo bash security-audit.sh
```

### Container Management
```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Restart specific service
docker-compose restart web-gui

# View logs
docker-compose logs -f web-gui
docker logs webbadeploy_gui --tail=50

# Check status
docker-compose ps
docker ps
```

### Firewall
```bash
# Enable firewall
sudo ufw enable

# Allow essential ports
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS

# Restrict dashboard to your IP
sudo ufw allow from YOUR_IP to any port 9000

# Check status
sudo ufw status verbose
```

### Permissions
```bash
# Production permissions
sudo chown -R www-data:www-data /opt/webbadeploy/data
sudo chown -R www-data:www-data /opt/webbadeploy/apps
sudo chmod 755 /opt/webbadeploy/data
sudo chmod 755 /opt/webbadeploy/apps
sudo chmod 664 /opt/webbadeploy/data/database.sqlite

# Docker socket (production)
sudo chmod 660 /var/run/docker.sock
```

### Database
```bash
# Access database
docker exec -it webbadeploy_gui sqlite3 /app/data/database.sqlite

# List tables
docker exec webbadeploy_gui sqlite3 /app/data/database.sqlite ".tables"

# Check users
docker exec webbadeploy_gui sqlite3 /app/data/database.sqlite "SELECT * FROM users;"

# Backup database
cp /opt/webbadeploy/data/database.sqlite /opt/webbadeploy/backups/database.sqlite.$(date +%Y%m%d)
```

### SSL/TLS
```bash
# Check certificates
ls -la /opt/webbadeploy/ssl/

# Fix acme.json permissions
sudo chmod 600 /opt/webbadeploy/ssl/acme.json

# Restart Traefik to renew certs
docker-compose restart traefik

# Check Traefik logs
docker logs webbadeploy_traefik --tail=50
```

### Updates
```bash
# Pull latest code
cd /opt/webbadeploy
git pull origin master

# Update containers
docker-compose pull
docker-compose up -d

# Check version
cat /opt/webbadeploy/VERSION
```

## 🔒 Security Checklist

### Before Production
- [ ] Run `production-readiness-check.sh`
- [ ] Change default database password
- [ ] Enable firewall (UFW)
- [ ] Restrict port 9000 access
- [ ] Configure SSL certificates
- [ ] Enable 2FA for admin
- [ ] Set strong passwords
- [ ] Configure backups

### Weekly Maintenance
- [ ] Check container status
- [ ] Review logs for errors
- [ ] Check disk space
- [ ] Run security audit

### Monthly Maintenance
- [ ] Update system packages
- [ ] Update Docker images
- [ ] Test backups
- [ ] Review user access
- [ ] Rotate credentials

## 🚨 Emergency Commands

### Reset Admin Password
```bash
sudo bash /opt/webbadeploy/reset-admin-password.sh
```

### Fix Permissions
```bash
sudo bash /opt/webbadeploy/fix-permissions-secure.sh
```

### Restart Everything
```bash
docker-compose down
docker-compose up -d
```

### View All Logs
```bash
docker-compose logs --tail=100
```

### Check System Resources
```bash
df -h                    # Disk space
free -h                  # Memory
docker stats --no-stream # Container resources
```

## 📊 Monitoring

### Check Container Health
```bash
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

### Check Logs for Errors
```bash
docker-compose logs --tail=100 | grep -i error
docker-compose logs --tail=100 | grep -i warning
```

### Check Disk Usage
```bash
df -h /opt/webbadeploy
du -sh /opt/webbadeploy/apps/*
du -sh /opt/webbadeploy/data
```

### Check Network
```bash
docker network ls
docker network inspect webbadeploy
```

## 🔐 Access Dashboard

### Via Direct Access (if port open)
```
http://YOUR_SERVER_IP:9000
```

### Via SSH Tunnel (recommended)
```bash
# On your local machine
ssh -L 9000:localhost:9000 user@your-server

# Then access
http://localhost:9000
```

### Via Traefik (if configured)
```
https://dashboard.yourdomain.com
```

## 📁 Important Paths

| Path | Description |
|------|-------------|
| `/opt/webbadeploy/` | Main installation directory |
| `/opt/webbadeploy/data/` | Database and application data |
| `/opt/webbadeploy/apps/` | Deployed applications |
| `/opt/webbadeploy/ssl/` | SSL certificates |
| `/opt/webbadeploy/backups/` | Backup files |
| `/opt/webbadeploy/logs/` | Log files |
| `/opt/webbadeploy/gui/` | Dashboard files |
| `/var/run/docker.sock` | Docker socket |

## 🔧 Troubleshooting

### Container Won't Start
```bash
docker logs CONTAINER_NAME
docker-compose up -d --force-recreate CONTAINER_NAME
```

### Permission Denied
```bash
sudo chown -R www-data:www-data /opt/webbadeploy/data
sudo chmod 755 /opt/webbadeploy/data
```

### Database Locked
```bash
docker-compose restart web-gui
```

### Port Already in Use
```bash
sudo netstat -tlnp | grep :9000
sudo kill -9 PID
```

### Out of Disk Space
```bash
# Clean Docker
docker system prune -a

# Clean logs
sudo truncate -s 0 /opt/webbadeploy/logs/*.log
```

## 📞 Support

- **Documentation**: `/opt/webbadeploy/README.md`
- **Security Guide**: `/opt/webbadeploy/PRODUCTION-DEPLOYMENT-GUIDE.md`
- **Security Checklist**: `/opt/webbadeploy/SECURITY-CHECKLIST.md`
- **Troubleshooting**: `/opt/webbadeploy/GITHUB-TROUBLESHOOTING.md`

---

**Quick Start for Production:**
```bash
sudo bash /opt/webbadeploy/production-readiness-check.sh
```

**Last Updated**: 2025-10-11
