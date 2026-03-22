# AI Gateway for WordPress

![Version](https://img.shields.io/badge/version-0.7.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B)
![License](https://img.shields.io/badge/license-MIT-green)

A WordPress plugin that provides a secure REST API for AI assistants and automation tools to manage site content.

## Features

- **Code Snippets Management** -- CRUD operations with atomic deploy and force recompile
- **Global Styles** -- Read and update CSS and JSON styles with deep merge support
- **Post Content Search** -- Substring search across post content
- **ACF Field Management** -- Update Advanced Custom Fields on posts
- **System Operations** -- Health checks, log reading, cache flushing, and system info
- **Admin Dashboard** -- Visual management interface at WP Admin > AI Gateway
- **Audit Logging** -- All mutations are logged with user identity and timestamps
- **Pre-flight Environment Checks** -- Automatic PHP version, WP version, and extension validation on activation

## Requirements

- PHP 8.0 or higher
- WordPress 6.0 or higher
- Optional: [Code Snippets](https://wordpress.org/plugins/code-snippets/) plugin (for snippet management endpoints; graceful 503 response when absent)

## Installation

1. Download the AI Gateway ZIP file
2. Go to **WordPress Admin > Plugins > Add New > Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Click **Activate**

No additional configuration is required. The plugin registers its REST API routes automatically on activation.

## Quick Start

### 1. Create an Application Password

1. Go to **WordPress Admin > Users > Profile**
2. Scroll to the **Application Passwords** section
3. Enter a name (e.g., "AI Gateway") and click **Add New Application Password**
4. Copy the generated password (spaces are optional -- they are stripped automatically)

### 2. Test the Connection

```bash
curl -u "your-username:your-app-password" \
  https://your-site.com/wp-json/ai-gateway/v1/health
```

Expected response:

```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "php_version": "8.4.0",
    "wp_version": "6.9.4",
    "plugin_version": "0.6.0"
  },
  "timestamp": "2026-01-01T00:00:00Z"
}
```

### 3. List Code Snippets

```bash
curl -u "your-username:your-app-password" \
  https://your-site.com/wp-json/ai-gateway/v1/code-snippets
```

## API Overview

All endpoints are under the `/wp-json/ai-gateway/v1` namespace. Authentication is required for all endpoints except `/health`.

### Code Snippets

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/code-snippets` | GET, POST | List and create snippets |
| `/code-snippets/{id}` | GET, PATCH, DELETE | Read, update, or delete a snippet |
| `/code-snippets/{id}/deploy` | POST | Atomic deploy (deactivate + activate + verify) |
| `/code-snippets/{id}/toggle` | POST | Force recompile (toggle off/on) |
| `/code-snippets/cleanup-duplicates` | DELETE | Remove duplicate inactive snippets |

### Global Styles

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/global-styles/css` | GET, PATCH | Read or update Additional CSS only |
| `/global-styles/json` | GET, PATCH | Read or deep-merge JSON styles (CSS preserved) |
| `/global-styles/debug` | GET | Diagnostic information |
| `/global-styles/repair` | POST | Fix corrupted JSON structure |

### Posts and ACF

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/posts/{id}/acf/{field}` | PATCH | Update an ACF field on a post |
| `/posts/search-content` | GET | Search post content by substring |
| `/acf/field-groups` | GET | List ACF field groups |

### System

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/health` | GET | Health check (no authentication required) |
| `/system-info` | GET | PHP, WordPress, and server information |
| `/logs` | GET | Read debug and error logs |
| `/system/flush-cache` | POST | Flush all caches (object cache, transients) |

### Admin

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/audit` | GET | Query the audit trail |
| `/admin/settings` | GET, PATCH | Read or update plugin settings |

For full endpoint documentation including request/response schemas, see [API-REFERENCE.md](API-REFERENCE.md).

## Authentication

AI Gateway uses **WordPress Application Passwords** with HTTP Basic Auth. This is the same authentication mechanism used by the WordPress core REST API.

```
Authorization: Basic base64(username:application_password)
```

With curl, use the `-u` flag:

```bash
curl -u "username:app_password" https://your-site.com/wp-json/ai-gateway/v1/...
```

**Requirements:**

- The authenticated user must have the **Administrator** role (`manage_options` capability)
- Application Passwords must be enabled on your WordPress installation (enabled by default since WordPress 5.6)
- HTTPS is strongly recommended (Application Passwords are transmitted in cleartext over HTTP)

## Configuration

Plugin settings are available through:

- **WP Admin UI:** Navigate to **WP Admin > AI Gateway** for the admin dashboard
- **REST API:** Use `GET /admin/settings` to read and `PATCH /admin/settings` to update settings programmatically

Available settings include debug logging and backup version limits.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed development setup and guidelines.

Quick start for contributors:

```bash
cd wp-content/plugins/ai-gateway
composer install
composer phpstan   # Static analysis (level 5)
composer phpcs     # WordPress coding standards
composer test      # Run 125+ unit and integration tests
```

## Security

To report a security vulnerability, see [SECURITY.md](SECURITY.md).

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
