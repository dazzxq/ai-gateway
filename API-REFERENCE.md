# AI Gateway REST API Reference

## Overview

AI Gateway extends WordPress with specialized REST API endpoints for content management and automation — code snippets, global styles, system operations, and more. For standard WordPress content (posts, pages, templates, plugins, themes), use the built-in [WP REST API](https://developer.wordpress.org/rest-api/reference/) directly.

**API Version:** v1
**Endpoint Prefix:** `/wp-json/ai-gateway/v1/`
**Authentication:** WordPress Application Passwords (Basic Auth)
**Base URL:** `https://your-site.com/wp-json/ai-gateway/v1/`

---

## Authentication

All endpoints require authentication via **WordPress Application Passwords** using HTTP Basic Auth.

### Setup

1. Go to **WordPress Admin > Users > Profile**
2. Scroll to **Application Passwords**
3. Enter a name (e.g., "Claude Code") and click **Add New Application Password**
4. Copy the generated password (shown only once)

### Usage

```bash
# Basic Auth with Application Password
curl -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://your-site.com/wp-json/ai-gateway/v1/health
```

The user must have **Administrator** role (`manage_options` capability).

### Authentication Errors

```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": { "status": 401 }
}
```

**Note:** Application Passwords require HTTPS on production. Local development may need `add_filter('wp_is_application_passwords_available', '__return_true');` in a mu-plugin.

---

## Quick Reference

| Endpoint | Methods | Description |
|----------|---------|-------------|
| **Code Snippets** | | |
| `/code-snippets` | GET, POST | List & create snippets |
| `/code-snippets/{id}` | GET, PATCH, DELETE | Snippet CRUD |
| `/code-snippets/{id}/deploy` | POST | Atomic deploy (deactivate + activate + verify) |
| `/code-snippets/{id}/toggle` | POST | Force recompile (deactivate/reactivate) |
| `/code-snippets/cleanup-duplicates` | DELETE | Remove duplicate inactive snippets |
| **Global Styles** | | |
| `/global-styles/css` | GET, PATCH | CSS only (preferred for CSS changes) |
| `/global-styles/json` | GET, PATCH | JSON only (deep merge, CSS preserved) |
| `/global-styles/debug` | GET | Diagnostic info |
| `/global-styles/repair` | POST | Fix corrupted JSON |
| **Post Content Search & ACF** | | |
| `/posts/{id}/acf/{field}` | PATCH | Update ACF field |
| `/posts/search-content` | GET | Search post content by substring |
| `/acf/field-groups` | GET | List ACF field groups |
| **System** | | |
| `/health` | GET | Health check (unauthenticated) |
| `/system-info` | GET | PHP/WP/server info |
| `/logs` | GET | Read debug/error logs |
| `/system/flush-cache` | POST | Flush SpeedyCache + WP cache + transients |
| **Admin** | | |
| `/audit` | GET | Query audit trail |
| `/admin/settings` | GET, PATCH | Plugin settings |

**Total: 18 endpoints**

---

## Code Snippets

Manages entries in the [Code Snippets](https://wordpress.org/plugins/code-snippets/) plugin's `wp_snippets` table. All code changes go through PHP lint validation before saving.

### List Code Snippets

**Endpoint:** `GET /code-snippets`

**Query Parameters:**
- `per_page` (integer, default: 20, max: 100)
- `page` (integer, default: 1)
- `active` (boolean) — Filter by active/inactive status

```bash
curl -u "username:APP_PASSWORD" \
  "https://your-site.com/wp-json/ai-gateway/v1/code-snippets?per_page=100"
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "snippets": [
      {
        "id": 867,
        "name": "News Stream — Unified Post Card Renderer",
        "code": "// PHP code here...",
        "active": 1,
        "scope": "global",
        "modified": "2026-03-16 10:00:00"
      }
    ],
    "total": 52,
    "page": 1,
    "per_page": 100
  }
}
```

### Create Code Snippet

**Endpoint:** `POST /code-snippets`

```bash
curl -X POST -u "username:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"name":"My Snippet","code":"add_filter(\"the_title\", function($t) { return $t; });","scope":"global"}' \
  https://your-site.com/wp-json/ai-gateway/v1/code-snippets
```

**Required fields:** `name`, `code`
**Optional fields:** `scope` (global|admin|front-end, default: global), `active` (0|1, default: 0)

**Note:** Code is validated with `php -l` before saving. Invalid PHP returns 400.

### Get Code Snippet

**Endpoint:** `GET /code-snippets/{id}`

```bash
curl -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/code-snippets/867
```

### Update Code Snippet

**Endpoint:** `PATCH /code-snippets/{id}`

```bash
curl -X PATCH -u "username:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"code":"// updated code here"}' \
  https://your-site.com/wp-json/ai-gateway/v1/code-snippets/867
```

**Important:** After PATCH, the Code Snippets plugin does NOT recompile cached code automatically. Use `/deploy` or `/toggle` to force recompile.

### Delete Code Snippet

**Endpoint:** `DELETE /code-snippets/{id}`

```bash
curl -X DELETE -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/code-snippets/867
```

### Deploy Snippet (Atomic)

**Endpoint:** `POST /code-snippets/{id}/deploy`

Performs an atomic deploy: deactivate → activate → verify. Use after PATCH to ensure code changes take effect safely.

```bash
curl -X POST -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/code-snippets/867/deploy
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 867,
    "name": "News Stream — Unified Post Card Renderer",
    "active": 1,
    "steps": [
      { "action": "deactivate", "result": "ok" },
      { "action": "activate", "result": "ok" },
      { "action": "verify", "result": "ok", "active": 1 }
    ]
  }
}
```

### Toggle Snippet (Force Recompile)

**Endpoint:** `POST /code-snippets/{id}/toggle`

Deactivates then reactivates a snippet, forcing the Code Snippets plugin to recompile.

```bash
curl -X POST -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/code-snippets/867/toggle
```

**Note:** Only works on active snippets. Returns 400 if snippet is inactive.

### Cleanup Duplicate Snippets

**Endpoint:** `DELETE /code-snippets/cleanup-duplicates`

Finds and removes duplicate inactive code snippets (same name). Keeps the newest copy. Active snippets are never touched.

```bash
curl -X DELETE -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/code-snippets/cleanup-duplicates
```

### Code Snippets Plugin Compatibility

- Requires Code Snippets plugin v6.0+
- Returns **503 Service Unavailable** if Code Snippets plugin is not installed/active
- The `wp_snippets` table is accessed via `$wpdb` with `$wpdb->prepare()` for SQL safety
- Snippet `scope` values: `global` (everywhere), `admin` (admin only), `front-end` (frontend only)

---

## Global Styles

Manages the WordPress Global Styles CSS stored in the `wp_global_styles` post type. This is the **Site Editor > Styles > Additional CSS** panel.

For full Global Styles JSON read/write, use the WP Core REST API: `GET/POST /wp/v2/global-styles/9` (see [Using with WP Core REST API](#using-with-wp-core-rest-api)).

### Get CSS Only (Preferred)

**Endpoint:** `GET /global-styles/css`

Returns only the CSS content from `styles.css` field.

```bash
curl -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/global-styles/css
```

### Update CSS Only (Preferred)

**Endpoint:** `PATCH /global-styles/css`

Updates only the CSS field while preserving other styles/settings.

```bash
curl -X PATCH -u "username:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"content":"body { color: navy; }"}' \
  https://your-site.com/wp-json/ai-gateway/v1/global-styles/css
```

**Notes:**
- Maximum CSS size: 256 KB
- Automatically sets `version: 3` and `isGlobalStylesUserThemeJSON: true` (required by WP Site Editor)
- Uses `$wpdb->update()` directly to avoid `wp_kses_post()` mangling CSS child selectors (`>`)
- Changes appear immediately in Site Editor > Styles > Additional CSS

### Get JSON Only

**Endpoint:** `GET /global-styles/json`

Returns the styles JSON without the CSS field (lightweight).

```bash
curl -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/global-styles/json
```

### Update JSON (Deep Merge)

**Endpoint:** `PATCH /global-styles/json`

Deep-merges partial JSON into existing styles. The `css` field is automatically stripped from input (use `/global-styles/css` for CSS changes).

```bash
curl -X PATCH -u "username:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"styles":{"typography":{"fontFamily":"Inter"}}}' \
  https://your-site.com/wp-json/ai-gateway/v1/global-styles/json
```

### Debug Global Styles

**Endpoint:** `GET /global-styles/debug`

Returns diagnostic info about the `wp_global_styles` post structure.

```bash
curl -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/global-styles/debug
```

### Repair Global Styles

**Endpoint:** `POST /global-styles/repair`

Fixes corrupted or malformed Global Styles JSON (missing version field, stale keys, unescaped characters).

```bash
curl -X POST -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/global-styles/repair
```

---

## Post Content Search & ACF

### Update ACF Field

**Endpoint:** `PATCH /posts/{id}/acf/{field_name}`

```bash
curl -X PATCH -u "username:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"value":"new value"}' \
  https://your-site.com/wp-json/ai-gateway/v1/posts/41/acf/featured_media
```

### Search Post Content

**Endpoint:** `GET /posts/search-content`

Search for substring matches in post content (useful for finding shortcode usage, class references, etc.).

```bash
curl -u "username:APP_PASSWORD" \
  "https://your-site.com/wp-json/ai-gateway/v1/posts/search-content?q=td-hero&per_page=10"
```

### List ACF Field Groups

**Endpoint:** `GET /acf/field-groups`

```bash
curl -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/acf/field-groups
```

---

## System

### Health Check

**Endpoint:** `GET /health`

Public endpoint (no auth required). Checks REST API, wp_options, filesystem, and PHP version.

```bash
curl https://your-site.com/wp-json/ai-gateway/v1/health
```

**Response (200):**
```json
{
  "status": "healthy",
  "checks": {
    "rest_api": { "status": "ok", "message": "REST API accessible" },
    "wp_options": { "status": "ok", "message": "wp_options read/write working" },
    "filesystem": { "status": "ok", "message": "Filesystem write accessible" },
    "php_version": { "status": "ok", "message": "PHP 8.4.18 meets requirements (minimum 7.4)" }
  },
  "gateway_version": "0.5.0",
  "php_version": "8.4.18",
  "wordpress_version": "6.9.4"
}
```

### System Info

**Endpoint:** `GET /system-info`

```bash
curl -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/system-info
```

### Read Debug Logs

**Endpoint:** `GET /logs`

Reads the WordPress debug log. Requires `WP_DEBUG` and `WP_DEBUG_LOG` to be enabled in `wp-config.php`.

```bash
curl -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/logs
```

### Flush Cache

**Endpoint:** `POST /system/flush-cache`

Flushes SpeedyCache (if active), WP object cache, and expired transients.

```bash
curl -X POST -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/system/flush-cache
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "speedycache": { "flushed": true },
    "object_cache": { "flushed": true },
    "transients": { "deleted": 15 }
  }
}
```

---

## Admin

### Audit Trail

**Endpoint:** `GET /audit`

Query the audit log. All write operations are automatically logged with user identity.

**Query Parameters:**
- `per_page` (integer, default: 20)
- `page` (integer, default: 1)

```bash
curl -u "username:APP_PASSWORD" \
  "https://your-site.com/wp-json/ai-gateway/v1/audit?per_page=10"
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "entries": [
      {
        "id": "3",
        "timestamp": "2026-03-22 06:44:04",
        "method": "PATCH",
        "endpoint": "/admin/settings",
        "status_code": "200",
        "user_id": "3",
        "username": "admin",
        "resource_type": "admin",
        "resource_id": "",
        "action_description": "update_admin_settings"
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 10,
      "total": 3,
      "total_pages": 1
    }
  }
}
```

**Audit fields:**
- `user_id` — WordPress user ID who performed the action
- `username` — WordPress username
- `method` — HTTP method (GET, PATCH, POST, DELETE)
- `endpoint` — REST endpoint path
- `status_code` — HTTP status code returned
- `resource_type` — Type of resource affected
- `action_description` — Human-readable action description

### Plugin Settings

**Endpoint:** `GET /admin/settings` | `PATCH /admin/settings`

```bash
# Get settings
curl -u "username:APP_PASSWORD" \
  https://your-site.com/wp-json/ai-gateway/v1/admin/settings

# Update settings
curl -X PATCH -u "username:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"enable_debug_logging": true}' \
  https://your-site.com/wp-json/ai-gateway/v1/admin/settings
```

---

## Using with WP Core REST API

AI Gateway supplements the WordPress REST API with value-add endpoints (code snippets, global styles partial updates, search, ACF, system ops). For standard WordPress operations, use the built-in WP Core endpoints with the same Application Password:

```bash
# Posts CRUD (WP Core)
curl -u "username:APP_PASSWORD" https://your-site.com/wp-json/wp/v2/posts
curl -u "username:APP_PASSWORD" https://your-site.com/wp-json/wp/v2/posts/41

# Templates (WP Core)
curl -u "username:APP_PASSWORD" https://your-site.com/wp-json/wp/v2/templates
curl -u "username:APP_PASSWORD" https://your-site.com/wp-json/wp/v2/template-parts

# Global Styles full JSON (WP Core)
curl -u "username:APP_PASSWORD" https://your-site.com/wp-json/wp/v2/global-styles/9

# Plugins (WP Core)
curl -u "username:APP_PASSWORD" https://your-site.com/wp-json/wp/v2/plugins

# Themes (WP Core)
curl -u "username:APP_PASSWORD" https://your-site.com/wp-json/wp/v2/themes

# Settings (WP Core)
curl -u "username:APP_PASSWORD" https://your-site.com/wp-json/wp/v2/settings
```

The same Application Password authenticates both AI Gateway and WP Core endpoints.

---

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK — Request successful |
| 201 | Created — Resource created |
| 400 | Bad Request — Invalid parameters or validation failure |
| 401 | Unauthorized — Missing or invalid authentication |
| 403 | Forbidden — Insufficient permissions |
| 404 | Not Found — Resource does not exist |
| 500 | Internal Server Error |
| 503 | Service Unavailable — Required plugin (Code Snippets) not active |

---

## Error Codes Reference

| Code | HTTP | Meaning |
|------|------|---------|
| `rest_forbidden` | 401/403 | Missing or invalid authentication |
| `missing_field` | 400 | Required field missing from request |
| `invalid_content` | 400 | Content validation failed |
| `content_too_large` | 400 | Content exceeds size limit |
| `not_found` | 404 | Resource does not exist |
| `php_lint_error` | 400 | PHP syntax error in snippet code |
| `snippets_unavailable` | 503 | Code Snippets plugin not active |
| `server_error` | 500 | Internal server error |

---

## Version History

### v0.6.0 (v6.0 Milestone — Phase 36)
- **Consolidated:** Removed posts CRUD, plugins, themes, templates-db, and global-styles full R/W endpoints (now use WP Core REST API)
- **Removed:** PluginInventoryController, ThemeInventoryController, DBTemplateController + managers (6 files)
- **Retained:** code-snippets, global-styles/css, global-styles/json, global-styles/debug, global-styles/repair, search-content, ACF, system, health, audit, logs
- **Endpoint count:** Reduced from 27 to 18 value-add endpoints

### v0.5.0 (v6.0 Milestone — Phase 34-35)
- **Auth migration:** Replaced custom Bearer token with WordPress Application Passwords (Basic Auth)
- **Removed:** Rate limiting system, API key management, `/auth/*` endpoints
- **Removed:** File-based endpoints (`/snippets`, `/templates`, `/backups`, `/files/list`, `/custom-css`)
- **Removed:** 19 dead source files, 26 dead test files — plugin reduced from 55 to 36 source files
- **Updated:** Audit trail records `user_id` + `username` instead of API key hash
- **Fixed:** `uninstall.php` no longer drops audit table during plugin update workflow

### v0.4.0
- `POST /code-snippets/{id}/deploy` — atomic deploy (deactivate + activate + verify)
- `GET/PATCH /global-styles/json` — read/merge JSON without 170KB CSS payload
- `POST /system/flush-cache` — SpeedyCache + WP object cache + transients
- Fix: Code Snippets v6+ `insert_snippet()` column names

### v0.3.0
- Global Styles management (GET/PATCH `/global-styles`, `/global-styles/css`)
- Global Styles debug and repair endpoints
- Post content search (`/posts/search-content`)

### v0.2.0
- Code Snippets duplicate cleanup endpoint

### v1.1 (Phases 11-14)
- Custom CSS management, Database template CRUD, Code Snippets integration

### v1.0 (Phases 1-10)
- Core file operations, Post/ACF management, Admin dashboard, Health/observability

---

## Support

For issues or questions, refer to the plugin source code or contact the development team.
