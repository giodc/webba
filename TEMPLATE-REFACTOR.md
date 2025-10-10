# Template Refactor - No More Heredoc Escaping! ✅

## Problem Solved

**Before:** Complex heredoc strings with multiple escaping layers caused parse errors  
**After:** Simple template files - no escaping issues!

---

## What Changed

### Old Approach (Heredoc) ❌
```php
$createCmd = "docker exec {$containerName} sh -c 'cat > /var/www/html/index.php << \"PHPEOF\"
<?php echo \$_SERVER[\\\"SERVER_SOFTWARE\\\"] ?? \\\"Apache\\\"; ?>
PHPEOF
'";
exec($createCmd);
```

**Problems:**
- Complex escaping: `\$_SERVER[\\\"...\\\"]`
- Easy to get wrong
- Parse errors on line 43
- Hard to maintain
- Hard to customize

### New Approach (Templates) ✅
```php
$templatePath = __DIR__ . '/templates/php-welcome.php';
$template = file_get_contents($templatePath);
$content = str_replace('{{SITE_NAME}}', $siteName, $template);

$tempFile = tempnam(sys_get_temp_dir(), 'php_welcome_');
file_put_contents($tempFile, $content);
exec("docker cp {$tempFile} {$containerName}:/var/www/html/index.php");
unlink($tempFile);
```

**Benefits:**
- ✅ No escaping needed!
- ✅ No parse errors!
- ✅ Easy to edit templates
- ✅ Clean, maintainable code
- ✅ Simple placeholder replacement

---

## Template Files

### 1. PHP Welcome Template
**Location:** `gui/templates/php-welcome.php`

```php
<?php http_response_code(200); ?>
<!doctype html>
<html>
<head>
    <title>{{SITE_NAME}} - Ready</title>
    ...
</head>
<body>
    <h1>🚀 {{SITE_NAME}}</h1>
    <div>Your PHP application is ready!</div>
    
    <div>PHP Version: <?php echo phpversion(); ?></div>
    <div>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></div>
    ...
</body>
</html>
```

### 2. Laravel Welcome Template
**Location:** `gui/templates/laravel-welcome.php`

```php
<?php http_response_code(200); ?>
<!doctype html>
<html>
<head>
    <title>{{SITE_NAME}} - Laravel</title>
    ...
</head>
<body>
    <h1>{{SITE_NAME}}</h1>
    <div>Laravel application container is ready!</div>
    
    <div>PHP Version: <?php echo phpversion(); ?></div>
    <div>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></div>
    <div>Database: <?php echo !empty($_ENV['DB_HOST']) ? 'Configured' : 'Not configured'; ?></div>
    ...
</body>
</html>
```

---

## How It Works

### 1. Template Loading
```php
$templatePath = __DIR__ . '/templates/php-welcome.php';
$template = file_get_contents($templatePath);
```

### 2. Placeholder Replacement
```php
$siteName = htmlspecialchars($site['name'], ENT_QUOTES);
$content = str_replace('{{SITE_NAME}}', $siteName, $template);
```

### 3. Copy to Container
```php
$tempFile = tempnam(sys_get_temp_dir(), 'php_welcome_');
file_put_contents($tempFile, $content);
exec("docker cp {$tempFile} {$containerName}:/var/www/html/index.php");
```

### 4. Cleanup
```php
exec("docker exec {$containerName} chown www-data:www-data /var/www/html/index.php");
unlink($tempFile);
```

---

## Customizing Templates

### To Customize Welcome Pages:

1. **Edit template files directly:**
   ```bash
   nano gui/templates/php-welcome.php
   nano gui/templates/laravel-welcome.php
   ```

2. **No escaping needed!** Just write normal PHP/HTML

3. **Use placeholders:**
   - `{{SITE_NAME}}` - Will be replaced with actual site name

4. **Test template syntax:**
   ```bash
   php -l gui/templates/php-welcome.php
   ```

5. **Commit changes:**
   ```bash
   git add gui/templates/
   git commit -m "customize: update welcome page templates"
   ```

---

