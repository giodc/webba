# GitHub Deployment Feature ✅

## Overview

Deploy PHP and Laravel applications directly from GitHub repositories - public or private!

---

## Features

### ✅ Simple Deployment
- Enter GitHub repository URL
- Optionally provide branch name (default: `main`)
- For private repos: add Personal Access Token
- **Tokens are encrypted** with AES-256-GCM

### ✅ Supported Formats
- `https://github.com/username/repo`
- `github.com/username/repo`
- `username/repo`

### ✅ Automatic Setup
- Git installed automatically in container
- Repository cloned on first deployment
- Composer dependencies installed (if composer.json exists)
- Proper file permissions set

### ✅ Security
- **Tokens encrypted at rest** using AES-256-GCM
- Tokens never logged or exposed
- Decrypted only when needed
- See `SECURITY-GITHUB-TOKENS.md` for details

---

## How to Use

### Creating a New Site with GitHub

1. **Click "Deploy New Application"**
2. **Choose type**: PHP or Laravel
3. **Fill in basic info**: Name, Domain, etc.
4. **Scroll to "GitHub Deployment (Optional)"**
5. **Enter repository**:
   - Public repo: `username/repo`
   - Private repo: `username/repo` + token
6. **Optional**: Change branch (default: `main`)
7. **Click "Deploy Application"**

### For Private Repositories

