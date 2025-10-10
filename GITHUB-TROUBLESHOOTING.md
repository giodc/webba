# GitHub Settings Not Showing? - Troubleshooting

## Quick Fix

### 1. **Hard Refresh Your Browser**
```
Windows/Linux: Ctrl + Shift + R or Ctrl + F5
Mac: Cmd + Shift + R
```

### 2. **Clear Browser Cache**
```
Windows/Linux: Ctrl + Shift + Delete
Mac: Cmd + Shift + Delete
```
- Select "Cached images and files"
- Click "Clear data"

### 3. **Check Site Type**
GitHub settings **only show for PHP and Laravel sites**, not WordPress!

- ✅ **PHP sites** - GitHub section will show
- ✅ **Laravel sites** - GitHub section will show
- ❌ **WordPress sites** - No GitHub section (not supported yet)

---

## How to See GitHub Settings

### Step 1: Edit a PHP or Laravel Site
1. Go to dashboard
2. Find a **PHP** or **Laravel** site
3. Click the site name or edit icon (pencil)

### Step 2: Scroll Down
1. You'll see sections in this order:
   - Site Information
   - Domain Configuration
   - Security & Status
   - **GitHub Deployment** ← Look for this!
   - Container Info

### Step 3: GitHub Section Should Show
```
GitHub Deployment
├── Repository: [input field]
├── Branch: [input field]
├── Personal Access Token: [password field]
└── [Check for Updates] [Pull Latest Changes] buttons (if repo configured)
```

---

## Verification Commands

### Check if files are in container:
```bash
docker exec webbadeploy_gui ls -la /var/www/html/includes/ | grep github
docker exec webbadeploy_gui ls -la /var/www/html/includes/ | grep encryption
```

### Check if database has GitHub fields:
```bash
docker exec webbadeploy_gui php -r "
require_once '/var/www/html/includes/functions.php';
\$db = initDatabase();
\$cols = \$db->query('PRAGMA table_info(sites)')->fetchAll(PDO::FETCH_ASSOC);
foreach(\$cols as \$col) {
    if(strpos(\$col['name'], 'github') !== false) {
        echo \$col['name'] . \"\n\";
    }
}"
```

**Expected output:**
```
github_repo
github_branch
github_token
github_last_commit
github_last_pull
```

### Check if HTML is in index.php:
```bash
docker exec webbadeploy_gui grep "editGithubSection" /var/www/html/index.php
```

**Expected:** Should find the div with id="editGithubSection"

### Check if JavaScript is loaded:
```bash
docker exec webbadeploy_gui grep "editGithubSection" /var/www/html/js/app.js
```

**Expected:** Should find the JavaScript code

---

## Still Not Showing?

### Option 1: Restart Docker Container
```bash
docker restart webbadeploy_gui
```

### Option 2: Force Rebuild
```bash
cd /opt/webbadeploy
docker-compose down
docker-compose up -d --build
```

### Option 3: Check Browser Console
1. Open browser DevTools (F12)
2. Go to Console tab
3. Look for errors
4. Should see: "Webbadeploy JS v5.2 loaded"

### Option 4: Test with Different Browser
Try opening in:
- Incognito/Private mode
- Different browser (Chrome, Firefox, Edge)

---

## What You Should See

### For PHP Site (ID: 133, 136, 140):
When you click edit, scroll down and you should see:

```
┌─────────────────────────────────────┐
│ 🔧 GitHub Deployment                │
├─────────────────────────────────────┤
│ Repository:                         │
│ [username/repo                    ] │
│ Leave empty to disable GitHub...    │
│                                     │
│ Branch:          Token:             │
│ [main         ]  [**********      ] │
└─────────────────────────────────────┘
```

### For Laravel Site (ID: 131, 134, 139, 141):
Same as above - GitHub section should appear!

### For WordPress Site (ID: 129, 132, 135, 137, 138):
**No GitHub section** - This is correct! WordPress doesn't support GitHub deployment yet.

---

## Test It Now!

1. **Go to dashboard**: http://your-server:9000
2. **Click on a Laravel site** (e.g., "tester" - ID 131)
3. **Scroll down** past "Security & Status"
4. **Look for "GitHub Deployment"** section

If you see it: ✅ **Working!**
If you don't: 🔄 **Hard refresh browser** (Ctrl+Shift+R)

---

## Example: Adding GitHub to Existing Site

1. Edit site "tester" (Laravel)
2. Scroll to GitHub Deployment
3. Enter: `laravel/laravel`
4. Branch: `main`
5. Token: (leave empty for public repo)
6. Click "Save Changes"
7. Done! Now you can pull from GitHub

---

## Summary

✅ **Files are installed** - Checked above  
✅ **Database is updated** - Checked above  
✅ **Code is correct** - Verified  
🔄 **Browser cache** - Most likely issue!  

**Solution: Hard refresh your browser!** (Ctrl+Shift+R)

---

**Still having issues?** Check the browser console (F12) for JavaScript errors.
