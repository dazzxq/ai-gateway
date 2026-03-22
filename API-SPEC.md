# API Specification - AI Gateway for WordPress

**v1.0.0 Complete API Reference**

---

## Giới Thiệu (Introduction)

### Xác Thực (Authentication)
- **Loại:** Bearer Token (OAuth 2.0 style)
- **Header:** `Authorization: Bearer {YOUR_API_KEY}`
- **Key Format:** 64-character hexadecimal string
- **Storage:** SHA-256 hash in WordPress wp_options

**Ví Dụ:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
  -H "Authorization: Bearer abc123def456...xyz789"
```

### Response Envelope
Tất cả responses tuân theo định dạng envelope tiêu chuẩn:

**Success (200/201/204):**
```json
{
  "success": true,
  "data": { ... },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

**Error (4xx/5xx):**
```json
{
  "success": false,
  "error": "error_code",
  "message": "Human readable error message",
  "timestamp": "2026-03-10T12:34:56Z"
}
```

### HTTP Status Codes
| Code | Nghĩa | Ví Dụ |
|------|-------|-------|
| 200 | OK - Request succeeded | GET, PATCH, DELETE |
| 201 | Created - Resource created | POST (success) |
| 204 | No Content - Request succeeded, no body | DELETE (success) |
| 400 | Bad Request - Invalid parameters | Missing required field |
| 401 | Unauthorized - Missing/invalid key | No Authorization header |
| 403 | Forbidden - Access denied | Path not in whitelist |
| 404 | Not Found - Resource missing | File doesn't exist |
| 422 | Unprocessable Entity - Validation error | Invalid PHP code |
| 429 | Too Many Requests - Rate limit exceeded | >60 req/min |
| 500 | Server Error - Unexpected error | PHP fatal error |

### Error Codes
```
auth_missing_header      - No Authorization header
auth_invalid_format      - Invalid Bearer format
auth_invalid_key         - Wrong API key
auth_rate_limit_exceeded - Rate limit hit (60/min)
path_not_whitelisted     - Path outside whitelist
path_traversal_detected  - Directory traversal attempt
validation_error         - Data validation failed
file_not_found           - File doesn't exist
permission_denied        - Permission check failed
```

---

## 1. Authentication - Tạo API Key

### POST /auth/generate-key

**Mô Tả:** Tạo API key mới (chỉ từ WordPress admin)

**Xác Thực:** WordPress admin login (cookie)

**Curl Example:**
```bash
curl -X POST "https://your-site.com/wp-json/ai-gateway/v1/auth/generate-key" \
  -H "Cookie: wordpress_logged_in=YOUR_ADMIN_COOKIE" \
  -H "Content-Type: application/json"
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "key": "abc123def456...64hex...xyz789",
    "hash": "sha256hashhere",
    "message": "Save this key immediately. It cannot be recovered."
  },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

---

## 2. File Operations - Snippets

### GET /snippets

**Mô Tả:** Liệt kê tất cả PHP snippets

**Xác Thực:** Yêu cầu Bearer token

**Parameters:**
```
page=1          (trang, mặc định: 1)
per_page=10     (mỗi trang, mặc định: 10)
```

**Curl Example:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/snippets?page=1&per_page=20" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "total": 5,
    "page": 1,
    "per_page": 10,
    "files": [
      {
        "name": "hello-world.php",
        "path": "/wp-content/mu-plugins/custom-snippets/hello-world.php",
        "size": 120,
        "modified": "2026-03-10T10:00:00Z"
      }
    ]
  },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

### POST /snippets

**Mô Tả:** Tạo PHP snippet mới

**Parameters (JSON body):**
```json
{
  "name": "filename.php",          // Bắt buộc
  "content": "<?php echo 'test'; ?>" // Bắt buộc
}
```

**Curl Example:**
```bash
curl -X POST "https://your-site.com/wp-json/ai-gateway/v1/snippets" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "custom-filter.php",
    "content": "<?php add_filter(\"the_content\", function($c) { return $c . \"<!-- custom -->\"; }); ?>"
  }'
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "name": "custom-filter.php",
    "path": "/wp-content/mu-plugins/custom-snippets/custom-filter.php",
    "size": 98,
    "backup_version": 1,
    "message": "Snippet created. Backup v1 created."
  },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

