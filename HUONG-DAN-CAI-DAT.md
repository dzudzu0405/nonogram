# Nonogram Studio — Hướng dẫn cài đặt lên cPanel

## Chuẩn bị
- Hosting cPanel có **PHP 8.1 trở lên** (bạn đang có 8.2) và **MySQL**.
- File cần upload: `dist/nonogram-studio-<phiên bản>.zip` (tạo bằng `python tools/build_zip.py`).

## Các bước

1. **Tạo database**
   cPanel → **MySQL® Databases**:
   - Tạo database, ví dụ `nonogram`. cPanel sẽ thêm tiền tố, thành `tenuser_nonogram`.
   - Tạo user với mật khẩu mạnh.
   - Ở mục *Add User To Database*, gán user vào database và chọn **ALL PRIVILEGES**.

2. **Upload code**
   cPanel → **File Manager** → mở `public_html` (hoặc thư mục của subdomain, ví dụ `app.tenmien.com`):
   - **Upload** file ZIP → chuột phải → **Extract**.
   - Xóa file ZIP sau khi giải nén.

3. **Tạo hộp thư gửi email** (để khách đặt lại được mật khẩu)
   cPanel → **Email Accounts** → **Create**, ví dụ `noreply@tenmien.com`. Lưu lại mật khẩu.
   Bấm **Connect Devices** ở hộp thư vừa tạo để xem *host* và *port* SMTP (thường là `mail.tenmien.com`, port `465`).

4. **Tạo file cấu hình**
   - Trong File Manager, **Copy** file `config.sample.php` thành `config.php`.
   - Chuột phải `config.php` → **Edit**, điền:
     - `app_url`: địa chỉ đầy đủ của site, ví dụ `https://app.tenmien.com` (không có dấu `/` ở cuối).
     - `db.name`, `db.user`, `db.pass`: thông tin database ở bước 1.
     - `app_key`: một chuỗi ngẫu nhiên dài (≥ 32 ký tự).
     - Khối `mail`: `host`, `port`, `encryption` (`ssl` cho port 465, `tls` cho 587), `username` và `from_email` là địa chỉ hộp thư ở bước 3, `password` là mật khẩu hộp thư.
     - `upgrade_urls`: link trang bán của từng gói nâng cấp (Pro, Vault…). Để trống gói nào thì nút nâng cấp của gói đó bị ẩn.
   - Giữ `'debug' => false` và `'open_registration' => false` trên site thật.

5. **Chạy trình cài đặt**
   - Mở `https://tenmien.com/install.php`.
   - Tất cả dòng phải có dấu ✓. Dòng nào có ✕ thì làm theo gợi ý ngay dưới dòng đó.
   - Nhập email và mật khẩu cho tài khoản **Admin**, rồi bấm **Install**. Trình cài đặt tự tạo các bảng trong database.
   - Cài xong, **xóa `install.php`** trong File Manager.

6. **Đăng nhập**: `https://tenmien.com/login.php`. Trang **Administration → Overview** sẽ báo nếu còn thiếu cấu hình (app_url, email, access link).

## Bán trên WarriorPlus: access link
WarriorPlus nhận tiền; app chỉ cần biết ai được dùng gì. Hai bên nối với nhau bằng **access link**: mỗi sản phẩm WarriorPlus một link.

**Cài đặt (một lần cho mỗi sản phẩm):**
1. **Administration → Access links → New link**.
2. Đặt tên theo sản phẩm WarriorPlus (ví dụ "Nonogram Studio FE"), tick sản phẩm mà link mở khóa (FE, Picture Vault, Pro…).
3. Bấm **Create link**, rồi **Copy** địa chỉ link.
4. Trong WarriorPlus, dán link vào sản phẩm đó ở ô **Delivery URL**.

**Khách mua sẽ thấy:**
1. Mua xong, WarriorPlus đưa khách tới access link.
2. Trang hiện những gì khách vừa mua, kèm nút **Create my account** hoặc **I already have an account**.
3. Tạo tài khoản hoặc đăng nhập xong là sản phẩm được mở ngay. Mua thêm gói khác thì mở link của gói đó.

Mở lại link cũ không làm mất hay thay đổi gì. Tài khoản chỉ tạo được khi khách đến từ access link (trừ khi bật `open_registration`).

**Nếu link bị lộ ra ngoài:** vào Access links → **New address**. Link cũ ngừng hoạt động ngay, khách đã mua trước đó vẫn giữ sản phẩm. Nhớ dán link mới vào WarriorPlus. Có thể đặt **giới hạn số lần dùng** và **ngày hết hạn** cho từng link. Mỗi lần dùng link đều được ghi lại kèm địa chỉ IP (Administration → Activity).

**Hoàn tiền:** WarriorPlus không báo hoàn tiền về app, nên làm tay: **Administration → Users** → tìm khách → bỏ tick sản phẩm, ghi lý do (ví dụ "Refund W+ #12345") → **Save products**. Project của khách không bị xóa.

## Quản trị
- **Users:** tìm theo tên/email, lọc theo sản phẩm, vai trò, trạng thái. Trong từng tài khoản: đổi sản phẩm, gửi link đặt lại mật khẩu, khóa/mở tài khoản, cấp quyền admin, xóa tài khoản (phải gõ đúng email).
- App không cho tự hạ quyền, tự khóa hay tự xóa tài khoản của chính mình, và không cho bỏ admin cuối cùng.
- Nên **khóa** thay vì xóa: khóa chỉ chặn đăng nhập, mở lại là mọi thứ như cũ.

## Cập nhật phiên bản mới
Upload ZIP mới → Extract đè lên file cũ. File `config.php` và thư mục `storage/` không có trong ZIP nên không bị ghi đè.
Bảng mới trong database (nếu có) được tạo tự động ở lần mở trang đầu tiên sau khi cập nhật. Sau khi cập nhật lên bản có access link, nhớ thêm `app_url`, khối `mail` và `upgrade_urls` vào `config.php` (xem `config.sample.php`).

## Bảo mật đã có sẵn
- `config.php`, các thư mục `lib/`, `install/`, `storage/` bị chặn truy cập từ web (bằng `.htaccess`).
- Mật khẩu được mã hóa, có chống CSRF, và khóa đăng nhập tạm thời khi nhập sai nhiều lần.
- Đăng nhập giữ được 30 ngày (session lưu trong `storage/sessions`), đăng xuất chỉ qua nút trong app.
- Link đặt lại mật khẩu dùng một lần, hết hạn sau 60 phút, và chỉ lưu dạng mã hóa.
- Mọi dữ liệu project và puzzle đều kiểm tra đúng chủ sở hữu trước khi đọc hoặc ghi.

## Chạy thử trên máy (cho lập trình)
```
php -S 127.0.0.1:8080 dev-router.php
```
Với `config.php` dùng SQLite: `'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/storage/dev.sqlite']`, `'debug' => true`, và `'mail' => ['driver' => 'log', 'from_email' => 'noreply@nonogram.test']` (email được ghi vào `storage/logs/mail.log` thay vì gửi đi).
Test: `node tests/engine.test.js`, `node tests/engine.fuzz.js`, `node tests/api.test.js`, `node tests/account.test.js`.
