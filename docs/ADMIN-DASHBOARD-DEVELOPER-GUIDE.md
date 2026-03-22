# AI Gateway Admin Dashboard - Developer Guide

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [File Structure](#file-structure)
3. [Class Reference](#class-reference)
4. [JavaScript API](#javascript-api)
5. [REST Endpoints](#rest-endpoints)
6. [Adding New Tabs](#adding-new-tabs)
7. [Customization Guide](#customization-guide)
8. [Security Considerations](#security-considerations)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

The AI Gateway Admin Dashboard follows a modular, tab-based architecture:

```
WordPress Admin Menu
    ↓
Admin_Dashboard (Menu Registration)
    ↓
Admin_Page_Renderer (Template Loading)
    ↓
Dashboard Template (HTML + Tab Structure)
    ├─ admin-dashboard.js (Tab Switching, API Calls)
    └─ admin-dashboard.css (Styling + Responsive)
         ↓
         REST API Calls (Via fetch)
         ↓
         Admin_Controller (REST Endpoints)
         ├─ /auth/keys (GET/POST/DELETE)
         ├─ /rate-limit/status (GET)
         ├─ /health (GET)
         └─ /admin/settings (GET/PATCH)
```

**Key Design Principles:**

1. **Admin-Only Access:** All endpoints require `manage_options` capability
2. **Vanilla JavaScript:** No frameworks, minimal dependencies
3. **Nonce Protection:** All AJAX requests validated with WordPress nonces
4. **PSR-4 Namespace:** All classes use `AI_Gateway\*` namespace
5. **Tab-Based UI:** Clear separation of concerns
6. **Responsive Design:** Works on mobile (360px), tablet (768px), desktop (1024px+)

---

## File Structure

```
ai-gateway/
├── includes/
│   ├── admin/
│   │   ├── class-admin-dashboard.php          # Menu registration, page rendering
│   │   ├── class-admin-page-renderer.php      # Template loading
│   │   └── class-admin-asset-enqueuer.php     # Script/style registration
│   ├── api/
│   │   └── class-admin-controller.php         # REST endpoints
│   └── class-plugin-loader.php                # Main class loader
├── templates/
│   └── admin/
│       ├── dashboard.php                      # Main dashboard template
│       ├── tab-overview.php                   # Overview tab
│       ├── tab-api-keys.php                   # API Keys tab
│       ├── tab-audit-log.php                  # Audit Log tab
│       ├── tab-health.php                     # Health tab
│       └── tab-settings.php                   # Settings tab
├── assets/
│   ├── admin-dashboard.js                     # Tab logic, API calls
│   └── admin-dashboard.css                    # Styling + responsive
├── docs/
│   ├── ADMIN-DASHBOARD-USER-GUIDE.md         # User documentation
│   └── ADMIN-DASHBOARD-DEVELOPER-GUIDE.md    # This file
└── ai-gateway-plugin.php                      # Plugin entry point
```

---

## Class Reference

### Admin_Dashboard

**Location:** `includes/admin/class-admin-dashboard.php`

Responsible for registering the admin menu and rendering the dashboard page.

```php
namespace AI_Gateway\Admin;

class Admin_Dashboard {
    public function __construct(Audit_Logger $audit_logger) { }
    public function register_menu() { }
    public function render_page() { }
    public function get_nonce() { }
}
```

**Methods:**

- `register_menu()` - Called on `admin_menu` hook, adds menu item
- `render_page()` - Callback for add_menu_page, renders dashboard
- `get_nonce()` - Returns WordPress nonce for AJAX requests

**Usage:**

```php
$dashboard = new Admin_Dashboard($audit_logger);
add_action('admin_menu', array($dashboard, 'register_menu'));
```

---

### Admin_Page_Renderer

**Location:** `includes/admin/class-admin-page-renderer.php`

Loads dashboard templates and passes data to views.

```php
namespace AI_Gateway\Admin;

class Admin_Page_Renderer {
    public function render() { }
    public function get_tab_content($tab_name) { }
}
```

**Methods:**

- `render()` - Loads `templates/admin/dashboard.php` with context
- `get_tab_content($tab_name)` - Loads individual tab template

**Usage:**

```php
$renderer = new Admin_Page_Renderer();
$renderer->render();
```

---

### Admin_Asset_Enqueuer

**Location:** `includes/admin/class-admin-asset-enqueuer.php`

Registers and enqueues dashboard CSS and JavaScript.

```php
namespace AI_Gateway\Admin;

class Admin_Asset_Enqueuer {
    public function __construct(Audit_Logger $audit_logger) { }
    public function enqueue_scripts() { }
    public function enqueue_styles() { }
}
```

**Methods:**

- `enqueue_scripts()` - Called on `admin_enqueue_scripts`, loads JS
- `enqueue_styles()` - Called on `admin_enqueue_scripts`, loads CSS

**Data Passed to JavaScript:**

Via `wp_localize_script()`:

```javascript
aiGatewayConfig = {
    nonce: "abc123...",           // WordPress nonce for requests
    api_root: "/wp-json/",        // REST API root URL
    plugin_url: "/wp-content/plugins/ai-gateway/",
    admin_page_url: "/wp-admin/admin.php?page=ai-gateway"
}
```

---

### Admin_Controller

**Location:** `includes/api/class-admin-controller.php`

Defines REST API endpoints for admin-only operations.

```php
namespace AI_Gateway\API;

class Admin_Controller {
    public function __construct(Audit_Logger $audit_logger) { }
    public function register_routes() { }
    public function check_admin_permission() { }

    // Endpoint callbacks
    public function list_keys_callback(WP_REST_Request $request) { }
    public function generate_key_callback(WP_REST_Request $request) { }
    public function revoke_key_callback(WP_REST_Request $request) { }
    public function get_rate_limit_status_callback(WP_REST_Request $request) { }
    public function get_settings_callback(WP_REST_Request $request) { }
    public function update_settings_callback(WP_REST_Request $request) { }
}
```

**Endpoints:**

All endpoints require `manage_options` capability.

1. `GET /wp-json/ai-gateway/v1/auth/keys`
2. `POST /wp-json/ai-gateway/v1/auth/keys/generate`
3. `DELETE /wp-json/ai-gateway/v1/auth/keys/{id}`
4. `GET /wp-json/ai-gateway/v1/rate-limit/status`
5. `GET /wp-json/ai-gateway/v1/admin/settings`
6. `PATCH /wp-json/ai-gateway/v1/admin/settings`

---

## JavaScript API

### Dashboard Object

**Location:** `assets/admin-dashboard.js`

**Global:** `window.aiGatewayDashboard`

**Exported Methods:**

```javascript
window.aiGatewayDashboard = {
    init()                                      // Initialize dashboard
    switchTab(tabName)                          // Switch to tab
    loadTab(tabName)                            // Load tab content
    fetchAPI(endpoint, options)                 // Fetch with error handling
    showError(message)                          // Show error notice
    showSuccess(message)                        // Show success notice
    escapeHtml(text)                            // Escape HTML entities
    getRelativeTime(date)                       // Format date as relative time
    formatDate(date)                            // Format date as locale string
}
```

### Tab Loading Functions

Each tab has a dedicated loading function:

```javascript
Dashboard.loadOverviewTab()       // Overview
Dashboard.loadApikeysTab()        // API Keys
Dashboard.loadAuditlogTab()       // Audit Log
Dashboard.loadHealthTab()         // Health
Dashboard.loadSettingsTab()        // Settings
```

### API Calls

**Example:** Fetching rate limit status

```javascript
this.fetchAPI('ai-gateway/v1/rate-limit/status')
    .then(data => {
        console.log(data.requests_this_minute);  // 23
        console.log(data.limit);                  // 60
        console.log(data.reset_at);              // ISO 8601 timestamp
    })
    .catch(error => {
        console.error('Failed:', error);
    });
```

**Error Handling:**

```javascript
fetchAPI(endpoint, options = {})
    .then(response => {
        // Success - response is parsed JSON
    })
    .catch(error => {
        // Automatic error display via showError()
        // Error messages visible to user
    });
```

---

## REST Endpoints

### GET /wp-json/ai-gateway/v1/auth/keys

**Permission:** Admin only (`manage_options`)

**Response:**

```json
{
    "keys": [
        {
            "id": "key_1",
            "suffix": "a9d7c3f",
            "created": "2026-03-11 12:00:00",
            "last_used": "2026-03-11 12:45:00",
            "status": "active"
        },
        {
            "id": "key_2",
            "suffix": "x2b9f8e",
            "created": "2026-03-10 10:00:00",
            "last_used": null,
            "status": "revoked"
        }
    ]
}
```

---

### POST /wp-json/ai-gateway/v1/auth/keys/generate

**Permission:** Admin only

**Response:**

```json
{
    "key": "0123456789abcdef0123456789abcdef01234567",
    "id": "key_3",
    "suffix": "01234567"
}
```

**Note:** The plaintext `key` is returned only once. Client must save it.

---

### DELETE /wp-json/ai-gateway/v1/auth/keys/{id}

**Permission:** Admin only

**Parameters:**

- `id` (required): Key ID from URL (e.g., `key_1`)

**Response:**

```json
{
    "success": true,
    "message": "Key revoked"
}
```

**HTTP Status:** 200 (success), 404 (not found)

---

### GET /wp-json/ai-gateway/v1/rate-limit/status

**Permission:** Admin only

**Response:**

```json
{
    "requests_this_minute": 23,
    "limit": 60,
    "reset_at": "2026-03-11T12:35:00Z"
}
```

---

### GET /wp-json/ai-gateway/v1/admin/settings

**Permission:** Admin only

**Response:**

```json
{
    "enable_debug_logging": false,
    "max_backup_versions": 3,
    "clean_backups_on_deactivate": false
}
```

---

### PATCH /wp-json/ai-gateway/v1/admin/settings

**Permission:** Admin only

**Request Body:**

```json
{
    "enable_debug_logging": true,
    "max_backup_versions": 5,
    "clean_backups_on_deactivate": true
}
```

**Validation:**

- `max_backup_versions`: Must be 1-10 integer
- Other fields: Boolean

**Response:**

```json
{
    "success": true,
    "settings": {
        "enable_debug_logging": true,
        "max_backup_versions": 5,
        "clean_backups_on_deactivate": true
    }
}
```

**HTTP Status:** 200 (success), 422 (validation error)

---

## Adding New Tabs

To add a new dashboard tab:

### Step 1: Create Tab Template

**File:** `templates/admin/tab-my-feature.php`

```php
<?php
/**
 * My Feature Tab
 * @package AI_Gateway\Admin
 */
?>
<div class="ai-gateway-my-feature-content">
    <!-- Tab content here -->
</div>
```

### Step 2: Create Tab Dashboard Button

Edit `templates/admin/dashboard.php`:

```html
<button
    class="nav-tab ai-gateway-tab-btn"
    data-tab="my-feature"
    role="tab"
    aria-selected="false"
    aria-controls="tab-my-feature"
>
    <?php esc_html_e( 'My Feature', 'ai-gateway' ); ?>
</button>
```

And add content container:

```html
<div id="tab-my-feature" class="ai-gateway-tab-content" role="tabpanel" aria-labelledby="tab-my-feature">
    <div class="ai-gateway-loading">
        <span class="spinner"></span>
        <?php esc_html_e( 'Loading...', 'ai-gateway' ); ?>
    </div>
</div>
```

### Step 3: Add JavaScript Tab Loader

Edit `assets/admin-dashboard.js`:

```javascript
/**
 * Load My Feature tab
 */
loadMyfeatureTab: function() {
    const tabContent = document.getElementById('tab-my-feature');

    this.fetchAPI('ai-gateway/v1/my-feature')
        .then(data => {
            let html = '<div class="ai-gateway-card">';
            html += '<h3>My Feature</h3>';
            // Build HTML from data
            html += '</div>';

            tabContent.innerHTML = html;
            this.isLoading = false;
        })
        .catch(error => {
            console.error('Error loading feature:', error);
            tabContent.innerHTML = '<div class="ai-gateway-error"><p>Error</p></div>';
            this.isLoading = false;
        });
},
```

### Step 4: Add REST Endpoint

Edit `includes/api/class-admin-controller.php`:

```php
public function register_routes() {
    // ... existing routes ...

    register_rest_route(
        'ai-gateway/v1',
        '/my-feature',
        array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_my_feature_callback' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        )
    );
}

public function get_my_feature_callback( WP_REST_Request $request ) {
    // Get data
    $data = /* ... fetch your data ... */;

    $this->audit_logger->log_action(
        'get_my_feature',
        '/my-feature',
        'GET',
        200
    );

    return new WP_REST_Response( $data, 200 );
}
```

---

## Customization Guide

### Styling

All dashboard styles are in `assets/admin-dashboard.css`.

**Key Classes:**

```css
.ai-gateway-card                    /* Card container */
.ai-gateway-tab-btn                 /* Tab button */
.ai-gateway-status-badge            /* Status badge */
.ai-gateway-method-badge            /* HTTP method badge */
.ai-gateway-modal                   /* Modal dialog */
.ai-gateway-table                   /* Table */
```

**Customizing Colors:**

Edit these CSS variables or inline styles:

```css
/* Primary color */
--ai-gateway-primary: #0073aa;

/* Status colors */
--ai-gateway-success: #d4edda;
--ai-gateway-warning: #fff3cd;
--ai-gateway-error: #f8d7da;
```

**Responsive Breakpoints:**

```css
/* Desktop: 1024px+ */
@media (min-width: 1024px) { }

/* Tablet: 768px-1023px */
@media (max-width: 1023px) and (min-width: 768px) { }

/* Mobile: <768px */
@media (max-width: 767px) { }
```

### JavaScript Modifications

**Customizing Tab Loading:**

```javascript
Dashboard.loadMyTab = function() {
    // Your custom implementation
};

// Trigger load
Dashboard.switchTab('my-tab');
```

**Adding Utilities:**

```javascript
// Add to Dashboard object
Dashboard.myUtility = function() {
    // Your code
};

// Use
Dashboard.myUtility();
```

---

## Security Considerations

### 1. Admin-Only Access

All endpoints check `manage_options` capability:

```php
public function check_admin_permission() {
    return current_user_can( 'manage_options' );
}
```

**Never bypass this check.**

### 2. Nonce Validation

All AJAX requests include nonce in headers:

```javascript
'X-WP-Nonce': this.nonce
```

WordPress automatically validates. **Never skip nonce validation.**

### 3. Input Sanitization

**Server-side:**

```php
$value = sanitize_text_field( $_POST['value'] );
$key_id = sanitize_key( $_POST['key_id'] );
```

**Client-side:**

```javascript
const html = this.escapeHtml(value);  // Always escape before display
```

### 4. API Key Storage

- **Never store plaintext keys**
- Use SHA-256 hashing: `hash('sha256', $key)`
- Display only last 8 characters (suffix)

### 5. Error Messages

**Never expose sensitive information:**

```php
// ❌ BAD
return new WP_REST_Response( array(
    'error' => 'Database: ' . $db->last_error  // Exposes DB details
), 500);

// ✅ GOOD
return new WP_REST_Response( array(
    'error' => 'Database error occurred'       // Generic message
), 500);
```

### 6. CORS Protection

REST API respects WordPress CORS headers. No special handling needed.

---

## Testing

### Unit Testing

For class methods:

```php
public function test_admin_permission_check() {
    $controller = new Admin_Controller($this->audit_logger);

    // Test with admin
    wp_set_current_user($this->admin_id);
    $this->assertTrue($controller->check_admin_permission());

    // Test without admin
    wp_set_current_user($this->user_id);
    $this->assertFalse($controller->check_admin_permission());
}
```

### Integration Testing

Test end-to-end flows:

```php
public function test_generate_and_revoke_key() {
    wp_set_current_user($this->admin_id);

    // Generate
    $response = $this->rest_client->post('/auth/keys/generate');
    $this->assertEquals(201, $response['status']);
    $key = $response['body']['key'];

    // Revoke
    $key_id = $response['body']['id'];
    $response = $this->rest_client->delete('/auth/keys/' . $key_id);
    $this->assertEquals(200, $response['status']);
}
```

### Manual Testing

See `SMOKE-TEST-RESULTS.md` for comprehensive manual test checklist.

---

## Troubleshooting

### Dashboard Not Loading

**Check:**
1. Plugin activated: `wp plugin list`
2. Admin menu appears: Visit `/wp-admin/`
3. User is admin: `wp user list --role=administrator`

**Debug:**
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check /wp-content/debug.log
```

### JavaScript Errors

**Browser Console (F12 → Console):**
- Look for red errors
- Check `aiGatewayConfig` is defined
- Verify REST API accessible: `fetch('/wp-json/').then(r => r.json())`

### REST Endpoint Returns 403

**Cause:** User doesn't have `manage_options` capability

**Fix:**
```php
wp user update admin --role=administrator
```

### Settings Don't Save

**Check:**
1. Validation passes: Check error message in browser
2. wp_options writable: File permissions
3. Nonce valid: Check `X-WP-Nonce` header

**Debug:**
```php
error_log('Settings update: ' . print_r($_POST, true));
```

---

## Performance Optimization

### Lazy Loading Tabs

Tabs load data only when clicked (already implemented):

```javascript
loadTab: function(tabName) {
    // Only load when user switches to this tab
    this.loadOverviewTab();
}
```

### Caching

Consider caching expensive operations:

```javascript
// Cache health status for 30 seconds
const healthCache = {};
function getCachedHealth() {
    if (healthCache.data &&
        Date.now() - healthCache.timestamp < 30000) {
        return Promise.resolve(healthCache.data);
    }

    return this.fetchAPI('ai-gateway/v1/health')
        .then(data => {
            healthCache.data = data;
            healthCache.timestamp = Date.now();
            return data;
        });
}
```

### Minification

Minify JavaScript and CSS for production:

```bash
npx uglify-js admin-dashboard.js -o admin-dashboard.min.js
npx cssnano admin-dashboard.css -o admin-dashboard.min.css
```

---

## Resources

- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [WordPress Admin Menu](https://developer.wordpress.org/plugins/administration-menus/)
- [WordPress Security](https://developer.wordpress.org/plugins/security/)
- [Nonces](https://developer.wordpress.org/plugins/security/nonces/)

---

*Developer Guide Version 1.0*
*Last Updated: 2026-03-11*
