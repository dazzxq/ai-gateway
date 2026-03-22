# Changelog

All notable changes to AI Gateway for WordPress are documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.5.0] - 2026-03-22

### Thay Đổi Lớn (Breaking Changes)
- 🔄 **Auth migration:** Chuyển từ Bearer token sang WordPress Application Passwords (Basic Auth)
  - Dùng `curl -u "username:app_password"` thay vì `Authorization: Bearer <key>`
  - Tạo App Password tại WP Admin > Users > Profile > Application Passwords
  - Cùng một App Password xác thực cả AI Gateway và WP Core REST API

### Đã Xóa (Removed)
- ❌ Custom API key system (APIAuthenticator, API key generation, SHA-256 hashing)
- ❌ Rate limiting system (RateLimiter — Loginizer đã bảo vệ brute force)
- ❌ Admin dashboard tab "API Keys" (quản lý qua WP Users > App Passwords)
- ❌ File-based endpoints: `/snippets`, `/templates`, `/backups`, `/files/list` (không có SSH access)
- ❌ `/custom-css` endpoint (dùng `/global-styles/css` thay thế)
- ❌ `/auth/keys`, `/auth/generate-key`, `/rate-limit/status` endpoints
- ❌ 19 source files, 26 test files — plugin giảm từ 55 xuống 36 source files

### Cập Nhật (Changed)
- 🔄 PermissionChecker: zero-dependency, chỉ dùng `current_user_can('manage_options')`
- 🔄 AuditLogger: ghi `user_id` + `username` thay vì `api_key_hash`
- 🔄 uninstall.php: deployment-safe — không DROP TABLE, không xóa persistent options
- 🔄 Admin dashboard: 5 tabs (Overview, API Reference, Audit Log, Health, Settings)

### Sửa Lỗi (Fixed)
- 🐛 uninstall.php không còn xóa audit table khi deploy (Deactivate > Delete > Upload > Activate)

---

## [1.0.0] - 2026-03-10

### Thêm Mới (Added)

#### Phase 1: Nền Tảng Bảo Mật
- ✅ API Key authentication with SHA-256 hashing
- ✅ Constant-time comparison (hash_equals) to prevent timing attacks
- ✅ Path whitelisting for safe file access
- ✅ Request/response envelope standardization
- ✅ Permission checking framework
- ✅ WordPress activation/deactivation hooks

#### Phase 2: Hoạt Động Tệp
- ✅ PHP Snippet CRUD (Create, Read, Update, Delete)
- ✅ Block Template CRUD with HTML parsing
- ✅ Automatic PHP linting before file write
- ✅ Backup system with version control (max 3 versions)
- ✅ Backup restoration/rollback functionality
- ✅ Atomic file writes (prevent partial/corrupted files)
- ✅ Block introspection (parse block names and attributes)

#### Phase 3: Quản Lý Nội Dung
- ✅ Post listing with pagination
- ✅ Post filtering (status, category, search)
- ✅ Post detail view with all metadata
- ✅ ACF field integration (requires ACF Pro)
- ✅ Complex ACF structures (repeater, flexible content)
- ✅ ACF field update support
- ✅ Safe WordPress settings endpoint
- ✅ Audit trail logging for all mutations

#### Phase 4: Giám Sát & Giới Hạn
- ✅ Rate limiting (60 requests/minute per API key)
- ✅ Sliding window rate limit with per-key isolation
- ✅ Retry-After header in 429 responses
- ✅ System information endpoint (PHP, WordPress, MySQL versions)
- ✅ Plugin inventory with status tracking
- ✅ Theme inventory with active theme display
- ✅ Health check endpoint (database, filesystem, plugins)
- ✅ Debug log reader with filtering and large file handling
- ✅ Response formatter for consistent error messages

#### Phase 5: Kiểm Định & Tài Liệu
- ✅ Comprehensive security test suite (11 files, 30+ test methods)
- ✅ Unit test refactoring for 80%+ coverage
- ✅ Integration test enhancement (8+ workflow tests)
- ✅ Security audit with checklist verification (11 items)
- ✅ Vietnamese operator documentation (README, API-SPEC, TROUBLESHOOTING)
- ✅ v1.0.0 release notes and changelog
- ✅ Error code reference and troubleshooting guide
- ✅ Manual testing scripts and health check procedures

### Cố Định (Fixed)

- N/A - Initial release

