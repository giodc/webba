# Webbadeploy 🚀

> **Easy, secure, and optimized web application deployment platform**

Webbadeploy is an open-source platform that makes deploying WordPress, PHP, and Laravel applications as simple as clicking a button. Built with Docker for security and portability, it provides a clean web interface for managing multiple applications on a single Ubuntu server.

## ✨ Features

- **🎯 One-Click Deployment**: Deploy WordPress, PHP, and Laravel apps instantly
- **🔒 Security First**: Docker containerization with SSL via Let's Encrypt
- **⚡ WordPress Optimized**: High-performance WordPress setup with Redis, OPcache, and CDN-ready configuration
- **🌐 Domain Management**: Support for test domains and custom domains
- **📱 Clean Web UI**: Modern, responsive interface for easy management
- **🔧 Zero Configuration**: Works out of the box on Ubuntu servers

## 🚀 Quick Start

### Installation

1. **Download and run the installer** (requires root privileges):
```bash
curl -fsSL https://raw.githubusercontent.com/giodc/webba/master/install-production.sh | sudo bash
```

2. **Start Webbadeploy**:
```bash
cd /opt/webbadeploy
sudo -u webbadeploy docker-compose up -d
```

3. **Access the web interface**:
   - Open your browser and navigate to `http://your-server-ip:3000`
   - Complete the initial setup (create admin account)
   - Start deploying applications!

### First Application

1. Click "Deploy Your First App"
2. Choose application type (WordPress recommended for first time)
3. Enter a name and domain
4. Configure WordPress settings (if selected)
5. Enable SSL for custom domains
6. Click "Deploy Application"
7. Your app will be ready in under 60 seconds!

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Nginx Proxy   │────│  Web GUI (PHP)  │────│   MariaDB DB    │
│  (Port 80/443)  │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │              ┌────────▼────────┐             │
         │              │ Docker Socket   │             │
         │              │ (App Creation)  │             │
         │              └─────────────────┘             │
         │                                              │
         ▼                                              ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  WordPress App  │    │   PHP/Laravel   │    │   App Database  │
│  + Redis Cache  │    │      App        │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 📋 Supported Applications

### 🎯 WordPress (Highly Optimized)
- **Performance**: Redis object caching, OPcache, optimized PHP-FPM
- **Security**: Hardened configuration, security headers
- **Features**: Auto-install, plugin management, database optimization
- **CDN Ready**: Optimized for CDN integration

### 🐘 PHP Applications
- **Version**: PHP 8.2 with essential extensions
- **Features**: Apache web server, optimized configuration
- **Use Cases**: Custom PHP apps, frameworks, legacy applications

### 🔥 Laravel Applications
- **Performance**: Optimized PHP-FPM, OPcache, queue workers
- **Features**: Composer, Artisan commands, database migrations
- **Architecture**: Nginx + PHP-FPM with Supervisor

## 🔐 SSL Certificates

Webbadeploy automatically handles SSL certificates using Let's Encrypt:

- **Test Domains** (*.test.local): No SSL needed for local development
- **Custom Domains**: Automatic SSL certificate request and renewal
- **Requirements**: Domain must point to your server before SSL request

### SSL Certificate Management
- Certificates auto-renew every 90 days
- Manual renewal: `docker exec webbadeploy_nginx certbot renew`
- Certificate status visible in the web interface

## 🎛️ Management

### Web Interface Features
- **Dashboard**: Overview of all deployed applications
- **Status Monitoring**: Real-time container status updates
- **Domain Management**: Easy domain and SSL configuration
- **One-Click Actions**: Start, stop, delete applications

### Command Line Management
```bash
# View running containers
docker ps

# Check logs
docker-compose logs -f [service]

# Restart services
docker-compose restart

# Update Webbadeploy
git pull && docker-compose build --no-cache
```

## 📁 Directory Structure

```
/opt/webbadeploy/
├── docker-compose.yml          # Main orchestration
├── install.sh                  # Installation script
├── gui/                        # Web interface
│   ├── index.php              # Main dashboard
│   ├── api.php                # REST API
│   └── includes/functions.php  # Core functions
├── nginx/                      # Nginx configuration
│   ├── nginx.conf             # Main config
│   └── sites/                 # Site-specific configs
├── apps/                       # Application templates
│   ├── wordpress/             # WordPress optimized setup
│   ├── php/                   # PHP application setup
│   └── laravel/               # Laravel application setup
├── data/                       # SQLite database
├── ssl/                        # SSL certificates
└── logs/                       # Application logs
```

## 🔧 Configuration

### WordPress Performance Tweaks
- **OPcache**: Enabled with optimized settings
- **Redis**: Object caching for database query optimization
- **PHP-FPM**: Tuned for WordPress workloads
- **Nginx**: Gzip compression, browser caching, security headers
- **Database**: Optimized MySQL configuration

### Security Features
- **Container Isolation**: Each app runs in its own container
- **Security Headers**: XSS protection, CSRF protection
- **SSL/TLS**: Modern cipher suites, HSTS headers
- **Rate Limiting**: Built-in DDoS protection
- **File Permissions**: Proper ownership and permissions

## 📊 System Requirements

### Minimum Requirements
- **OS**: Ubuntu 20.04 LTS or newer
- **RAM**: 2GB (4GB recommended for multiple WordPress sites)
- **Storage**: 20GB available space
- **CPU**: 1 core (2 cores recommended)

### Recommended for Production
- **RAM**: 8GB+ for multiple high-traffic WordPress sites
- **Storage**: 100GB+ SSD
- **CPU**: 4+ cores
- **Network**: Static IP address for SSL certificates

## 🐛 Troubleshooting

### Common Issues

**Port already in use (80/443)**
```bash
sudo netstat -tulpn | grep :80
sudo systemctl stop apache2  # If Apache is running
```

**Docker permission denied**
```bash
sudo usermod -aG docker $USER
# Logout and login again
```

**SSL certificate failed**
- Ensure domain points to server IP
- Check firewall allows ports 80/443
- Verify DNS propagation: `nslookup yourdomain.com`

### Getting Help
- **Logs**: Check `docker-compose logs` for detailed error information
- **Status**: Use the web interface to monitor application status
- **Issues**: Report bugs on GitHub Issues page

## 📈 Performance Optimization

### WordPress Optimization
- Enable Redis caching in the web interface
- Use a CDN for static assets
- Optimize images before upload
- Regular database cleanup

### Server Optimization
```bash
# Increase file limits
echo "fs.file-max = 65535" >> /etc/sysctl.conf

# Optimize Docker
echo '{"log-driver": "json-file", "log-opts": {"max-size": "10m", "max-file": "3"}}' > /etc/docker/daemon.json

# Restart services
systemctl reload sysctl
systemctl restart docker
```

## 🤝 Contributing

We welcome contributions! Please see our contributing guidelines and feel free to submit pull requests.

### Development Setup
```bash
git clone https://github.com/your-repo/webbadeploy.git
cd webbadeploy
docker-compose up -d
```

## 📄 Traefik

http://localhost:8080/dashboard/#/http/routers
http://localhost:9090/metrics

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🙏 Acknowledgments

- Docker community for containerization best practices
- Let's Encrypt for free SSL certificates
- WordPress community for optimization techniques
- Bootstrap for the clean UI framework

---

**Made with ❤️ for developers who value simplicity and performance**

For support, questions, or feature requests, please open an issue on GitHub.