1. **Generate GitHub Token**:
   - Go to [GitHub Settings → Tokens](https://github.com/settings/tokens)
   - Click "Generate new token" → "Fine-grained token"
   - Set permissions: `Contents: Read-only`
   - Copy token

2. **Enter in WharfTales**:
   - Paste token in "Personal Access Token" field
   - Token will be **encrypted automatically**
   - Never stored in plain text

---

## What Happens During Deployment

### First Deployment
1. Container created and started
2. Git installed (if not present)
3. Repository cloned to `/var/www/html`
4. Composer install (if `composer.json` exists)
5. File permissions set
6. Site ready!

### Subsequent Updates
1. Git pull latest changes
2. Composer install (if needed)
3. Files updated
4. Site refreshed!

---

## Database Schema

### New Fields Added to `sites` Table

```sql
github_repo TEXT                -- Repository URL or username/repo
github_branch TEXT DEFAULT 'main'  -- Branch to deploy
github_token TEXT               -- Encrypted token (AES-256-GCM)
github_last_commit TEXT         -- Last deployed commit hash
github_last_pull DATETIME       -- Last deployment timestamp
deployment_method TEXT DEFAULT 'manual'  -- 'github' or 'manual'
```

---

## Files Created/Modified

### New Files
- ✅ `gui/includes/encryption.php` - AES-256-GCM encryption
- ✅ `gui/includes/github-deploy.php` - GitHub deployment functions
- ✅ `gui/migrations/add_github_fields.php` - Database migration
- ✅ `SECURITY-GITHUB-TOKENS.md` - Security documentation
- ✅ `GITHUB-DEPLOYMENT-FEATURE.md` - This file

### Modified Files
- ✅ `gui/index.php` - Added GitHub fields to forms
- ✅ `gui/js/app.js` - Show/hide GitHub options
- ✅ `gui/api.php` - GitHub deployment integration
- ✅ `gui/includes/functions.php` - Store GitHub info, encrypt tokens

---

## API Functions

### Deployment Functions

#### `deployFromGitHub($site, $containerName)`
Clones or pulls repository from GitHub
- Installs git if needed
- Clones on first run
- Pulls updates on subsequent runs
- Returns commit hash

#### `checkGitHubUpdates($site, $containerName)`
Checks if updates are available
- Compares local vs remote commits
- Returns update status
- Used for "Check for Updates" feature

#### `runComposerInstall($containerName)`
Installs Composer dependencies
- Checks for composer.json
- Installs Composer if needed
- Runs `composer install --no-dev --optimize-autoloader`

### Encryption Functions

#### `encryptGitHubToken($token)`
Encrypts token using AES-256-GCM
- 256-bit key
- Random 12-byte nonce
- 16-byte authentication tag
- Returns base64-encoded encrypted data

#### `decryptGitHubToken($encryptedToken)`
Decrypts token for use
- Validates authentication tag
- Returns plain text token
- Used only during deployment

#### `maskSensitiveData($data, $showChars = 4)`
Masks token for display
- Shows first/last 4 characters
- Middle replaced with asterisks
- Example: `ghp_****...****wxyz`

---

## Usage Examples

### Public Repository
```
Repository: laravel/laravel
Branch: main
Token: (leave empty)
```

### Private Repository
```
Repository: mycompany/private-app
Branch: develop
Token: ghp_abc123...xyz789
```

### Full URL
```
Repository: https://github.com/username/repo
Branch: main
Token: (if private)
```

---

## Composer Support

### Automatic Installation
If `composer.json` is detected:
1. Composer installed automatically
2. Dependencies installed with `composer install`
3. Optimized autoloader generated
4. Production-ready setup

### Laravel Projects
For Laravel apps:
1. Clone repository
2. Run `composer install`
3. Set up `.env` file (manual or automated)
4. Run `php artisan key:generate` (manual)
5. Run migrations (manual)

---

## Security Features

### ✅ Token Encryption
- **AES-256-GCM** encryption
- Unique encryption key per installation
- Key stored securely in database
- Tokens never in plain text

### ✅ No Exposure
- Tokens never logged
- Not in API responses
- Not visible in UI
- Git URLs sanitized before logging

### ✅ Minimal Permissions
- Recommend fine-grained tokens
- Only `Contents: Read` needed
- Repository-specific access
- Time-limited tokens

---

## Future Enhancements

### Planned Features
- [ ] **Update checking** - Check for new commits
- [ ] **One-click updates** - Pull latest changes from UI
- [ ] **Webhook deployments** - Auto-deploy on push
- [ ] **SSH key support** - More secure than tokens
- [ ] **Deploy keys** - Per-repository read-only keys
- [ ] **Build scripts** - Run custom commands after deployment
- [ ] **Environment variables** - Manage `.env` from UI
- [ ] **Rollback** - Revert to previous commit

---

## Troubleshooting

### Repository Not Cloning?

**Check:**
1. Repository URL is correct
2. Token has access (for private repos)
3. Token permissions include `Contents: Read`
4. Branch name is correct

**View logs:**
```bash
docker logs <container_name>
```

### Composer Install Failing?

**Common issues:**
1. Missing PHP extensions
2. Memory limit too low
3. Composer version incompatibility

**Solution:**
```bash
# Check container logs
docker exec <container_name> composer install --verbose
```

### Token Not Working?

**Verify:**
1. Token not expired
2. Token has repository access
3. Organization permissions correct
4. Token type is "Fine-grained" or "Classic"

---

## Example Workflow

### Deploy Laravel from GitHub

1. **Create new site**:
   - Type: Laravel
   - Name: My Laravel App
   - Domain: myapp.test.local

2. **GitHub settings**:
   - Repository: `username/laravel-project`
   - Branch: `main`
   - Token: `ghp_...` (encrypted automatically)

3. **Database**:
   - MySQL/MariaDB selected
   - Credentials auto-generated

4. **Deploy**:
   - Container created
   - Repository cloned
   - Composer dependencies installed
   - Database ready

5. **Manual steps** (for now):
   - Copy `.env.example` to `.env`
   - Update database credentials
   - Run `php artisan key:generate`
   - Run `php artisan migrate`

6. **Done!** Visit `http://myapp.test.local`

---

## Comparison: GitHub vs SFTP

| Feature | GitHub | SFTP |
|---------|--------|------|
| **Setup** | Enter repo URL | Upload files manually |
| **Updates** | Git pull | Re-upload files |
| **Version Control** | Yes (Git) | No |
| **Rollback** | Easy (git checkout) | Manual backup needed |
| **Team Collaboration** | Yes | Limited |
| **CI/CD Ready** | Yes | No |
| **Security** | Token encrypted | Password hashed |

**Recommendation:** Use GitHub for version-controlled projects, SFTP for quick uploads.

---

## Summary

✅ **Simple**: Just enter repository URL  
✅ **Secure**: Tokens encrypted with AES-256-GCM  
✅ **Automatic**: Git + Composer installed automatically  
✅ **Flexible**: Public or private repositories  
✅ **Fast**: Clone once, pull updates  
✅ **Safe**: No plain text tokens, ever  

**Deploy from GitHub in seconds!** 🚀

---

**Ready to test?** Create a new PHP or Laravel site with GitHub deployment!
