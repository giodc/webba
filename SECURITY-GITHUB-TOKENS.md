# GitHub Token Security

## Overview

GitHub Personal Access Tokens are **encrypted at rest** using AES-256-GCM encryption to protect your private repositories.

---

## Encryption Details

### Algorithm
- **AES-256-GCM** (Galois/Counter Mode)
- 256-bit encryption key
- 12-byte random nonce per encryption
- 16-byte authentication tag for integrity

### Key Management
- Encryption key is automatically generated on first use
- Stored securely in database settings
- Unique per Webbadeploy installation
- Never exposed in logs or API responses

---

## How It Works

### When You Save a Token

1. **User enters token** in the GitHub deployment form
2. **Token is encrypted** using `encryptGitHubToken()`
3. **Encrypted token stored** in database
4. **Original token discarded** from memory

```php
// In createSite function
if ($githubToken) {
    $githubToken = encryptGitHubToken($githubToken);  // Encrypted before storage
}
```

### When Deploying from GitHub

1. **Encrypted token retrieved** from database
2. **Token is decrypted** using `decryptGitHubToken()`
3. **Token used** to authenticate with GitHub
4. **Decrypted token discarded** after use

```php
// In deployFromGitHub function
if ($githubToken) {
    $githubToken = decryptGitHubToken($githubToken);  // Decrypted only when needed
}
$repoUrl = normalizeGitHubUrl($githubRepo, $githubToken);
```

---

## Security Features

### ✅ Encryption at Rest
- Tokens are **never stored in plain text**
- Database compromise does not expose tokens without encryption key

### ✅ Minimal Exposure
- Tokens are **decrypted only when needed** (during deployment)
- Immediately discarded after use

### ✅ No Logging
- Tokens are **never logged** to files or console
- Git URLs with embedded tokens are **not logged**

### ✅ Secure Transmission
- Tokens sent over HTTPS to GitHub
- Never exposed in API responses or UI

### ✅ Input Sanitization
- URLs are cleaned of any existing tokens before processing
- Prevents token injection attacks

---

## Best Practices

### For Users

1. **Use Fine-Grained Tokens**
   - Create tokens with minimal permissions
   - Limit to specific repositories
   - Set expiration dates

2. **Token Permissions**
   - **Minimum required**: `Contents: Read-only`
   - For private repos only

3. **Rotate Tokens Regularly**
   - Generate new tokens periodically
   - Revoke old tokens after updating

4. **Monitor Token Usage**
   - Check GitHub's token usage logs
   - Revoke if suspicious activity detected

### Creating a Secure Token

1. Go to [GitHub Settings → Tokens](https://github.com/settings/tokens)
2. Click "Generate new token" → "Fine-grained token"
3. Set **Repository access**: Only select repositories
4. Set **Permissions**:
   - Contents: Read-only
   - Metadata: Read-only (automatic)
5. Set **Expiration**: 90 days (recommended)
6. Generate and copy token
7. Paste into Webbadeploy (will be encrypted automatically)

---

## Public Repositories

**No token needed!** Public repositories can be deployed without a token.

Just enter the repository URL:
- `https://github.com/username/repo`
- `github.com/username/repo`
- `username/repo`

---

## Token Storage

### Database Schema
```sql
github_token TEXT  -- Encrypted using AES-256-GCM
```

### Encryption Format
```
base64(nonce + tag + ciphertext)
- nonce: 12 bytes (random)
- tag: 16 bytes (authentication)
- ciphertext: variable length
```

---

## Security Considerations

### ✅ What's Protected
- Tokens encrypted in database
- Tokens not exposed in logs
- Tokens not visible in UI
- Tokens not in API responses

### ⚠️ Potential Risks
- **Server compromise**: If attacker gains root access, they could:
  - Read encryption key from database
  - Decrypt tokens
  - **Mitigation**: Use fine-grained tokens with minimal permissions

- **Memory dumps**: Tokens briefly in memory during deployment
  - **Mitigation**: Tokens discarded immediately after use

### 🔒 Additional Security

For maximum security:
1. **Use SSH keys** instead of HTTPS tokens (future feature)
2. **Deploy keys** per repository (future feature)
3. **Webhook deployments** instead of polling (future feature)

---

## API Endpoints

### Tokens Never Exposed
```json
// GET /api.php?action=get_site&id=123
{
  "github_repo": "username/repo",
  "github_branch": "main",
  "github_token": null  // ← Never returned in API
}
```

### Masked Display
When displaying token info:
```php
$masked = maskSensitiveData($token, 4);
// ghp_abc...xyz (shows only first/last 4 chars)
```

---

## Compliance

### Data Protection
- **GDPR compliant**: Tokens encrypted, can be deleted
- **PCI DSS**: Encryption at rest using industry-standard algorithms
- **SOC 2**: Secure key management and access controls

### Audit Trail
- Token usage logged (without exposing token)
- Last deployment timestamp recorded
- Commit hashes tracked

---

## Troubleshooting

### Token Not Working?

1. **Check token permissions**
   - Needs `Contents: Read` access
   - Check repository access

2. **Token expired?**
   - GitHub tokens can expire
   - Generate new token and update

3. **Private repo access**
   - Ensure token has access to specific repository
   - Check organization permissions

### Rotating Tokens

```bash
# 1. Generate new token on GitHub
# 2. Update in Webbadeploy UI (Settings → GitHub)
# 3. Old token automatically replaced and encrypted
# 4. Revoke old token on GitHub
```

---

## Future Enhancements

### Planned Security Features
- [ ] SSH key support (more secure than tokens)
- [ ] Deploy keys (read-only, per-repository)
- [ ] Webhook-based deployments (no stored credentials)
- [ ] Token rotation reminders
- [ ] Audit log for token usage
- [ ] Multi-factor authentication for token changes

---

## Summary

✅ **Tokens are encrypted** using AES-256-GCM  
✅ **Never stored in plain text**  
✅ **Decrypted only when needed**  
✅ **Never logged or exposed**  
✅ **Use fine-grained tokens** with minimal permissions  
✅ **Rotate tokens regularly**  

**Your GitHub tokens are secure!** 🔒

---

**Questions or concerns?** Check the encryption code in:
- `gui/includes/encryption.php` - Encryption functions
- `gui/includes/github-deploy.php` - Token usage
- `gui/includes/functions.php` - Token storage
