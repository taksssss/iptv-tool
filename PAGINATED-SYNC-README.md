# Paginated Fetch and Save for IPTV Sources

This directory contains implementation for paginated fetching and saving of IPTV live sources.

## Files

- **manage.php**: Backend API with paginated GET and POST endpoints
- **manage.js**: Frontend JavaScript with pagination logic and jQuery fallback
- **sources.json**: Data store for live sources (supports mixed string/object entries)
- **test-manage.html**: Demo page for testing the paginated sync functionality

## Backend API (manage.php)

### GET /manage.php?action=get_sources

Fetch a page of sources from the data store.

**Parameters:**
- `page` (int, default: 1): Page number to fetch
- `pageSize` (int, default: 200): Number of items per page

**Response:**
```json
{
  "success": true,
  "totalItems": 1000,
  "page": 1,
  "pageSize": 200,
  "items": [...]
}
```

### POST /manage.php?action=save_sources

Save a page of sources to the data store.

**Parameters:**
- `page` (int, default: 1): Page number to save
- `pageSize` (int, default: 200): Number of items per page

**Request Body:**
```json
{
  "items": [
    "CCTV1,http://example.com/cctv1.m3u8",
    {"channel": "湖南卫视", "url": "http://example.com/hunan.m3u8"}
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "保存成功",
  "written": 2
}
```

## Frontend API (manage.js)

### window.iptvManage.fetchAndSaveAll(options)

Main function to fetch and save all pages with pagination.

**Options:**
- `pageSize` (int, default: 200): Items per page
- `startPage` (int, default: 1): Starting page number
- `delayMs` (int, default: 50): Delay between page operations in milliseconds
- `maxPages` (int, default: Infinity): Maximum pages to process

**Returns:** Promise that resolves with `{totalItems, totalPages}`

**Example:**
```javascript
window.iptvManage.fetchAndSaveAll({
  pageSize: 200,
  startPage: 1,
  delayMs: 100
}).then(result => {
  console.log('Completed:', result);
}).catch(error => {
  console.error('Error:', error);
});
```

### Progress Display

The implementation displays progress via:
- Console logs
- DOM elements `#progress` and `#log` (if present)

## Data Store (sources.json)

The `sources.json` file supports both string and object entries:

```json
[
  "CCTV1,http://example.com/cctv1.m3u8",
  "CCTV2,http://example.com/cctv2.m3u8",
  {"channel": "湖南卫视", "url": "http://example.com/hunan.m3u8"},
  {"channel": "浙江卫视", "url": "http://example.com/zhejiang.m3u8"}
]
```

## Implementation Features

### Concurrency Safety
- Uses PHP `flock()` with `LOCK_EX` for file locking
- Read-modify-write strategy with retry logic (max 3 attempts)
- Safe concurrent access from multiple processes

### Data Preservation
- Maintains array indices when saving pages
- Fills gaps with `null` placeholders
- Supports mixed data types (strings and objects)

### Error Handling
- Validates request parameters
- Returns helpful error messages
- HTTP status codes for error conditions
- Retry logic for transient failures

### Browser Compatibility
- Uses native `fetch()` API when available
- Falls back to jQuery AJAX if fetch not supported
- Works without jQuery in modern browsers

## Testing

Open `test-manage.html` in a browser to test the functionality:

1. Open the file in a web server (required for AJAX calls)
2. Set the page size (default: 200)
3. Click "开始分页同步" to start the sync
4. Monitor progress in the log area

## Requirements

- **PHP 7+** with JSON support
- **Modern browser** with JavaScript enabled
- **Optional:** jQuery for older browser support

## Usage in Production

1. Place these files in your web-accessible directory
2. Ensure `sources.json` exists and is writable by the web server
3. Integrate `manage.js` into your page:
   ```html
   <script src="manage.js"></script>
   ```
4. Add UI elements:
   ```html
   <input type="number" id="pageSizeInput" value="200">
   <button id="startPagedSync">Start Sync</button>
   <div id="progress"></div>
   <div id="log"></div>
   ```
5. The sync will start automatically when the button is clicked

## Security Notes

- This implementation does not include authentication
- Add appropriate access controls in production
- Validate and sanitize all input data
- Consider rate limiting for the API endpoints
