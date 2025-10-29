# Renamed from WharfTales to WharfTales

## ✅ Complete Technical Infrastructure Rename

All references have been updated from "WharfTales" to "WharfTales" throughout the codebase.

---

## 🔄 What Changed:

### **1. Container Names:**
```
wharftales_traefik  →  wharftales_traefik
wharftales_gui      →  wharftales_gui
wharftales_db       →  wharftales_db
```

### **2. Network Name:**
```
wharftales  →  wharftales
```

### **3. Installation Path:**
```
/opt/wharftales  →  /opt/wharftales
```

### **4. Database Credentials:**
```
MYSQL_DATABASE: wharftales  →  wharftales
MYSQL_USER: wharftales      →  wharftales
MYSQL_PASSWORD: wharftales_pass  →  wharftales_pass
MYSQL_ROOT_PASSWORD: wharftales_root_pass  →  wharftales_root_pass
```

### **5. System User:**
```
wharftales:wharftales  →  wharftales:wharftales
```

---

## 📁 Files Updated:

### **Core Infrastructure:**
- ✅ `docker-compose.yml.template` - Container names, network, DB credentials
- ✅ `install.sh` - All paths and container references
- ✅ `update.sh` - All paths and container references
- ✅ `safe-update.sh` - All paths and container references

### **GUI Application:**
- ✅ All PHP files in `/gui/` - Path references updated
- ✅ `gui/includes/auth.php` - Comments and branding
- ✅ `gui/includes/telemetry.php` - Endpoint URL
- ✅ `gui/login.php` - Title and branding
- ✅ `gui/index.php` - Page title
- ✅ `gui/settings.php` - Page title and messages
- ✅ `gui/users.php` - Page title
- ✅ `gui/edit-site.php` - Page title

### **Documentation:**
- ✅ `README.md` - All references
- ✅ `TELEMETRY_SERVER_EXAMPLE.md` - Paths and examples

---

## 🚀 Fresh Installation:

New installations will use:
```bash
# Installation directory
/opt/wharftales/

# Containers
wharftales_traefik
wharftales_gui
wharftales_db

# Network
wharftales

# Database
wharftales (database name)
wharftales (user)
```

---

## 🎯 User-Facing Changes:

**Login Page:**
```
WharfTales
Easy App Deployment Platform
```

**Page Titles:**
- Dashboard - WharfTales
- Settings - WharfTales
- User Management - WharfTales
- Edit Site - WharfTales

**Telemetry:**
- Endpoint: https://telemetry.wharftales.org/ping
- Message: "Help us improve WharfTales!"

---

## ⚠️ Important Notes:

1. **Existing Installations:** This rename is for fresh installations only. Existing installations will continue to work with old names.

2. **Migration Not Required:** Since you're in development, no migration is needed. Just use the new names for fresh installs.

3. **GitHub Repository:** The GitHub repo has been renamed to `giodc/wharftales` (was `giodc/wharftales`).

4. **Backward Compatibility:** Old documentation and guides may still reference "WharfTales" - these will be updated gradually.

---

## 🔧 Installation Command:

```bash
curl -fsSL https://raw.githubusercontent.com/giodc/wharftales/master/install.sh | sudo bash
```

The installer will now:
- Install to `/opt/wharftales/`
- Create containers with `wharftales_` prefix
- Use `wharftales` network
- Show "WharfTales" branding throughout

---

## ✨ Result:

Fresh installations will be fully branded as **WharfTales** with all technical infrastructure using the new naming convention!
