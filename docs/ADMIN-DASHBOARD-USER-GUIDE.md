# AI Gateway Admin Dashboard - User Guide

**Tiếng Việt: [Hướng dẫn cho Người Dùng](#tiếng-việt)**

---

## English - Administrator Guide

### Overview

The AI Gateway Admin Dashboard provides a secure web interface for WordPress site administrators to manage the AI Gateway plugin. This guide walks you through each feature and how to use it.

**Access:** WordPress Admin → **AI Gateway** menu item

---

## Quick Start

1. **Login to WordPress Admin** as a user with Administrator role
2. **Click "AI Gateway"** in the left sidebar menu
3. **Dashboard loads** with the Overview tab active
4. **Switch tabs** by clicking the tab buttons (Overview, API Keys, Audit Log, Health, Settings)

---

## Overview Tab

The Overview tab gives you a quick snapshot of your AI Gateway system.

### Rate Limit Status

- **Shows:** Current API request usage this minute
- **Format:** "23 / 60 requests in current minute"
- **Progress Bar:** Visual indication of usage
  - Green to Yellow gradient
  - When full (60 requests), new requests are rejected temporarily
- **Resets:** Every minute on the minute

### Recent Activity

- **Shows:** Last 5 API requests (mutations)
- **Columns:**
  - **Endpoint:** The API path that was called (e.g., `/snippets/name.php`)
  - **Method:** HTTP method (GET, POST, PATCH, DELETE)
    - GET = blue badge (safe, read-only)
    - POST = green badge (create)
    - PATCH = yellow badge (update)
    - DELETE = red badge (delete)
  - **Status:** HTTP status code
    - 2xx codes = green (success)
    - 4xx codes = red (client error)
    - 5xx codes = red (server error)
  - **Time:** When the request occurred (e.g., "2 minutes ago")

### System Health

- **Badge:** Shows overall system health
  - Green **OK** = All systems operational
  - Red **ERROR** = One or more issues detected
- **What it checks:** PHP version, WordPress version, REST API, Database, Filesystem

---

## API Keys Tab

This tab allows you to generate and manage API authentication keys.

### Generate New Key

1. **Click "Generate New Key"** button
2. **Modal dialog appears** with:
   - ⚠️ Warning message: "Copy this key now. It will not be shown again"
   - Full API key displayed
   - "Copy to Clipboard" button
3. **Copy the key** immediately (essential!)
4. **Store safely** in your application or secure location
5. **Close the dialog** when done

### Manage Existing Keys

The table shows all API keys you've generated:

- **Key ID:** Unique identifier for this key (e.g., `key_1`)
- **Suffix:** Last 8 characters only (e.g., `****...a9d7c3f`)
  - Full key never displayed in this list for security
- **Created:** When the key was generated
- **Last Used:** When this key was last used to make an API request
  - "Never" if not yet used
- **Status:**
  - Green **Active** = Can be used for API calls
  - Red **Revoked** = Cannot be used, is disabled
- **Action:**
  - **Revoke:** Disables this key immediately
  - Greyed out for already-revoked keys

### Revoking Keys

1. **Click "Revoke"** button on the key you want to disable
2. **Confirmation dialog** appears
3. **Confirm** you want to revoke
4. **Key is immediately disabled**
5. **Status changes** to "Revoked" (red)
6. **Any API calls** with this key will be rejected

**Note:** Revocation cannot be undone. If you need the key again, generate a new one.

---

## Audit Log Tab

The Audit Log shows a complete history of all mutations (changes) made through the AI Gateway API.

### Viewing Entries

The table displays:

- **Endpoint:** Which API route was accessed (e.g., `/snippets/name.php`)
- **Method:** Type of operation (GET, POST, PATCH, DELETE) - see Overview tab for colors
- **Status:** Result of the request (HTTP status code)
  - 200/201 = success (green)
  - 400/404 = client error (red)
  - 422 = validation error (red)
  - 500 = server error (red)
- **Timestamp:** When the request occurred (formatted as date/time)
- **Action Type:** What kind of operation (e.g., "update_post", "create_snippet")

### Pagination

- **Per page:** Choose 25, 50, or 100 entries per page
- **Previous/Next buttons:** Navigate between pages
- **Page indicator:** Shows current page number

### Filtering

1. **Endpoint filter:**
   - Type an endpoint path to search for
   - Example: `snippets` (case-sensitive)

2. **Status filter:**
   - Dropdown with options:
     - "All statuses" = show everything
     - "Success (2xx)" = only successful requests
     - "Error (4xx-5xx)" = only failed requests

3. **Apply Filters button:**
   - Click after selecting filters
   - Table updates to show only matching entries

### Use Cases

- **Troubleshooting:** Find failed requests by filtering "Error (4xx-5xx)"
- **Activity Tracking:** See who accessed what and when
- **Compliance:** Audit trail for security and governance
- **Performance:** Identify high-volume endpoints

---

## Health Tab

The Health tab shows the status of your infrastructure and dependencies.

### Status Grid

Shows 6 infrastructure checks. Each displays:

- **Component name** (e.g., "PHP Version")
- **Status badge:**
  - Green **OK** = Working correctly, no action needed
  - Yellow **Warning** = Functioning but may have issues (e.g., ACF plugin not installed)
  - Red **ERROR** = Not working, attention needed

The 6 components are:

1. **PHP Version**: PHP 8.0+ is required
   - Green = 8.0 or higher installed
   - Red = Below 8.0 (not supported)

2. **WordPress Version**: WordPress 6.0+ is required
   - Green = 6.0 or higher
   - Red = Below 6.0 (not supported)

3. **REST API**: WordPress REST API must be accessible
   - Green = Working
   - Red = Disabled or blocked

4. **Filesystem**: Plugin must be able to read/write files
   - Green = File permissions correct
   - Red = Permission errors (contact host)

5. **Database**: WordPress database connectivity
   - Green = Connected
   - Red = Connection failed (critical)

6. **ACF Plugin**: Advanced Custom Fields (optional)
   - Green = Installed and active
   - Yellow = Not installed (optional, some features won't work)

### Version Information

Table showing:

- **Gateway Version**: AI Gateway plugin version
- **PHP Version**: Server's PHP version
- **WordPress Version**: Your WordPress version
- **Active Theme**: Currently active WordPress theme name
- **Installed Plugins**: Count of all installed plugins

---

## Settings Tab

The Settings tab allows you to configure plugin behavior.

### Available Settings

#### Debug Logging
- **Checkbox:** Enable/disable
- **What it does:** Records detailed debug information to WordPress debug.log
- **Use when:** Troubleshooting problems, reporting bugs
- **Warning:** May impact performance; disable after debugging

#### Max Backup Versions per File
- **Input field:** Number (1-10)
- **Default:** 3
- **What it does:** How many backup versions to keep for each file
- **Why:** Prevents excessive disk space usage
- **Example:** If set to 3, oldest backup is deleted when 4th backup created

#### Clean Old Backups on Deactivation
- **Checkbox:** Enable/disable
- **Default:** Disabled
- **What it does:** Automatically delete all backup files when plugin is deactivated
- **Use when:** Conserving disk space when uninstalling plugin
- **Warning:** Backups are permanently deleted

### Saving Changes

1. **Adjust settings** as desired
2. **Click "Save Settings"** button
3. **Success message** appears: "Settings saved successfully"
4. **Settings take effect** immediately
5. **Verify after reload:** Settings persist across page refreshes

### Validation

- **Numeric validation:** Max Backups field only accepts numbers 1-10
- **Error message** appears if you enter invalid value
- **Cannot save** with invalid data

---

## Troubleshooting

### Dashboard Won't Load

**Problem:** Page shows "You do not have permission to access this page"

**Solution:**
- Verify you're logged in as Administrator
- Check with WordPress site owner if your role is correct
- Ensure AI Gateway plugin is activated (Plugins → AI Gateway → Active)

### API Key Generation Fails

**Problem:** "Generate New Key" button doesn't work

**Solution:**
- Ensure you're admin (see above)
- Check WordPress debug.log for errors
- Try refreshing the page
- Contact site owner

### Settings Don't Save

**Problem:** Settings revert after save

**Solution:**
- Check for error message (may appear briefly)
- Verify numeric values are in range 1-10
- Check WordPress wp-options table has write permission
- Review WordPress debug.log

### Can't See Recent Activity

**Problem:** Overview tab shows "No recent activity"

**Solution:**
- No API requests have been made yet
- This is normal for new installations
- Make some API requests, then activity will appear

### Tables Show No Data

**Problem:** Audit Log, Health, or other tabs show empty

**Solution:**
- Initial load may take 1-2 seconds
- Refresh the page
- Check browser console for errors (F12 → Console)
- Verify REST API is accessible (Health tab)

---

## Security Notes

### Protecting Your API Keys

1. **Keys are shown only once** when generated
2. **Copy immediately** - they won't be shown again
3. **Store securely** - don't commit to version control
4. **Use environment variables** or secure vaults
5. **Revoke unused keys** regularly
6. **Never share keys** via email or chat

### Access Control

- **Only Administrators** can access this dashboard
- **Non-admins see 403 Forbidden** if they try to access directly
- **All changes logged** in the Audit Log for accountability

### What's NOT Visible

- Plaintext API keys in lists (only suffix shown)
- Sensitive data in timestamps
- Authentication tokens in browser history
- Request/response bodies in audit log

---

## Contact & Support

For issues with the AI Gateway Admin Dashboard:

1. **Check the Health tab** - confirms all systems operational
2. **Review Audit Log** - see if error messages appear
3. **Contact WordPress Administrator** - they can help troubleshoot
4. **Enable Debug Logging** (Settings tab) and review `/wp-content/debug.log`

---

---

## Tiếng Việt - Hướng dẫn cho Quản trị viên

### Tổng quan

Bảng điều khiển AI Gateway Admin cung cấp giao diện web an toàn để quản lý plugin AI Gateway. Hướng dẫn này giải thích cách sử dụng từng tính năng.

**Truy cập:** WordPress Admin → Menu **AI Gateway**

---

## Tổng quan nhanh

1. **Đăng nhập WordPress Admin** với tài khoản Administrator
2. **Nhấp vào "AI Gateway"** trong menu bên trái
3. **Bảng điều khiển tải** với tab Overview
4. **Chuyển đổi tab** bằng cách nhấp các nút (Overview, API Keys, Audit Log, Health, Settings)

---

## Tab Tổng quan (Overview)

Tab Tổng quan hiển thị tình trạng hệ thống AI Gateway.

### Trạng thái Giới hạn Rate Limit

- **Hiển thị:** Mức sử dụng API yêu cầu phút này
- **Định dạng:** "23 / 60 requests in current minute"
- **Thanh tiến trình:** Chỉ báo trực quan
  - Xanh lá sang vàng
  - Khi đầy (60 yêu cầu), yêu cầu mới bị từ chối tạm thời

### Hoạt động gần đây

- **Hiển thị:** 5 yêu cầu API gần nhất
- **Cột:**
  - **Endpoint:** Đường dẫn API (ví dụ: `/snippets/name.php`)
  - **Method:** Phương thức HTTP (GET, POST, PATCH, DELETE)
  - **Status:** Mã trạng thái HTTP (200, 400, 500, v.v.)
  - **Time:** Khi yêu cầu xảy ra

### Sức khỏe hệ thống

- **Badge:** Hiển thị sức khỏe chung
  - Xanh **OK** = Tất cả hệ thống hoạt động
  - Đỏ **ERROR** = Có vấn đề cần chú ý

---

## Tab Khóa API (API Keys)

Tab này cho phép tạo và quản lý khóa xác thực API.

### Tạo khóa mới

1. **Nhấp "Generate New Key"**
2. **Hộp thoại xuất hiện** với cảnh báo và khóa đầy đủ
3. **Sao chép khóa** ngay lập tức (rất quan trọng!)
4. **Lưu trữ an toàn** trong ứng dụng hoặc kho bí mật
5. **Đóng hộp thoại** khi xong

### Quản lý khóa hiện có

Bảng hiển thị tất cả khóa API:

- **Key ID:** Mã định danh duy nhất
- **Suffix:** 8 ký tự cuối cùng (không hiển thị khóa đầy đủ vì lý do bảo mật)
- **Created:** Khi khóa được tạo
- **Last Used:** Khi khóa này được sử dụng lần cuối
- **Status:** Trạng thái hiện tại
  - Xanh **Active** = Có thể sử dụng
  - Đỏ **Revoked** = Bị vô hiệu hóa
- **Action:** Nút Revoke để vô hiệu hóa

### Thu hồi khóa

1. **Nhấp "Revoke"** trên khóa cần tắt
2. **Hộp thoại xác nhận** xuất hiện
3. **Xác nhận** thu hồi
4. **Khóa bị vô hiệu hóa** ngay lập tức
5. **Trạng thái chuyển** thành "Revoked" (đỏ)

---

## Tab Nhật ký Kiểm tra (Audit Log)

Tab Audit Log hiển thị lịch sử đầy đủ tất cả các thay đổi.

### Xem mục

Bảng hiển thị:
- **Endpoint:** Đường dẫn API
- **Method:** Loại hoạt động
- **Status:** Mã trạng thái HTTP
- **Timestamp:** Khi yêu cầu xảy ra
- **Action Type:** Loại hoạt động (ví dụ: "update_post")

### Phân trang

- **Per page:** Chọn 25, 50 hoặc 100 mục
- **Nút Previous/Next:** Điều hướng trang
- **Chỉ báo trang:** Hiển thị trang hiện tại

### Lọc

1. **Lọc Endpoint:** Nhập đường dẫn để tìm kiếm
2. **Lọc Status:** Chọn "All statuses", "Success", hoặc "Error"
3. **Nhấp Apply Filters:** Cập nhật bảng

---

## Tab Sức khỏe (Health)

Tab Health hiển thị trạng thái cơ sở hạ tầng và phụ thuộc.

### Lưới Trạng thái

Hiển thị 6 kiểm tra:
- **PHP Version:** Phiên bản PHP
- **WordPress Version:** Phiên bản WordPress
- **REST API:** API hoạt động
- **Filesystem:** Quyền file
- **Database:** Kết nối cơ sở dữ liệu
- **ACF Plugin:** Plugin Advanced Custom Fields (tùy chọn)

Mỗi mục hiển thị:
- Xanh **OK** = Hoạt động bình thường
- Vàng **Warning** = Có cảnh báo
- Đỏ **ERROR** = Lỗi, cần chú ý

### Thông tin Phiên bản

Bảng hiển thị:
- **Gateway Version:** Phiên bản plugin
- **PHP Version:** Phiên bản PHP
- **WordPress Version:** Phiên bản WordPress
- **Active Theme:** Theme đang hoạt động
- **Installed Plugins:** Số plugin được cài đặt

---

## Tab Cài đặt (Settings)

Tab Settings cho phép cấu hình hành vi plugin.

### Cài đặt có sẵn

#### Debug Logging
- **Hộp kiểm:** Bật/tắt
- **Tác dụng:** Ghi lại thông tin gỡ lỗi chi tiết
- **Khi nào:** Khi khắc phục sự cố

#### Max Backup Versions per File
- **Trường nhập:** Số (1-10)
- **Mặc định:** 3
- **Tác dụng:** Số lượng bản sao lưu cần giữ
- **Ví dụ:** Khi đặt thành 3, bản sao lưu cũ nhất bị xóa khi tạo bản sao thứ 4

#### Clean Old Backups on Deactivation
- **Hộp kiểm:** Bật/tắt
- **Tác dụng:** Tự động xóa bản sao lưu khi plugin tắt
- **Cảnh báo:** Bản sao lưu bị xóa vĩnh viễn

### Lưu thay đổi

1. **Điều chỉnh cài đặt** theo mong muốn
2. **Nhấp "Save Settings"**
3. **Thông báo thành công** xuất hiện
4. **Cài đặt có hiệu lực** ngay lập tức

---

## Khắc phục sự cố

### Bảng điều khiển không tải

**Vấn đề:** Trang hiển thị "You do not have permission to access this page"

**Giải pháp:**
- Kiểm tra bạn đã đăng nhập với tài khoản Administrator
- Yêu cầu chủ sở hữu trang web xác minh vai trò
- Đảm bảo plugin AI Gateway được kích hoạt

### Tạo khóa API không thành công

**Vấn đề:** Nút "Generate New Key" không hoạt động

**Giải pháp:**
- Kiểm tra bạn là quản trị viên
- Xem debug.log để tìm lỗi
- Tải lại trang
- Liên hệ chủ sở hữu trang web

### Cài đặt không lưu

**Vấn đề:** Cài đặt quay lại sau khi lưu

**Giải pháp:**
- Kiểm tra thông báo lỗi
- Kiểm tra giá trị số nằm trong phạm vi 1-10
- Kiểm tra quyền ghi vào wp-options

---

## Ghi chú Bảo mật

### Bảo vệ khóa API

1. **Khóa chỉ hiển thị một lần** khi tạo
2. **Sao chép ngay lập tức** - sẽ không hiển thị lại
3. **Lưu trữ an toàn** - không commit vào version control
4. **Thu hồi khóa không sử dụng** thường xuyên
5. **Không chia sẻ khóa** qua email hoặc chat

### Kiểm soát truy cập

- **Chỉ Quản trị viên** có thể truy cập bảng điều khiển
- **Người dùng khác thấy 403** nếu cố gắng truy cập trực tiếp
- **Tất cả thay đổi được ghi** trong Audit Log

---

## Liên hệ & Hỗ trợ

Để cần giúp đỡ:

1. **Kiểm tra tab Health** - xác nhận tất cả hệ thống hoạt động
2. **Xem Audit Log** - kiểm tra thông báo lỗi
3. **Liên hệ Quản trị viên WordPress** - họ có thể giúp khắc phục sự cố
4. **Bật Debug Logging** (tab Settings) và xem `/wp-content/debug.log`

---

*Hướng dẫn này được cập nhật: 2026-03-11*
*Phiên bản: 1.0*
