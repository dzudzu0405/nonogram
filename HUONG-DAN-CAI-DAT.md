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

3. **Tạo file cấu hình**
   - Trong File Manager, **Copy** file `config.sample.php` thành `config.php`.
   - Chuột phải `config.php` → **Edit**, điền:
     - `db.name`, `db.user`, `db.pass`: thông tin database ở bước 1.
     - `app_key`: một chuỗi ngẫu nhiên dài (≥ 32 ký tự).
     - `mail_from`: email gửi đi, ví dụ `support@tenmien.com`.
   - Giữ `'debug' => false` trên site thật.

4. **Chạy trình cài đặt**
   - Mở `https://tenmien.com/install.php`.
   - Tất cả dòng phải có dấu ✓. Dòng nào có ✕ thì làm theo gợi ý ngay dưới dòng đó.
   - Nhập email và mật khẩu cho tài khoản **Admin**, rồi bấm **Install**. Trình cài đặt tự tạo các bảng trong database.
   - Cài xong, **xóa `install.php`** trong File Manager.

5. **Đăng nhập**: `https://tenmien.com/login.php`

## Cập nhật phiên bản mới
Upload ZIP mới → Extract đè lên file cũ. File `config.php` và thư mục `storage/` không có trong ZIP nên không bị ghi đè.
Nếu bản mới có thay đổi database, tài liệu phát hành sẽ ghi rõ cần chạy gì.

## Bảo mật đã có sẵn
- `config.php`, các thư mục `lib/`, `install/`, `storage/` bị chặn truy cập từ web (bằng `.htaccess`).
- Mật khẩu được mã hóa, có chống CSRF, và khóa đăng nhập tạm thời khi nhập sai nhiều lần.
- Mọi dữ liệu project và puzzle đều kiểm tra đúng chủ sở hữu trước khi đọc hoặc ghi.

## Chạy thử trên máy (cho lập trình)
```
php -S 127.0.0.1:8080 dev-router.php
```
Với `config.php` dùng SQLite: `'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/storage/dev.sqlite']`.
Test: `node tests/engine.test.js` và `node tests/api.test.js`.
