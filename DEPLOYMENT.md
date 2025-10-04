# Webbadeploy Deployment Guide

## Quick Install (Fresh Ubuntu Server)

### One-Line Installation

```bash
curl -fsSL https://raw.githubusercontent.com/yourrepo/webbadeploy/main/install-production.sh | sudo bash
```

Or download and run:

```bash
wget https://raw.githubusercontent.com/yourrepo/webbadeploy/main/install-production.sh
chmod +x install-production.sh
sudo ./install-production.sh
```

## System Requirements

### Minimum Requirements
- **OS**: Ubuntu 20.04+, Debian 11+, or similar
- **RAM**: 1GB (2GB+ recommended)
- **Disk**: 10GB free space
- **CPU**: 1 core (2+ recommended)

### Recommended for Production
- **RAM**: 4GB+
- **Disk**: 50GB+ SSD
- **CPU**: 2+ cores
- **Network**: Static IP or domain name

## Manual Installation

### 1. Install Docker

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com | sudo bash

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

### 2. Clone/Download Webbadeploy

```bash
# Create directory
sudo mkdir -p /opt/webbadeploy
cd /opt/webbadeploy

# Download files (or git clone if available)
# Copy all Webbadeploy files here
```

### 3. Start Services

```bash
cd /opt/webbadeploy
sudo docker-compose up -d
```

### 4. Configure Firewall

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw allow 8080/tcp  # Traefik Dashboard
sudo ufw allow 2222:2299/tcp  # SFTP Range
sudo ufw enable
```

## Post-Installation

### Access Dashboard
- Main Dashboard: `http://YOUR_SERVER_IP`
- Traefik Dashboard: `http://YOUR_SERVER_IP:8080`

### First Steps
1. Deploy your first application
2. Configure domain DNS (if using custom domains)
3. Enable SSL for production sites
4. Set up SFTP access for file management

## Directory Structure

```
/opt/webbadeploy/
├── docker-compose.yml       # Main compose file
├── gui/                     # Web interface
│   ├── Dockerfile
│   ├── index.php
│   ├── api.php
│   ├── edit-site.php
│   ├── includes/
│   └── js/
├── data/                    # Database (SQLite)
├── apps/                    # Deployed applications
│   ├── php/sites/
│   ├── laravel/sites/
│   └── wordpress/sites/
└── ssl/                     # SSL certificates
```

## Configuration

### Environment Variables

Edit `docker-compose.yml` to customize:

```yaml
environment:
  - MYSQL_ROOT_PASSWORD=your_secure_password
  - MYSQL_DATABASE=webbadeploy
```

### Ports

Default ports:
- `80` - HTTP
- `443` - HTTPS
- `8080` - Traefik Dashboard
- `2222+` - SFTP (auto-assigned per site)

## Backup & Restore

### Backup

```bash
# Backup database
sudo cp -r /opt/webbadeploy/data /backup/webbadeploy-data-$(date +%Y%m%d)

# Backup site files
sudo cp -r /opt/webbadeploy/apps /backup/webbadeploy-apps-$(date +%Y%m%d)

# Backup SSL certificates
sudo cp -r /opt/webbadeploy/ssl /backup/webbadeploy-ssl-$(date +%Y%m%d)
```

### Restore

```bash
# Stop services
cd /opt/webbadeploy
sudo docker-compose down

# Restore files
sudo cp -r /backup/webbadeploy-data-YYYYMMDD /opt/webbadeploy/data
sudo cp -r /backup/webbadeploy-apps-YYYYMMDD /opt/webbadeploy/apps
sudo cp -r /backup/webbadeploy-ssl-YYYYMMDD /opt/webbadeploy/ssl

# Start services
sudo docker-compose up -d
```

## Maintenance

### View Logs

```bash
cd /opt/webbadeploy
sudo docker-compose logs -f
```

### Restart Services

```bash
cd /opt/webbadeploy
sudo docker-compose restart
```

### Update Webbadeploy

```bash
cd /opt/webbadeploy
sudo docker-compose pull
sudo docker-compose up -d
```

### Stop Services

```bash
cd /opt/webbadeploy
sudo docker-compose down
```

## Troubleshooting

### Services won't start

```bash
# Check logs
sudo docker-compose logs

# Check Docker status
sudo systemctl status docker

# Restart Docker
sudo systemctl restart docker
```

### Permission issues

```bash
# Fix permissions
sudo chmod -R 777 /opt/webbadeploy/apps
sudo chmod -R 777 /opt/webbadeploy/data
```

### Port conflicts

```bash
# Check what's using port 80
sudo lsof -i :80

# Stop conflicting service
sudo systemctl stop apache2  # or nginx
```

## Security Recommendations

### Production Checklist

- [ ] Change default database passwords
- [ ] Enable firewall (UFW)
- [ ] Set up SSL certificates
- [ ] Regular backups
- [ ] Keep Docker updated
- [ ] Monitor logs
- [ ] Use strong SFTP passwords
- [ ] Restrict Traefik dashboard access

### Secure Traefik Dashboard

Edit `docker-compose.yml`:

```yaml
command:
  - "--api.insecure=false"  # Disable insecure API
  - "--api.dashboard=true"
  # Add authentication
```

## Support

- Documentation: https://github.com/yourrepo/webbadeploy
- Issues: https://github.com/yourrepo/webbadeploy/issues
- Community: https://discord.gg/yourserver

## License

MIT License - See LICENSE file for details