## Adding More Placeholders

To add more dynamic content:

1. **Add placeholder to template:**
   ```html
   <div>Domain: {{SITE_DOMAIN}}</div>
   ```

2. **Replace in api.php:**
   ```php
   $content = str_replace('{{SITE_NAME}}', $siteName, $template);
   $content = str_replace('{{SITE_DOMAIN}}', $site['domain'], $content);
   ```

---

## Verification

### Check Templates Exist
```bash
ls -la gui/templates/
# Should show:
# php-welcome.php
# laravel-welcome.php
```

### Check Template Syntax
```bash
docker exec webbadeploy_gui php -l /var/www/html/templates/php-welcome.php
docker exec webbadeploy_gui php -l /var/www/html/templates/laravel-welcome.php
# Expected: No syntax errors detected ✅
```

### Check API Syntax
```bash
docker exec webbadeploy_gui php -l /var/www/html/api.php
# Expected: No syntax errors detected ✅
```

### Test Creating Sites
1. Create a new PHP site
2. Create a new Laravel site
3. Both should work without parse errors! ✅

---

## Migration from Old Code

### Old Heredoc Code (Removed)
The old heredoc approach with complex escaping has been completely removed from:
- `deployPHP()` function
- `deployLaravel()` function

### New Template Code (Active)
Both functions now use the template file approach.

---

## Benefits Summary

| Feature | Before (Heredoc) | After (Templates) |
|---------|------------------|-------------------|
| **Escaping** | Complex (`\\\$`, `\\\"`) | None needed! |
| **Parse Errors** | Yes (line 43) | No ✅ |
| **Maintainability** | Hard | Easy ✅ |
| **Customization** | Difficult | Simple ✅ |
| **Testing** | Can't test directly | Can test with `php -l` ✅ |
| **Readability** | Poor | Excellent ✅ |

---

## Git Commit

**Commit:** 8d0c0f6  
**Message:** "refactor: use template files instead of heredoc for welcome pages"

**Changes:**
- Created `gui/templates/php-welcome.php`
- Created `gui/templates/laravel-welcome.php`
- Modified `gui/api.php` to use templates
- Removed all heredoc code
- 160 insertions, 137 deletions

---

## Future Improvements

### Easy to Add:
1. **More templates** - WordPress, Node.js, Python, etc.
2. **Template variables** - Add more placeholders
3. **Template themes** - Multiple design options
4. **Template editor** - Web-based template customization

### Example: Adding WordPress Template
```bash
# 1. Create template
cat > gui/templates/wordpress-welcome.php << 'EOF'
<?php http_response_code(200); ?>
<!doctype html>
<html>
<head>
    <title>{{SITE_NAME}} - WordPress</title>
</head>
<body>
    <h1>{{SITE_NAME}}</h1>
    <p>WordPress is ready to install!</p>
</body>
</html>
EOF

# 2. Use in deployWordPress()
$templatePath = __DIR__ . '/templates/wordpress-welcome.php';
$template = file_get_contents($templatePath);
$content = str_replace('{{SITE_NAME}}', $siteName, $template);
# ... copy to container
```

---

## Troubleshooting

### Template Not Found
```
Error: file_get_contents(): failed to open stream
```
**Solution:** Ensure templates directory exists in container:
```bash
docker exec webbadeploy_gui ls -la /var/www/html/templates/
```

### Parse Error in Template
```bash
# Test template syntax
docker exec webbadeploy_gui php -l /var/www/html/templates/php-welcome.php
```

### Placeholder Not Replaced
Check that placeholder matches exactly:
- Template: `{{SITE_NAME}}`
- Code: `str_replace('{{SITE_NAME}}', ...)`

---

## Summary

✅ **No more heredoc escaping!**  
✅ **No more parse errors!**  
✅ **Easy to customize!**  
✅ **Clean, maintainable code!**  
✅ **Template files can be edited directly!**  

**The heredoc nightmare is over!** 🎉

---

**Date:** 2025-10-10  
**Commit:** 8d0c0f6  
**Status:** ✅ Production ready