**Error Response (422):**
```json
{
  "success": false,
  "error": "validation_error",
  "message": "Parse error on line 1: unexpected '{' in test.php",
  "timestamp": "2026-03-10T12:34:56Z"
}
```

### GET /snippets/{name}

**Mô Tả:** Đọc nội dung snippet

**Curl Example:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/snippets/hello-world.php" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "name": "hello-world.php",
    "content": "<?php echo 'Hello World'; ?>",
    "size": 30,
    "modified": "2026-03-10T10:00:00Z"
  },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

### PATCH /snippets/{name}

**Mô Tả:** Cập nhật snippet (tự động sao lưu phiên bản cũ)

**Parameters (JSON body):**
```json
{
  "content": "<?php // updated code ?>"
}
```

**Curl Example:**
```bash
curl -X PATCH "https://your-site.com/wp-json/ai-gateway/v1/snippets/hello-world.php" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"content": "<?php echo \"Updated\"; ?>"}'
```

### DELETE /snippets/{name}

**Mô Tả:** Xóa snippet

**Curl Example:**
```bash
curl -X DELETE "https://your-site.com/wp-json/ai-gateway/v1/snippets/hello-world.php" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

---

## 3. Backup & Restore

### GET /snippets/{name}/backups

**Mô Tả:** Liệt kê các phiên bản sao lưu

**Curl Example:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/snippets/hello-world.php/backups" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "file": "hello-world.php",
    "backups": [
      {
        "version": 1,
        "created": "2026-03-10T09:00:00Z",
        "size": 28
      },
      {
        "version": 2,
        "created": "2026-03-10T10:00:00Z",
        "size": 35
      }
    ]
  },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

### POST /snippets/{name}/backups/{version}/restore

**Mô Tả:** Khôi phục từ phiên bản sao lưu

**Curl Example:**
```bash
curl -X POST "https://your-site.com/wp-json/ai-gateway/v1/snippets/hello-world.php/backups/1/restore" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "file": "hello-world.php",
    "restored_version": 1,
    "message": "File restored to backup version 1"
  },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

---

## 4. Posts Management

### GET /posts

**Mô Tả:** Liệt kê bài viết (với bộ lọc)

**Parameters:**
```
page=1              (trang, mặc định: 1)
per_page=10         (mỗi trang, mặc định: 10)
status=publish      (publish/draft/any)
category=tech       (category slug)
search=keyword      (tìm kiếm title/content)
```

**Curl Example:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/posts?status=publish&category=tech&page=1" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

### GET /posts/{id}

**Mô Tả:** Đọc bài viết (bao gồm ACF fields)

**Curl Example:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/posts/123" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

### PATCH /posts/{id}

**Mô Tả:** Cập nhật bài viết

**Parameters:**
```json
{
  "title": "New Title",
  "content": "Post content here"
}
```

---

## 5. System & Observability

### GET /system/info

**Mô Tả:** Thông tin hệ thống

**Curl Example:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/system/info" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "wordpress_version": "6.4.2",
    "php_version": "8.1.15",
    "mysql_version": "8.0.35",
    "server_software": "Apache/2.4.57",
    "memory_limit": "256M",
    "max_execution_time": 300
  },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

### GET /health

**Mô Tả:** Kiểm tra sức khỏe API

**Curl Example:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

---

## Rate Limiting

**Giới Hạn:** 60 requests/phút per API key

**Response Header (khi vượt quá giới hạn):**
```
HTTP/1.1 429 Too Many Requests
Retry-After: 45
```

**Response Body:**
```json
{
  "success": false,
  "error": "auth_rate_limit_exceeded",
  "message": "Rate limit exceeded: 60 requests per minute",
  "timestamp": "2026-03-10T12:34:56Z"
}
```

---

**API Version:** 1.0.0
**Last Updated:** 2026-03-10
**Documentation:** Vietnamese (Tiếng Việt)
