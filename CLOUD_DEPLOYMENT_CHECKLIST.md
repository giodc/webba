# Cloud Deployment Checklist

## Pre-Deployment Verification ✅

All critical issues have been resolved and the system is ready for cloud deployment.

### Issues Fixed
- ✅ Dashboard authentication system (REQUEST_URI undefined error)
- ✅ Let's Encrypt email update permissions
- ✅ Install script updated with proper permissions
- ✅ Permission fix script available

---

## Deployment Steps

### 1. Prepare Cloud Server
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y git docker.io docker-compose curl ufw

# Enable Docker
sudo systemctl enable docker
sudo systemctl start docker
```

### 2. Clone Repository
```bash
cd /opt
sudo git clone https://github.com/yourusername/wharftales.git
cd wharftales
```

### 3. Run Installation Script
```bash
sudo chmod +x install-production.sh
sudo ./install-production.sh
```

The install script now automatically:
- Sets up Docker permissions
- Configures docker-compose.yml with correct ownership
- Starts all services
- Configures firewall

### 4. Configure DNS (if using custom domains)
Point your domain's A record to your server's IP address:
```
A    @           YOUR_SERVER_IP
A    *.yourdomain.com   YOUR_SERVER_IP  (for wildcard)
```

### 5. Update Let's Encrypt Email
1. Access dashboard at `http://YOUR_SERVER_IP:9000`
2. Login with default credentials
3. Go to Settings → SSL Configuration
4. Update email address
5. Restart Traefik when prompted

### 6. Security Hardening

#### Change Default Passwords
```bash
# Access the dashboard and change admin password immediately
```

#### Configure Firewall
```bash
# The install script configures UFW, verify it:
sudo ufw status

# Should show:
# - 22/tcp (SSH)
# - 80/tcp (HTTP)
# - 443/tcp (HTTPS)
# - 9000/tcp (Dashboard)
# - 2222:2299/tcp (SFTP Range)
```

#### Optional: Change Dashboard Port
Edit `/opt/wharftales/docker-compose.yml`:
```yaml
web-gui:
  ports:
    - "3000:80"  # Change 9000 to your preferred port
```

Then restart:
```bash
cd /opt/wharftales
sudo docker-compose up -d
```

---

## Post-Deployment Verification

### Check Services Status
```bash
cd /opt/wharftales
docker-compose ps
```

All services should show "Up":
- wharftales_gui
- wharftales_traefik
- wharftales_db

### Test Dashboard Access
```bash
curl -I http://localhost:9000/
# Should return: HTTP/1.1 302 Found (redirect to login)
```

### Check Logs
```bash
# View all logs
docker-compose logs -f

# View specific service
docker-compose logs -f web-gui
docker-compose logs -f traefik
```

### Test SSL Certificate Generation
1. Create a test site with SSL enabled
2. Ensure domain points to server
3. Wait for Let's Encrypt to issue certificate (1-2 minutes)
4. Verify HTTPS works

---

## Troubleshooting

### Dashboard Not Loading
```bash
# Check if container is running
docker ps | grep wharftales_gui

# Check logs
docker logs wharftales_gui

# Restart if needed
cd /opt/wharftales
docker-compose restart web-gui
```

### Permission Issues
```bash
# Run the fix script
cd /opt/wharftales
sudo ./fix-docker-permissions.sh
```

### SSL Not Working
```bash
# Check Traefik logs
docker logs wharftales_traefik

# Verify ports 80 and 443 are accessible
sudo ufw status
curl -I http://yourdomain.com
```

### Can't Update Settings
```bash
# Verify docker-compose.yml permissions
ls -la /opt/wharftales/docker-compose.yml
# Should show: -rw-rw-r-- 1 www-data www-data

# Fix if needed
sudo chown www-data:www-data /opt/wharftales/docker-compose.yml
sudo chmod 664 /opt/wharftales/docker-compose.yml
```

---

## Maintenance Commands

### Update WharfTales
```bash
cd /opt/wharftales
git pull
docker-compose pull
docker-compose up -d --build
```

### Backup Database
```bash
cd /opt/wharftales
cp data/database.sqlite data/database.sqlite.backup.$(date +%Y%m%d)
```

### View Resource Usage
```bash
docker stats
```

### Restart All Services
```bash
cd /opt/wharftales
docker-compose restart
```

### Stop All Services
```bash
cd /opt/wharftales
docker-compose down
```

### Start All Services
```bash
cd /opt/wharftales
docker-compose up -d
```

---

## Support

- Documentation: `/opt/wharftales/docs/`
- Fixes Applied: `/opt/wharftales/FIXES_APPLIED.md`
- Quick Start: `/opt/wharftales/QUICK_START_UPDATES.md`

---

**System Status**: ✅ Ready for Production Deployment
**Last Updated**: October 4, 2025
