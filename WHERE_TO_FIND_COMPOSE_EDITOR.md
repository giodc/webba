# Where to Find the Docker Compose Editor

## 🎯 Quick Access Points

### 1. **Settings Page** (Main Traefik Config)
```
http://your-server-ip:9000/settings.php
```
- Scroll down to **"Advanced Configuration"** section
- Click **"Edit Main Traefik Config (YAML)"** button
- This edits the main `/opt/webbadeploy/docker-compose.yml`

### 2. **Site Edit Page** (Site-Specific Config)
```
http://your-server-ip:9000/edit-site.php?id=SITE_ID
```
- Look at the left sidebar navigation
- Find **"Docker Compose"** link (with external link icon)
- Click to open the editor in a new tab
- This edits the site's specific docker-compose.yml

### 3. **Direct URL**
```
# Main Traefik config
http://your-server-ip:9000/compose-editor.php

# Specific site config
http://your-server-ip:9000/compose-editor.php?site_id=SITE_ID
```

## 📍 Visual Guide

### Settings Page
```
┌─────────────────────────────────────────┐
│ System Settings                         │
├─────────────────────────────────────────┤
│                                         │
│ [Domain Configuration]                  │
│ [Dashboard Domain]                      │
│ [SSL Configuration]                     │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ Advanced Configuration              │ │
│ │                                     │ │
│ │ Edit raw docker-compose YAML        │ │
│ │ configurations for advanced         │ │
│ │ debugging and customization.        │ │
│ │                                     │ │
│ │ [Edit Main Traefik Config (YAML)]   │ │ ← HERE!
│ │                                     │ │
│ │ ⚠️ Warning: Only edit if you know   │ │
│ │ what you're doing...                │ │
│ └─────────────────────────────────────┘ │
│                                         │
└─────────────────────────────────────────┘
```

### Site Edit Page (Sidebar)
```
┌──────────────────┐
│ Navigation       │
├──────────────────┤
│ ⚡ Overview      │
│ ⚙️  Settings      │
│ 🌐 Domain & SSL  │
│ 📦 Container     │
│ 📁 Files         │
│ 📋 Logs          │
│ 🗄️  Database     │
│ ⚡ Redis Cache   │
│ 🔌 SFTP Access   │
│ 💾 Backup        │
│ 📄 Docker Compose│ ← HERE! (Admins only)
│ ⚠️  Danger Zone  │
└──────────────────┘
```

## 🔐 Permissions

- **Main Traefik Config**: Admin only
- **Site Config**: Admin only
- Regular users won't see these options

## ✨ Features

### In the Compose Editor:
- ✅ Edit raw YAML directly
- ✅ See last updated timestamp
- ✅ Save to database + regenerate file
- ✅ Reset button to discard changes
- ✅ Quick actions (Restart, View Logs)
- ✅ Warning before leaving with unsaved changes

### What Happens When You Save:
1. YAML is validated (basic check)
2. Saved to database
3. Physical file is regenerated from database
4. Success message shows file path
5. You can then restart the service to apply changes

## 🧪 Try It Now

1. **Go to Settings:**
   ```
   http://your-server-ip:9000/settings.php
   ```

2. **Scroll to "Advanced Configuration"**

3. **Click "Edit Main Traefik Config (YAML)"**

4. **You'll see the full docker-compose.yml in an editor**

5. **Make a small change (like a comment)**

6. **Click "Save Configuration"**

7. **Verify it saved successfully**

## 💡 Use Cases

### Debug SSL Issues
- Edit main Traefik config
- Check/modify Let's Encrypt settings
- Add custom Traefik commands

### Customize Site Containers
- Edit site-specific compose
- Add custom environment variables
- Modify port mappings
- Add custom volumes

### Quick Fixes
- Fix YAML syntax errors
- Update container images
- Modify network settings

## ⚠️ Important Notes

1. **Backup First**: Changes are immediate (no undo)
2. **Validate YAML**: Invalid YAML will break deployment
3. **Test Changes**: Restart services after editing
4. **Database is Source**: Files are regenerated from database

## 🔍 Troubleshooting

### Can't Find the Editor?
- Make sure you're logged in as **admin**
- Regular users don't have access
- Check the URL is correct

### Changes Not Applied?
- Save the configuration first
- Restart the affected service
- Check logs for errors

### Editor Shows Empty?
- Run migration script if you haven't:
  ```bash
  docker-compose exec web-gui php /var/www/html/migrate-compose-to-db.php
  ```

## 📚 Related Documentation

- `DATABASE_MIGRATION_COMPLETE.md` - Full migration details
- `DATABASE_COMPOSE_STORAGE_PROPOSAL.md` - Architecture explanation
- `DOCKER_COMPOSE_ACCESS_FIX.md` - Old file-based issues (now solved!)
