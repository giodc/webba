# Dashboard Performance Improvements

## Problem
The dashboard was very slow to load because it was calling `docker stats` synchronously for every running container during page load. Each `docker stats` call is blocking and can take 1-2 seconds, causing severe performance issues with multiple sites.

## Solution Implemented

### 1. Removed Blocking Stats from Page Load
- **Before**: `index.php` called `getContainerStats()` for each site during initial page render
- **After**: Stats sections now show loading spinners and load asynchronously via JavaScript

### 2. New Async Stats API Endpoint
- Created `get_dashboard_stats` API endpoint in `api.php`
- Implements 5-second caching to prevent hammering Docker
- Returns lightweight CPU and memory stats only
- Cache files stored in `/tmp/stats_cache_{site_id}`

### 3. JavaScript Async Loading
- Added `loadAllDashboardStats()` function in `app.js`
- Loads stats for all sites in parallel after page load
- Auto-refreshes every 10 seconds
- Non-blocking - page loads instantly

## Performance Gains

### Before
- **Page Load**: 5-10 seconds with 5 sites (blocking)
- **Docker Calls**: N calls on every page load (N = number of running containers)
- **User Experience**: Long wait, blank screen

### After
- **Page Load**: <1 second (instant)
- **Docker Calls**: Cached for 5 seconds, called asynchronously
- **User Experience**: Instant page load, stats appear within 1-2 seconds

## Technical Details

### Files Modified
1. **`/opt/wharftales/gui/index.php`**
   - Removed `getContainerStats()` function
   - Removed blocking stats calls in foreach loop
   - Added `data-site-id` attributes to cards
   - Changed stats section to show loading spinners initially

2. **`/opt/wharftales/gui/api.php`**
   - Added `get_dashboard_stats` case to switch statement
   - Implemented `getDashboardStats()` function with caching

3. **`/opt/wharftales/gui/js/app.js`**
   - Added `loadAllDashboardStats()` function
   - Set up auto-refresh interval (10 seconds)
   - Updated version to v5.0

### Caching Strategy
- Stats cached for 5 seconds per container
- Cache files: `/tmp/stats_cache_{site_id}`
- Prevents multiple simultaneous requests from overloading Docker
- Balances freshness with performance

## Testing
1. Clear browser cache (Ctrl+Shift+R)
2. Open dashboard - should load instantly
3. Stats should appear within 1-2 seconds
4. Check browser console for: "WharfTales JS v5.0 loaded"

## Future Optimizations
- Consider Redis for stats caching instead of file-based
- Add WebSocket support for real-time stats updates
- Implement stats aggregation for overview dashboard
