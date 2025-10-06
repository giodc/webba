# Quick Start Guide - New Features

## 🚀 Getting Started with User Management & 2FA

This guide will help you quickly set up and use the new features in Webbadeploy.

---

## 📋 Table of Contents

1. [First-Time Setup](#first-time-setup)
2. [User Management](#user-management)
3. [Two-Factor Authentication](#two-factor-authentication)
4. [Site Permissions](#site-permissions)
5. [Redis Configuration](#redis-configuration)

---

## First-Time Setup

### For New Installations

1. **Install Webbadeploy:**
   ```bash
   curl -fsSL https://raw.githubusercontent.com/giodc/webba/master/install-production.sh | sudo bash
   ```

2. **Access the Dashboard:**
   - Open `http://your-server-ip:3000`
   - You'll be redirected to the setup page

3. **Create Admin Account:**
   - Enter a username (min. 3 characters)
   - Enter a strong password (min. 8 characters)
   - Optionally add your email
   - Click "Create Admin Account"

4. **You're Done!** The migration has already run during installation.

### For Existing Installations

1. **Update Webbadeploy:**
   ```bash
   cd /opt/webbadeploy
   git pull origin master
   docker-compose down
   docker-compose up -d --build
   ```

2. **Run Migration:**
   ```bash
   docker exec webbadeploy_gui php /app/migrate-rbac-2fa.php
   ```

3. **Verify:**
   - Login to your dashboard
   - You should see a "Users" menu item (you're now an admin)
   - Your existing sites are still there and working

---

## User Management

### Creating Users (Admin Only)

1. **Navigate to Users:**
   - Click "Users" in the main navigation menu

2. **Add New User:**
   - Click the "Add User" button
   - Fill in the form:
     - **Username**: Unique username (min. 3 chars)
     - **Email**: Optional but recommended
     - **Password**: Min. 8 characters
     - **Role**: Choose Admin or User
     - **Can create sites**: Toggle to allow/deny site creation

3. **Click "Create User"**

### User Roles Explained

**Admin Role:**
- ✅ Can see and manage ALL sites
- ✅ Can create/edit/delete users
- ✅ Can grant site permissions to users
- ✅ Can access all system settings
- ✅ Can view audit logs

**User Role:**
- ✅ Can see only their own sites
- ✅ Can see sites they've been granted access to
- ✅ Can create sites (if enabled by admin)
- ❌ Cannot access user management
- ❌ Cannot see other users' sites (unless granted)

### Editing Users

1. Go to **Users** page
2. Click the **pencil icon** next to a user
3. Modify:
   - Email address
   - Role (Admin/User)
   - Site creation permission
4. Click "Save Changes"

### Deleting Users

1. Go to **Users** page
2. Click the **trash icon** next to a user
3. Confirm deletion
4. **Note:** You cannot delete the last admin user

---

## Two-Factor Authentication

### Enabling 2FA for Your Account

1. **Open 2FA Settings:**
   - Click your username in the top-right corner
   - Select "Two-Factor Auth"

2. **Scan QR Code:**
   - Open your authenticator app (Google Authenticator, Authy, etc.)
   - Scan the QR code displayed
   - Or manually enter the secret key

3. **Verify Setup:**
   - Enter the 6-digit code from your authenticator app
   - Click "Verify"

4. **Save Backup Codes:**
   - You'll receive 10 backup codes
   - **IMPORTANT:** Save these in a secure location
   - Each code can be used once
   - Example format: `A1B2C3D4`

5. **Done!** 2FA is now enabled

### Logging In with 2FA

1. Enter your username and password as usual
2. You'll be redirected to the 2FA verification page
3. Enter the 6-digit code from your authenticator app
4. Or use one of your backup codes
5. Click "Verify Code"

**Note:** The 2FA verification page times out after 5 minutes for security.

### Using Backup Codes

If you lose access to your authenticator app:
1. On the 2FA verification page
2. Enter one of your backup codes instead of the 6-digit code
3. The code will be consumed (can't be used again)
4. You'll be logged in

### Disabling 2FA

1. Click your username → "Two-Factor Auth"
2. Click "Disable 2FA"
3. Enter your password to confirm
4. Click "Confirm"

### Supported Authenticator Apps

- ✅ Google Authenticator (iOS/Android)
- ✅ Microsoft Authenticator (iOS/Android)
- ✅ Authy (iOS/Android/Desktop)
- ✅ 1Password (with TOTP support)
- ✅ Bitwarden (with TOTP support)
- ✅ Any TOTP-compatible app

---

## Site Permissions

### Granting Access to Sites (Admin Only)

1. **Navigate to Users:**
   - Click "Users" in the main menu

2. **Manage Permissions:**
   - Click the **key icon** next to a user
   - This opens the Site Permissions modal

3. **Grant Access:**
   - Select a site from the dropdown
   - Choose permission level:
     - **View**: Read-only access
     - **Edit**: Can modify settings
     - **Manage**: Full control
   - Click "Grant"

4. **View Current Permissions:**
   - All granted permissions are listed below
   - Click the **X** button to revoke access

### Permission Levels Explained

**View Permission:**
- Can see site information
- Can view site settings
- Cannot make changes
- Good for: Monitoring, reporting

**Edit Permission:**
- All View permissions
- Can modify site settings
- Can manage files
- Cannot delete site
- Good for: Developers, content managers

**Manage Permission:**
- All Edit permissions
- Can delete site
- Can manage SSL
- Can restart containers
- Good for: Site administrators

### How Users See Sites

**Admin Users:**
- See ALL sites in the system
- No restrictions

**Regular Users:**
- See only sites they own (created by them)
- See sites they've been granted access to
- Dashboard shows only their accessible sites

---

## Redis Configuration

### Enabling Redis for WordPress

1. **Edit Site:**
   - Click on a WordPress site
   - Go to the site settings

2. **Enable Redis:**
   - Toggle "Enable Redis Cache"
   - Redis host and port are shown
   - Click "Save"

3. **Verify:**
   - Redis container will be created automatically
   - WordPress will use Redis for object caching

### Enabling Redis for PHP Applications

1. **Edit Site:**
   - Click on a PHP site
   - Go to site settings

2. **Enable Redis:**
   - Toggle "Enable Redis Cache"
   - Note the Redis connection details:
     - Host: `{site-name}_redis`
     - Port: `6379`

3. **Configure Your App:**
   - Use the provided connection details in your PHP code
   - Example:
     ```php
     $redis = new Redis();
     $redis->connect('mysite_redis', 6379);
     ```

### Enabling Redis for Laravel Applications

1. **Edit Site:**
   - Click on a Laravel site
   - Go to site settings

2. **Enable Redis:**
   - Toggle "Enable Redis Cache"
   - Note the connection details

3. **Update .env:**
   - Laravel will automatically use Redis
   - Or manually update:
     ```
     REDIS_HOST={site-name}_redis
     REDIS_PORT=6379
     CACHE_DRIVER=redis
     SESSION_DRIVER=redis
     ```

### Viewing Redis Information

For sites without a database (or with Redis enabled):
- Redis connection information is displayed in site settings
- Shows: Host, Port, and connection status
- Available for WordPress, PHP, and Laravel sites

---

## 🔒 Security Best Practices

### For Admins

1. **Enable 2FA Immediately:**
   - Protect your admin account with 2FA
   - Store backup codes securely

2. **Create Separate Admin Accounts:**
   - Don't share admin credentials
   - Create individual admin accounts for each administrator

3. **Use Strong Passwords:**
   - Minimum 12 characters
   - Mix of uppercase, lowercase, numbers, symbols
   - Use a password manager

4. **Regular User Audits:**
   - Review user list monthly
   - Remove inactive users
   - Check site permissions

5. **Principle of Least Privilege:**
   - Give users only the permissions they need
   - Use "View" permission when possible
   - Limit "Manage" permission to trusted users

### For Users

1. **Enable 2FA:**
   - Even as a regular user, enable 2FA
   - Protects your sites from unauthorized access

2. **Secure Your Backup Codes:**
   - Print them and store in a safe place
   - Or use a password manager
   - Don't store them in plain text on your computer

3. **Use Strong Passwords:**
   - Don't reuse passwords from other services
   - Change password if you suspect compromise

4. **Log Out When Done:**
   - Especially on shared computers
   - Sessions expire automatically, but log out manually

---

## 🐛 Troubleshooting

### Can't See Users Menu

**Problem:** "Users" menu item doesn't appear

**Solution:**
- You need admin role
- Run migration: `docker exec webbadeploy_gui php /app/migrate-rbac-2fa.php`
- First user is automatically made admin

### 2FA Not Working

**Problem:** 2FA codes are rejected

**Solutions:**
1. **Check Time Sync:**
   - TOTP requires accurate time
   - Ensure your phone's time is synced
   - Check server time: `date`

2. **Use Backup Code:**
   - If authenticator is out of sync
   - Use one of your backup codes

3. **Disable and Re-enable:**
   - If you still have access, disable 2FA
   - Set it up again with a fresh QR code

### Lost Access to Authenticator

**Problem:** Lost phone or can't access authenticator app

**Solutions:**
1. **Use Backup Code:**
   - Enter one of your saved backup codes
   - This will log you in

2. **Admin Reset (if you're locked out):**
   ```bash
   # Disable 2FA via command line
   docker exec webbadeploy_gui php -r "
   require 'includes/auth.php';
   \$db = initAuthDatabase();
   \$stmt = \$db->prepare('UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE username = ?');
   \$stmt->execute(['your-username']);
   echo '2FA disabled for user';
   "
   ```

### User Can't Create Sites

**Problem:** User gets "Permission denied" when creating sites

**Solution:**
- Admin needs to enable "Can create sites" for that user
- Go to Users → Edit user → Check "Can create sites"

### User Can't See Their Sites

**Problem:** User's dashboard is empty

**Solutions:**
1. **Check Ownership:**
   - Sites created before migration might not have owner_id set
   - Admin can grant permission manually

2. **Grant Permission:**
   - Admin: Go to Users → Manage Permissions
   - Grant at least "View" permission to the site

### Migration Failed

**Problem:** Migration script errors

**Solution:**
```bash
# Check logs
docker logs webbadeploy_gui

# Try running migration manually
docker exec -it webbadeploy_gui bash
cd /app
php migrate-rbac-2fa.php

# Check database
sqlite3 /app/data/database.sqlite
.tables
.schema users
```

---

## 📊 Monitoring & Audit

### Viewing Audit Logs

Currently, audit logs are stored in the database:

```bash
# Access database
docker exec -it webbadeploy_gui bash
sqlite3 /app/data/database.sqlite

# View recent audit logs
SELECT datetime(created_at, 'localtime') as time, 
       action, 
       resource_type, 
       resource_id, 
       ip_address 
FROM audit_log 
ORDER BY created_at DESC 
LIMIT 20;
```

### Logged Events

- User login/logout
- 2FA enabled/disabled
- 2FA backup code used
- User created/updated/deleted
- Site permissions granted/revoked
- All admin actions

---

## 🎯 Common Use Cases

### Use Case 1: Agency Managing Client Sites

**Setup:**
1. Create admin account for agency owner
2. Create user accounts for each client
3. Grant each client "Manage" permission to their site only
4. Enable 2FA for admin account

**Result:**
- Clients can manage their own sites
- Clients can't see other clients' sites
- Agency admin can see and manage all sites

### Use Case 2: Development Team

**Setup:**
1. Create admin accounts for senior developers
2. Create user accounts for junior developers
3. Grant "Edit" permission to junior devs for specific sites
4. Enable 2FA for all team members

**Result:**
- Senior devs have full access
- Junior devs can work on assigned sites
- All actions are logged
- Secure with 2FA

### Use Case 3: Freelancer with Multiple Clients

**Setup:**
1. Use admin account for yourself
2. Create user accounts for clients who want access
3. Grant "View" permission so clients can monitor
4. Enable 2FA for your admin account

**Result:**
- You manage all sites
- Clients can view their site status
- Clients can't make changes
- Your account is protected with 2FA

---

## 🚀 Next Steps

1. **Set Up Your First User:**
   - Create a test user account
   - Try different permission levels
   - Test the user experience

2. **Enable 2FA:**
   - Protect your admin account
   - Test the login flow
   - Save your backup codes

3. **Organize Your Sites:**
   - Grant appropriate permissions
   - Set up team access if needed
   - Document who has access to what

4. **Regular Maintenance:**
   - Review users monthly
   - Check audit logs for suspicious activity
   - Update passwords periodically
   - Keep backup codes secure

---

## 📚 Additional Resources

- **Main Documentation:** `/opt/webbadeploy/README.md`
- **Implementation Details:** `/opt/webbadeploy/RBAC_2FA_IMPLEMENTATION.md`
- **Migration Script:** `/opt/webbadeploy/gui/migrate-rbac-2fa.php`

---

## 💬 Need Help?

If you encounter issues:

1. Check the troubleshooting section above
2. Review logs: `docker-compose logs -f web-gui`
3. Check GitHub issues
4. Open a new issue with:
   - Your setup (new/existing installation)
   - Error messages
   - Steps to reproduce

---

**Happy Deploying! 🎉**
