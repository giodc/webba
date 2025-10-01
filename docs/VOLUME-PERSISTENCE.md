# Docker Volume Persistence Guide

## **Yes, Volumes Are Permanent!**

Docker volumes persist **permanently** until you explicitly delete them. This is a key feature that makes volumes ideal for production data.

---

## **Volume Lifecycle**

### ✅ **Volumes Survive:**

| Action | Volume Status | Data Status |
|--------|---------------|-------------|
| `docker stop container` | ✅ Kept | ✅ Safe |
| `docker restart container` | ✅ Kept | ✅ Safe |
| `docker rm container` | ✅ Kept | ✅ Safe |
| `docker-compose down` | ✅ Kept | ✅ Safe |
| `docker-compose restart` | ✅ Kept | ✅ Safe |
| System reboot | ✅ Kept | ✅ Safe |
| Docker daemon restart | ✅ Kept | ✅ Safe |
| Container image update | ✅ Kept | ✅ Safe |
| Recreate container | ✅ Kept | ✅ Safe |

### ❌ **Volumes Are Deleted Only When:**

| Action | Volume Status | Data Status |
|--------|---------------|-------------|
| `docker volume rm volume_name` | ❌ Deleted | ❌ Lost |
| `docker-compose down -v` | ❌ Deleted | ❌ Lost |
| `docker volume prune` (if unused) | ❌ Deleted | ❌ Lost |
| Manual deletion | ❌ Deleted | ❌ Lost |

---

## **WebBadeploy Delete Behavior**

### **Current Implementation**

When you delete a site via the GUI, you now have **two options**:

#### **Option 1: Delete Everything (Default)**
```
DELETE /api.php?action=delete_site&id=123
```
- Stops container
- Removes container
- **Deletes volume and all data** ❌
- Removes database record

#### **Option 2: Keep Data**
```
DELETE /api.php?action=delete_site&id=123&keep_data=true
```
- Stops container
- Removes container
- **Preserves volume and data** ✅
- Removes database record
- Volume remains for backup/restore

---

## **Practical Examples**

### **Scenario 1: Update Container Image**

```bash
# Stop and remove container
docker stop php_demo_1759272459
docker rm php_demo_1759272459

# Pull new image
docker pull php:8.3-apache

# Recreate with new image (same volume)
docker run -d \
  --name php_demo_1759272459 \
  -v php_demo_1759272459_data:/var/www/html \
  php:8.3-apache

# ✅ All your data is still there!
```

### **Scenario 2: Move to Different Server**

```bash
# On old server - backup volume
docker run --rm \
  -v php_demo_1759272459_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/site-backup.tar.gz -C /data .

# Transfer backup to new server
scp site-backup.tar.gz user@newserver:/tmp/

# On new server - restore
docker volume create php_demo_1759272459_data
docker run --rm \
  -v php_demo_1759272459_data:/data \
  -v /tmp:/backup \
  alpine tar xzf /backup/site-backup.tar.gz -C /data

# Deploy container with existing volume
# ✅ Data restored!
```

### **Scenario 3: Accidental Container Deletion**

```bash
# Oops! Deleted container
docker rm -f php_demo_1759272459

# Check if volume still exists
docker volume ls | grep php_demo_1759272459_data
# ✅ Volume is still there!

# Recreate container with same volume
docker-compose up -d
# ✅ All data recovered!
```

---

## **Volume Storage Location**

Volumes are stored on the host filesystem:

### **Linux**
```
/var/lib/docker/volumes/php_demo_1759272459_data/_data/
```

### **macOS (Docker Desktop)**
```
~/Library/Containers/com.docker.docker/Data/vms/0/data/docker/volumes/
```

### **Windows (Docker Desktop)**
```
\\wsl$\docker-desktop-data\data\docker\volumes\
```

**Note:** Direct access to these paths is not recommended. Use Docker commands instead.

---

## **Best Practices**

### 1. **Regular Backups**

```bash
# Automated backup script
#!/bin/bash
VOLUME="php_demo_1759272459_data"
BACKUP_DIR="/backups"
DATE=$(date +%Y%m%d_%H%M%S)

docker run --rm \
  -v $VOLUME:/data \
  -v $BACKUP_DIR:/backup \
  alpine tar czf /backup/${VOLUME}_${DATE}.tar.gz -C /data .

# Keep only last 7 backups
ls -t $BACKUP_DIR/${VOLUME}_*.tar.gz | tail -n +8 | xargs rm -f
```

### 2. **Volume Naming Convention**

Use descriptive names that include:
- Application type
- Site name
- Timestamp (for uniqueness)