### Bảo Mật (Security)

**All 11 Security Checklist Items Implemented & Tested:**

1. ✅ **API Key Validation** - Constant-time comparison, SHA-256 hashing
2. ✅ **Path Validation** - No directory traversal, no symlinks, strict whitelist
3. ✅ **Input Sanitization** - All request fields validated/sanitized
4. ✅ **Output Encoding** - All responses JSON-encoded, no XSS risk
5. ✅ **Authentication** - All endpoints require Bearer token
6. ✅ **Permission Checking** - Consistent permission enforcement
7. ✅ **File Permissions** - Created files not world-readable
8. ✅ **Secret Protection** - API keys never logged or exposed
9. ✅ **Rate Limiting** - 60 requests/minute per key with isolation
10. ✅ **Backup Integrity** - Backups protected from API deletion
11. ✅ **Error Safety** - No path or version disclosure in errors

**Security Testing:**
- 11 security test suites created
- Each security item has corresponding test file
- ~30 security test methods verifying implementation
- Comprehensive coverage of attack vectors

**Vulnerabilities & Mitigations:**
- **Timing Attacks:** Mitigated with hash_equals()
- **Path Traversal:** Blocked by realpath() + whitelist validation
- **XSS:** Prevented by JSON output encoding
- **SQL Injection:** Input sanitization via WordPress functions
- **Rate Limit Bypass:** Per-key isolation with minute window
- **Backup Compromise:** API endpoints don't expose backup deletion
- **Information Leakage:** Error messages sanitized, no version info

### Ghi Chú (Notes)

#### Requirements
- **PHP:** 8.0 or higher
- **WordPress:** 5.9 or higher
- **ACF Pro:** 6.0 or higher (for ACF field management)
- **Web Server:** Apache 2.4+ or Nginx 1.18+ with PHP-FPM

#### Constraints
- **SSH Access:** Plugin designed for no SSH/FTP access - dashboard only
- **No Load Testing:** SSH not available to VPS, performance testing manual
- **Test Framework:** Uses WP_UnitTestCase (existing WordPress test infrastructure)
- **Language:** Documentation in Vietnamese only (operator-focused, not developer-focused)

#### Architecture Decisions
- **Multi-file Plugin:** ~2000 lines organized in PSR-4 namespace structure
- **REST API:** WordPress native /wp-json/ endpoints with permission callbacks
- **File Operations:** Atomic writes, symlink validation, whitelist enforcement
- **Backup Storage:** Local filesystem with version control (max 3 per file)
- **Rate Limiting:** In-memory tracking with wp_transients (suitable for shared hosting)
- **Logging:** WordPress debug.log integration with structured audit trail

#### Known Limitations
- No support for large file uploads (>5MB recommended limit)
- ACF Pro required for complex field structures
- No real-time synchronization (API call per modification)
- Rate limit window is per-minute (not configurable)
- Backups stored on same server (not off-site)

#### Future Considerations
- Webhook support for event notifications
- OAuth 2.0 provider integration
- S3/cloud backup integration
- GraphQL API support
- Performance metrics collection

---

## Installation & Activation

### First Install
1. Download ZIP from release page
2. Upload via WordPress Admin: **Plugins > Add New > Upload Plugin**
3. Click **Install Now** → **Activate**
4. API key auto-generated on activation

### Updates
1. Deactivate current version: **Plugins > Installed Plugins > Deactivate**
2. Delete old version: **Plugins > Installed Plugins > Delete**
3. Upload new ZIP via **Plugins > Add New > Upload Plugin**
4. Click **Install Now** → **Activate**

### Configuration
- No configuration required for basic operation
- API key management via **Settings > AI Gateway**
- Debug logging via **wp-config.php** (WP_DEBUG constants)

---

## Support & Documentation

- **README.md** - Installation and quick start guide (Vietnamese)
- **API-SPEC.md** - Complete API reference with curl examples (Vietnamese)
- **TROUBLESHOOTING.md** - Error codes, debugging, performance guide (Vietnamese)
- **CHANGELOG.md** - This file

## Contributors

- AI Gateway Development Team
- WordPress Plugin Compatibility Testing
- Security Audit & Testing Team

---

**Version:** 1.0.0
**Release Date:** 2026-03-10
**Status:** Production Ready ✅
**Language:** Vietnamese (Tiếng Việt)
**License:** MIT
