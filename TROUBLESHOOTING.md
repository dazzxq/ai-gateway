# Troubleshooting Guide - AI Gateway for WordPress

**Hướng Dẫn Khắc Phục Sự Cố và Hiệu Suất**

---

## Lỗi Thường Gặp (Common Errors)

### Error 401 Unauthorized

**Nguyên Nhân:**
- API Key không hợp lệ
- Thiếu Authorization header
- Key hết hạn hoặc bị xóa

**Cách Sửa:**
1. Kiểm tra Authorization header: `Authorization: Bearer YOUR_API_KEY`
2. Đảm bảo không có khoảng trắng thêm ở đầu/cuối key
3. Tạo API Key mới:
   - Vào **WordPress Admin > Settings > AI Gateway**
   - Nhấp **Generate New API Key**
   - Sao chép khóa mới

**Ví Dụ - Sai:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health"
# Missing Authorization header
```

**Ví Dụ - Đúng:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
  -H "Authorization: Bearer abc123def456..."
```

---

### Error 403 Forbidden

**Nguyên Nhân:**
- Đường dẫn tệp không nằm trong danh sách trắng
- Thư mục không được phép truy cập
- Cố gắng truy cập tệp hệ thống/WordPress

**Cách Sửa:**
1. Kiểm tra đường dẫn tệp:
   - Các snippets phải trong: `/wp-content/mu-plugins/custom-snippets/`
   - Templates phải trong: `/wp-content/themes/` hoặc `/wp-content/blocks/`
2. Xem danh sách thư mục được phép: Xem **API-SPEC.md**
3. Liên hệ admin để thêm thư mục mới (nếu cần)

**Debug:**
```bash
# Kiểm tra xem tệp có tồn tại không
ls -la /path/to/file.php

# Kiểm tra quyền tệp
stat /path/to/file.php
```

---

### Error 404 Not Found

**Nguyên Nhân:**
- Tệp/bài viết không tồn tại
- Đường dẫn sai
- Tệp bị xóa

**Cách Sửa:**
1. Kiểm tra tên tệp/ID:
   ```bash
   # Liệt kê tất cả snippets
   curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/snippets" \
     -H "Authorization: Bearer YOUR_API_KEY"
   ```
2. Kiểm tra tên chính xác (case-sensitive)
3. Tạo tệp nếu chưa tồn tại:
   ```bash
   curl -X POST "https://your-site.com/wp-json/ai-gateway/v1/snippets" \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"name": "new-file.php", "content": "<?php // test ?>"}'
   ```

---

### Error 422 Unprocessable Entity

**Nguyên Nhân:**
- Dữ liệu không hợp lệ (cú pháp PHP sai, JSON sai)
- Trường bắt buộc bị thiếu
- Định dạng không đúng

**Cách Sửa - PHP Syntax Error:**
```bash
# Sai - Cú pháp PHP không hợp lệ
curl -X POST "https://your-site.com/wp-json/ai-gateway/v1/snippets" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "test.php", "content": "<?php echo \"hello; ?>"}'  # Missing closing quote

# Đúng - Cú pháp hợp lệ
curl -X POST "https://your-site.com/wp-json/ai-gateway/v1/snippets" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "test.php", "content": "<?php echo \"hello\"; ?>"}'
```

**Debug:**
1. Kiểm tra cú pháp PHP cục bộ:
   ```bash
   php -l /path/to/file.php
   ```
2. Xem chi tiết lỗi trong response:
   - Response chứa dòng lỗi (line number)
   - Sửa dòng được chỉ định

---

### Error 429 Too Many Requests

**Nguyên Nhân:**
- Vượt quá giới hạn 60 yêu cầu/phút per API key

**Cách Sửa:**
1. **Ngừng gửi yêu cầu** - Đợi 1 phút để reset bộ đếm
2. **Xem Retry-After header:**
   ```bash
   curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -v  # Xem headers
   ```
3. **Giảm tần suất yêu cầu:**
   - Batch requests khi có thể
   - Sử dụng pagination (per_page parameter)
   - Thêm delay giữa requests

**Ví Dụ Đúng - Pagination:**
```bash
# Thay vì 100 requests riêng
# Lấy 100 items trong 1 request
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/posts?per_page=100&page=1" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

---

### Error 500 Server Error

**Nguyên Nhân:**
- PHP fatal error
- Exception không xử lý
- Vấn đề cơ sở dữ liệu
- Memory/timeout

**Cách Sửa:**
1. **Bật Debug Logging:**
   ```php
   // wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Xem debug.log:**
   ```bash
   curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/logs?lines=50" \
     -H "Authorization: Bearer YOUR_API_KEY"
   ```

3. **Kiểm tra logs file:**
   ```bash
   tail -50 /wp-content/debug.log
   ```

4. **Liên hệ admin** với:
   - Thời gian lỗi xảy ra
   - Yêu cầu được gửi (endpoint, method)
   - Full error message từ debug.log

---

## Kiểm Tra Sức Khỏe (Health Check)

### Endpoint: GET /health

**Mô Tả:** Kiểm tra tất cả các dependency API

**Curl:**
```bash
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "checks": {
      "database": "connected",
      "filesystem": "writable",
      "plugins": "all_active",
      "rate_limit": "operational"
    }
  },
  "timestamp": "2026-03-10T12:34:56Z"
}
```

**Giải Thích:**
- `database`: WordPress DB connection OK
- `filesystem`: Có thể ghi tệp vào thư mục cho phép
- `plugins`: ACF Pro và dependencies hoạt động
- `rate_limit`: Rate limiter hoạt động bình thường

---

## Debug Logging

### Bật WP_DEBUG