```
php_mysite_1759272459_data
laravel_api_1759272500_data
wordpress_blog_1759272600_data
```

### 3. **Monitor Volume Usage**

```bash
# Check all volumes
docker system df -v

# Check specific volume
docker run --rm -v php_demo_1759272459_data:/data alpine du -sh /data

# List large files
docker run --rm -v php_demo_1759272459_data:/data alpine \
  find /data -type f -size +10M -exec ls -lh {} \;
```

### 4. **Cleanup Strategy**

```bash
# List unused volumes
docker volume ls -f dangling=true

# Remove unused volumes (CAREFUL!)
docker volume prune

# Remove specific old volume
docker volume rm old_site_data
```

---

## **Data Retention Policy**

### **Recommended Approach**

1. **Active Sites**
   - Keep volumes indefinitely
   - Regular backups (daily/weekly)
   - Monitor disk usage

2. **Deleted Sites**
   - Keep volume for 30 days
   - Create final backup before deletion
   - Document deletion date

3. **Test/Dev Sites**
   - Delete volumes immediately
   - No backup needed
   - Clean up regularly

### **Implementation**

```bash
# Tag volumes with metadata
docker volume create \
  --label site=mysite \
  --label environment=production \
  --label created=$(date +%Y-%m-%d) \
  php_mysite_data

# Find volumes by label
docker volume ls --filter label=environment=production
```

---

## **Disaster Recovery**

### **Scenario: Complete Server Failure**

**Preparation:**
```bash
# Regular automated backups
0 2 * * * /opt/webbadeploy/scripts/backup-all-volumes.sh
```

**Recovery:**
```bash
# 1. Setup new server with WebBadeploy
# 2. Restore volumes from backups
for backup in /backups/*.tar.gz; do
  volume=$(basename $backup .tar.gz)
  docker volume create $volume
  docker run --rm \
    -v $volume:/data \
    -v /backups:/backup \
    alpine tar xzf /backup/$(basename $backup) -C /data
done

# 3. Restore database
# 4. Deploy containers
# ✅ Full recovery complete
```

---

## **FAQ**

### **Q: What happens if I run out of disk space?**

**A:** Docker will fail to write new data. Containers may crash. Solution:
```bash
# Check disk usage
df -h
docker system df

# Clean up
docker system prune -a
docker volume prune
```

### **Q: Can I move a volume to a different location?**

**A:** Yes, but requires backup/restore:
```bash
# Backup
docker run --rm -v old_volume:/data -v $(pwd):/backup alpine \
  tar czf /backup/data.tar.gz -C /data .

# Create new volume
docker volume create new_volume

# Restore
docker run --rm -v new_volume:/data -v $(pwd):/backup alpine \
  tar xzf /backup/data.tar.gz -C /data

# Delete old volume
docker volume rm old_volume
```

### **Q: Are volumes encrypted?**

**A:** No, by default. For encryption:
```yaml
volumes:
  encrypted_data:
    driver: local
    driver_opts:
      type: "nfs"
      o: "addr=encrypted-storage,rw"
      device: ":/path/to/encrypted"
```

### **Q: How do I share volumes between servers?**

**A:** Use network storage:
```yaml
volumes:
  shared_data:
    driver: local
    driver_opts:
      type: nfs
      o: addr=nfs-server,rw
      device: ":/path/to/share"
```

---

## **Monitoring & Alerts**

### **Setup Monitoring**

```bash
#!/bin/bash
# monitor-volumes.sh

THRESHOLD=80  # Alert at 80% full

for volume in $(docker volume ls -q); do
  usage=$(docker run --rm -v $volume:/data alpine \
    df /data | tail -1 | awk '{print $5}' | sed 's/%//')
  
  if [ $usage -gt $THRESHOLD ]; then
    echo "ALERT: Volume $volume is ${usage}% full"
    # Send notification (email, Slack, etc.)
  fi
done
```

### **Cron Job**

```bash
# Check volumes every hour
0 * * * * /opt/webbadeploy/scripts/monitor-volumes.sh
```

---

## **Summary**

✅ **Volumes are permanent** - They persist until explicitly deleted
✅ **Survive container operations** - Stop, restart, remove, update
✅ **Survive system reboots** - Data is safe
✅ **Require explicit deletion** - Won't disappear accidentally
✅ **WebBadeploy has options** - Keep or delete data when removing sites
✅ **Easy to backup** - Simple tar commands
✅ **Production-ready** - Industry standard approach

**Your data is safe with Docker volumes!** 🎉
