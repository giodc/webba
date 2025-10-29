# Password Reset Guide for WharfTales

This guide explains how to reset the admin password from the command line when you've forgotten it.

## Quick Reset (Recommended)

From the host machine (where Docker is running):

```bash
cd /opt/wharftales
./reset-admin-password.sh admin YourNewPassword123
```

Replace `YourNewPassword123` with your desired new password.

## Alternative Methods

### Method 1: Using the Shell Script

```bash
# Reset admin password
./reset-admin-password.sh admin MyNewPassword

# Reset a different user's password
./reset-admin-password.sh username MyNewPassword
```

### Method 2: Direct Docker Command

If the shell script doesn't work, you can run the PHP script directly:

```bash
# Find your container name
docker ps | grep wharftales

# Run the reset script (replace CONTAINER_NAME with actual name)
docker exec wharftales-gui-1 php /var/www/html/reset-password.php admin YourNewPassword
```

### Method 3: Inside the Container

If you're already inside the container:

```bash
# Enter the container
docker exec -it wharftales-gui-1 bash

# Run the reset script
cd /var/www/html
php reset-password.php admin YourNewPassword
```

### Method 4: Direct Database Access

For advanced users, you can directly modify the SQLite database:

```bash
# Enter the container
docker exec -it wharftales-gui-1 bash

# Access the database
sqlite3 /app/data/database.sqlite

# Generate a password hash (use PHP)
php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT) . PHP_EOL;"

# Update the password in SQLite
# (Copy the hash from above and use it in the query below)
UPDATE users SET password_hash = 'PASTE_HASH_HERE', failed_attempts = 0, locked_until = NULL WHERE username = 'admin';

# Exit SQLite
.quit
```

## Security Notes

1. **Use a strong password**: At least 8 characters with mixed case, numbers, and symbols
2. **Change immediately**: After resetting, log in and change to a memorable password
3. **2FA**: If you had 2FA enabled, it will remain enabled. You may need to disable it if you lost access to your authenticator app
4. **Account locks**: The reset script automatically unlocks your account if it was locked due to failed login attempts

## Troubleshooting

### Container Not Found

If you get "Could not find WharfTales GUI container":

```bash
# List all containers
docker ps -a

# Find the correct container name and use it directly
docker exec YOUR_CONTAINER_NAME php /var/www/html/reset-password.php admin NewPassword
```

### Permission Denied

If you get permission errors:

```bash
# Make the script executable
chmod +x /opt/wharftales/reset-admin-password.sh

# Or run with bash
bash /opt/wharftales/reset-admin-password.sh admin NewPassword
```

### Database Locked

If you get "database is locked":

```bash
# Stop the container temporarily
docker stop wharftales-gui-1

# Start it again
docker start wharftales-gui-1

# Try the reset again
./reset-admin-password.sh admin NewPassword
```

## Disabling 2FA (If Needed)

If you're locked out due to 2FA and lost your authenticator:

```bash
docker exec -it wharftales-gui-1 sqlite3 /app/data/database.sqlite

# Disable 2FA for admin user
UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE username = 'admin';
.quit
```

## Support

For more help, check the WharfTales documentation or open an issue on GitHub.
