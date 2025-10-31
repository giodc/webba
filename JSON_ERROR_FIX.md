# Fix: Network error - Unexpected end of JSON input

## Problem
Browser console showing:
```
Network error: Failed to execute 'json' on 'Response': Unexpected end of JSON input
Network error: Unexpected end of JSON input
```

## Root Cause
The old update system functions (`performSystemUpdate`, `getUpdateInformation`, `getUpdateLogs`) were trying to include a non-existent file `includes/updater.php`, causing PHP errors that corrupted the JSON response.

## What Was Fixed

### 1. Replaced Old Update Functions
**File:** `/opt/wharftales/gui/api.php`

Replaced/fixed four broken functions:
- `performSystemUpdate()` - Now uses `triggerUpdate()`
- `getUpdateInformation()` - Now uses `checkForUpdates()`  
- `getUpdateLogs()` - Now reads from log files
- `checkForUpdatesHandler()` - **NEW** - Handles background update checks from `app.js`

### 2. Added Missing `ob_clean()` Calls
Added output buffer cleaning before all JSON responses in:
- `triggerUpdateHandler()`
- `checkUpdateStatusHandler()`
- All three replaced functions above

### 3. Removed Trailing Whitespace
Removed extra newline after closing `?>` tag in `api.php` that could corrupt JSON.

## Testing

### Test 1: Check for Updates
```bash
# Should return valid JSON
curl -s http://localhost:9000/api.php?action=get_update_info \
  -H "Cookie: PHPSESSID=your_session_id" | jq .
```

### Test 2: Trigger Update
```bash
# Should return valid JSON
curl -s http://localhost:9000/api.php?action=trigger_update \
  -X POST \
  -H "Cookie: PHPSESSID=your_session_id" \
  -H "Content-Type: application/json" \
  -d '{"skip_backup": false}' | jq .
```

### Test 3: Via Web UI
1. Go to Settings → System Updates
2. Click "Check Now"
3. Should show version info without errors
4. Check browser console - no JSON errors

## Files Modified
- `/opt/wharftales/gui/api.php` - Fixed update handlers
- All changes committed and ready to push

## Verification
After the fix, the API should return proper JSON:
```json
{
  "success": true,
  "info": {
    "update_available": false,
    "current_version": "0.0.4",
    "latest_version": "0.0.4"
  }
}
```

Instead of broken/incomplete JSON or PHP errors.