**File: wp-config.php**
```php
// Enable debug mode
define('WP_DEBUG', true);

// Save debug to file (not displayed)
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Detailed logging
define('SCRIPT_DEBUG', true);
```

### Xem Logs qua API

**Endpoint:** `GET /logs`

**Parameters:**
```
lines=50            # Số dòng (mặc định: 20)
level=error,warning # Lọc theo level
search=keyword      # Tìm kiếm
```

**Curl:**
```bash
# Xem 100 dòng log gần nhất
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/logs?lines=100" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Xem chỉ errors
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/logs?level=error" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Tìm kiếm lỗi cụ thể
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/logs?search=parse+error" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

---

## Tính Năng Hiệu Suất (Performance Expectations)

### Typical Response Times
| Endpoint | Thời Gian Trung Bình | Max |
|----------|-------------------|-----|
| GET /health | <100ms | 500ms |
| GET /snippets | 50-200ms | 1s |
| GET /snippets/{name} | 50-100ms | 500ms |
| POST /snippets | 100-300ms | 2s (PHP lint) |
| PATCH /snippets | 150-400ms | 2s (backup + lint) |
| GET /posts | 100-500ms | 2s (large DB) |
| PATCH /posts | 200-800ms | 3s |

### Rate Limit Behavior
- **Limit:** 60 requests/phút per API key
- **Window:** Sliding 60-second window
- **Reset:** Tự động sau 60 giây
- **Header:** `Retry-After` (thời gian chờ tính bằng giây)

**Test Rate Limit:**
```bash
#!/bin/bash
# Test script - gửi 61 requests
for i in {1..61}; do
  echo "Request $i..."
  RESPONSE=$(curl -s -w "\n%{http_code}" \
    -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
    -H "Authorization: Bearer YOUR_API_KEY")

  HTTP_CODE=$(echo "$RESPONSE" | tail -1)
  if [ "$HTTP_CODE" = "429" ]; then
    echo "✓ Rate limit hit on request $i (expected)"
    break
  fi
done
```

### File Size Limits
- **Snippet files:** Típ < 5MB (soft limit)
- **Backup size:** ~3MB per snippet (3 versions = ~9MB)
- **Total backups:** ~30MB (10 snippets × 3 versions)

### Large File Handling
```bash
# Large log file
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/logs?lines=1000" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  > large-log.json
```

---

## Hướng Dẫn Kiểm Tra Thủ Công (Manual Testing Guide)

### 1. Kiểm Tra Cài Đặt (Installation Verification)
```bash
# Step 1: Kiểm tra plugin đã kích hoạt
curl -I -X GET "https://your-site.com/wp-json/ai-gateway/v1/health"
# Expecting: HTTP/1.1 401 Unauthorized (no key yet) - OK

# Step 2: Tạo API key (từ WordPress Admin)
# Settings > AI Gateway > Generate New API Key
# Sao chép key

# Step 3: Kiểm tra auth hoạt động
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
  -H "Authorization: Bearer YOUR_API_KEY"
# Expecting: 200 OK - ✓ Installation OK
```

### 2. Kiểm Tra API Key (API Key Verification)
```bash
# Mỗi API key nên tạo request HTTP 200 với health endpoint
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -w "\nHTTP Status: %{http_code}\n"
# Expected: HTTP Status: 200
```

### 3. Kiểm Tra Thời Gian Response (Response Time Testing)
```bash
# Time a simple request
time curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Should be <500ms for typical operation
# If >1000ms, something is slow
```

### 4. Kiểm Tra Giới Hạn Tốc Độ (Rate Limit Testing)
```bash
#!/bin/bash
# Send 61 rapid requests
KEY="your_api_key_here"
for i in {1..61}; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
    -X GET "https://your-site.com/wp-json/ai-gateway/v1/health" \
    -H "Authorization: Bearer $KEY")

  if [ "$STATUS" = "429" ]; then
    echo "✓ Request $i: Rate limited (expected at 61)"
    exit 0
  elif [ "$STATUS" != "200" ]; then
    echo "✗ Request $i: Unexpected status $STATUS"
    exit 1
  fi

  # Show progress every 10 requests
  [ $((i % 10)) -eq 0 ] && echo "  $i requests OK..."
done

echo "✗ Rate limit not enforced!"
exit 1
```

### 5. Kiểm Tra Tạo Tệp (File Creation Testing)
```bash
# Create a snippet
curl -X POST "https://your-site.com/wp-json/ai-gateway/v1/snippets" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "test-'$(date +%s)'.php",
    "content": "<?php echo \"Test on \"..date(\"Y-m-d H:i:s\"); ?>"
  }'

# Should return 201 Created
# Response should include backup_version: 1
```

### 6. Kiểm Tra Sao Lưu (Backup Testing)
```bash
# Create snippet
RESPONSE=$(curl -s -X POST "https://your-site.com/wp-json/ai-gateway/v1/snippets" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "test-backup.php", "content": "<?php echo \"v1\"; ?>"}')

# Update snippet (should create backup v2)
curl -X PATCH "https://your-site.com/wp-json/ai-gateway/v1/snippets/test-backup.php" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"content": "<?php echo \"v2\"; ?>"}'

# List backups
curl -X GET "https://your-site.com/wp-json/ai-gateway/v1/snippets/test-backup.php/backups" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Should show 2 versions (v1, v2)
```

---

## Contact & Support

- **Email:** support@your-site.com
- **Documentation:** See README.md and API-SPEC.md
- **Issues:** Contact admin with:
  - Error message
  - HTTP status code
  - Endpoint and parameters used
  - Time the issue occurred
  - Relevant logs (from GET /logs)

---

**Last Updated:** 2026-03-10
**Version:** 1.0.0
**Language:** Vietnamese (Tiếng Việt)
