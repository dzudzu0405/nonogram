# Test case — Dashboard (trang Projects)

Phạm vi: `app/index.php`, `assets/js/dashboard.js`, phần dashboard trong `assets/css/app.css` và các API `api/projects.php` mà dashboard gọi.
Không bao gồm: trang editor, xuất PDF, các lỗi backend đã ghi nhận nhưng tạm hoãn (session 24 phút, install.php, login_attempts…).

- **Tự động**: chạy bằng [dashboard.e2e.js](dashboard.e2e.js) (xem cách chạy ở đầu file). Cột "Loại" ghi `Auto`.
- **Thủ công**: những việc script không làm được (phím Esc thật của trình duyệt, nhìn giao diện, focus bàn phím). Cột "Loại" ghi `Manual`.

Dữ liệu test: 3 tài khoản trong [dev-accounts.json](dev-accounts.json) — `admin` (mọi tính năng), `fe` (gói FE: trắng đen, lưới 30, tối đa 100 project), `nolicense` (chưa có license). Script tự tạo project tên bắt đầu bằng `E2E ` và tự xóa sau khi chạy.

## A. Tải trang và số liệu tóm tắt

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-01 | Auto | Danh sách khớp dữ liệu | Mở `/app/` | Số thẻ và tên project trùng với API `projects.php?action=list` |
| TC-02 | Auto | Số liệu tóm tắt đúng | Mở `/app/` có 1 project đủ puzzle, 1 project có puzzle chưa duy nhất, 1 project rỗng | Projects, Puzzles, Need checking và số trên các tab All / Need checking / Empty đúng; thanh hạn mức > 0 |
| TC-03 | Auto | Lời chào | Mở `/app/` | "Good morning / afternoon / evening / Working late, <tên>" theo giờ máy người dùng |
| TC-04 | Auto | Thông tin trên thẻ | Xem thẻ các project mẫu | Có số puzzle, kích thước, độ khó, nhãn trạng thái ("✓ All unique", "1 to check", "Empty"), viền vàng cho project cần kiểm tra, "just now" cho project vừa sửa |

## B. Bộ lọc

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-05 | Auto | Lọc "Need checking" | Bấm tab Need checking | Chỉ còn project có puzzle chưa duy nhất; tab được chọn |
| TC-06 | Auto | Lọc "Empty", quay về "All" | Bấm Empty rồi All | Empty chỉ còn project rỗng; All hiện lại đủ |
| TC-07 | Auto | Bấm thẻ tóm tắt "Need checking" | Bấm thẻ vàng | Tự chuyển sang tab Need checking |

## C. Tìm kiếm và phím tắt

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-08 | Auto | Không phân biệt hoa thường | Gõ `e2e ALPHA` | Ra đúng "E2E Alpha" |
| TC-09 | Auto | Không có kết quả | Tab Empty + gõ `alpha` | Hiện "No projects match …" và nút "Show all projects"; bấm nút thì xóa ô tìm và về All |
| TC-10 | Auto | Gõ HTML vào ô tìm | Gõ `<b>x</b>` | Hiện dạng chữ, không tạo thẻ HTML |
| TC-11 | Auto | Esc trong ô tìm | Gõ chữ rồi nhấn Esc | Ô tìm bị xóa, hiện lại đủ project |
| TC-12 | Auto | Phím `/` và `N` | Nhấn `/`; nhấn `N` khi đang gõ trong ô tìm; nhấn `N` ở ngoài | `/` đưa con trỏ vào ô tìm; `N` khi đang gõ không mở gì; `N` ở ngoài mở hộp "New project"; Cancel đóng hộp |

## D. Sắp xếp và kiểu hiển thị

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-13 | Auto | Sắp theo tên | Chọn "Name A–Z" | A→Z, không phân biệt hoa thường, số theo thứ tự tự nhiên |
| TC-14 | Auto | Nhiều puzzle nhất | Chọn "Most puzzles" | Số puzzle giảm dần |
| TC-15 | Auto | Sửa gần nhất / mới tạo | Chọn "Last edited", "Newest first" | Project đầu tiên đúng theo `updated_at` / `created_at` |
| TC-16 | Auto | Ghi nhớ lựa chọn | Chọn Name + dạng danh sách, tải lại trang | Vẫn là Name và dạng danh sách |
| TC-17 | Auto | Trình duyệt chặn lưu trữ | Chặn `localStorage`, đổi sắp xếp và kiểu hiển thị | Không có lỗi, trang vẫn chạy |

## E. Menu thẻ và mở project

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-18 | Auto | Menu ⋯ | Mở menu thẻ 1, rồi thẻ 2; bấm ra ngoài; mở lại rồi nhấn Esc | Mỗi lúc chỉ 1 menu mở; bấm ngoài và Esc đều đóng |
| TC-19 | Auto | Bấm ⋯ không mở project | Bấm ⋯ | Vẫn ở dashboard |
| TC-20 | Auto | Bấm vào thẻ | Bấm phần dưới của thẻ | Mở `editor.php?id=<id>` |
| TC-21 | Auto | Link tiêu đề | Kiểm tra link tên project | Trỏ đúng `editor.php?id=<id>` |
| TC-45 | Auto | Menu với bàn phím | Mở menu, Tab ra ngoài; mở lại, nhấn Esc | Mở menu thì focus vào "Rename"; Tab ra ngoài thì menu đóng; Esc đóng menu và đưa focus về nút ⋯ |

## F. Tạo project

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-22 | Auto | Tên rỗng | Mở hộp, để toàn dấu cách, bấm Create | Hộp không đóng, không tạo project |
| TC-23 | Auto | Gợi ý tên, Cancel, bấm nền | Bấm gợi ý; Cancel; mở lại rồi bấm vùng tối bên ngoài | Gợi ý điền vào ô; Cancel và bấm nền đều đóng, không tạo project |
| TC-24 | Auto | Tạo thành công | Nhập `  E2E Created  `, Create | Mở editor của project mới; tên được bỏ dấu cách thừa |
| TC-25 | Auto | Upload images | Bấm "Upload images" | Tạo project "Uploaded pictures <ngày>", mở editor tại `#upload` |

## G. Đổi tên

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-26 | Auto | Đổi tên | ⋯ > Rename, nhập tên mới, Save | Hộp điền sẵn tên cũ, không hiện gợi ý; thẻ và server đều có tên mới |
| TC-27 | Auto | Giữ nguyên tên | Rename rồi Save ngay | Không gửi request nào |
| TC-28 | Auto | Tên chứa HTML (XSS) | Đặt tên `<img src=x onerror=…>` | Hiện dạng chữ ở thẻ, hộp xóa, khi tìm kiếm và sau khi tải lại; không có script nào chạy |
| TC-29 | Auto | Tên quá dài | Ô nhập có `maxlength=160`; vượt qua giới hạn đó và gửi 254 ký tự | Server chỉ lưu 160 ký tự |

## H. Nhân bản và xóa

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-30 | Auto | Duplicate | ⋯ > Duplicate | Có "<tên> (copy)" với cùng số puzzle |
| TC-31 | Auto | Hủy xóa | ⋯ > Delete > Cancel | Hộp ghi rõ số puzzle bị xóa; project vẫn còn |
| TC-32 | Auto | Xác nhận xóa | ⋯ > Delete > Delete | Thẻ biến mất, tải lại vẫn không còn |
| TC-33 | Auto | Xóa project rỗng | ⋯ > Delete | Hộp ghi "This project is empty" |

## I. Lỗi

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-34 | Auto | Không tải được danh sách | Giả lập API list trả lỗi 500 | Hiện thông báo lỗi và nút "Try again"; bấm nút thì tải lại được |
| TC-35 | Auto | Thao tác bị server từ chối | Giả lập Duplicate trả 403 | Hiện toast đỏ với nội dung lỗi của server |
| TC-36 | Auto | Hết phiên đăng nhập | Đăng xuất ở tab khác rồi bấm Duplicate | Chuyển sang trang đăng nhập |

## J. Màn hình nhỏ

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-37 | Auto | Điện thoại 375px | Mở ở chiều rộng 375px, thử dạng lưới và danh sách | Không có thanh cuộn ngang; ô tìm kiếm rộng ít nhất 250px |

## K. Tài khoản khác

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-38 | Auto | Gói FE | Đăng nhập `fe` | "/ 100" project; thẻ gói "30×30 … Pro adds 50×50 and color" |
| TC-39 | Auto | Không thấy dữ liệu người khác | Đăng nhập `fe` khi admin có project E2E | Không có project nào của admin |
| TC-40 | Auto | Tài khoản chưa có project | Đăng nhập `fe` (chưa có project) | Hướng dẫn 3 bước; ẩn thanh công cụ; thẻ Need checking bị vô hiệu; nút New project hoạt động. Tự SKIP nếu `fe` đã có project |
| TC-41 | Auto | Hết hạn mức | `fe` có 100 project rồi tạo thêm | Toast đỏ "You have reached 100 projects…", vẫn ở dashboard, thanh hạn mức đầy |
| TC-42 | Auto | Không có license | Đăng nhập `nolicense` | Chỉ hiện thông báo license, không có dashboard |
| TC-43 | Auto | Chưa đăng nhập | Mở `/app/` | Chuyển về `login.php` |
| TC-44 | Auto | Không lỗi JavaScript | Cả lượt chạy | Không có lỗi JS hay promise bị từ chối mà không xử lý |

## L. Kiểm tra thủ công

| ID | Loại | Tình huống | Các bước | Kết quả mong đợi |
|---|---|---|---|---|
| TC-M1 | Manual | Esc đóng hộp thoại | Mở New project, nhấn phím Esc thật | Hộp đóng, không tạo project |
| TC-M2 | Manual | Enter trong hộp thoại | Gõ tên rồi nhấn Enter | Tạo project (hoặc lưu tên khi đổi tên) |
| TC-M3 | Manual | Dùng bàn phím (Tab) | Tab qua các nút, tab, thẻ, menu | Luôn thấy viền focus; Enter/Space bấm được; menu mở thì focus vào mục đầu |
| TC-M4 | Manual | Chế độ sáng / tối | Đổi giao diện hệ điều hành | Chữ đọc rõ, màu nhãn trạng thái phân biệt được ở cả hai chế độ |
| TC-M5 | Manual | Tên project rất dài trên thẻ | Đặt tên 160 ký tự | Tên bị cắt bằng "…", không vỡ bố cục, nút ⋯ vẫn bấm được |
| TC-M6 | Manual | Trình duyệt | Chrome, Edge, Firefox, Safari | Hộp thoại (`<dialog>`) và `color-mix` hiển thị đúng (cần Safari 16.4+ / Firefox 113+) |

## Kết quả lần chạy 2026-10-13 (bản local, Chrome)

| Hạng mục | Kết quả |
|---|---|
| Tự động (TC-01 → TC-45) | 45/45 PASS, chạy 2 lần liên tiếp đều ổn định |
| Kiểm tra độ nhạy của bộ test | Cố tình cài 3 lỗi (tìm kiếm phân biệt hoa thường, đổi tên gửi request thừa, tên không được escape): TC-08, TC-27, TC-28 fail đúng như mong đợi, không test nào khác bị ảnh hưởng |
| TC-M1 Esc đóng hộp thoại | PASS |
| TC-M2 Enter để tạo | PASS |
| TC-M3 Dùng bàn phím | PASS sau khi sửa BUG-01 |
| TC-M4 Sáng / tối | PASS (kiểm tra bằng mắt) |
| TC-M5 Tên rất dài | PASS: cắt bằng "…", nút ⋯ vẫn bấm được |
| TC-M6 Trình duyệt khác | Chưa test (chỉ có Chrome) |

### Lỗi tìm thấy

| ID | Mức độ | Mô tả | Trạng thái |
|---|---|---|---|
| BUG-01 | Thấp | Đang mở menu ⋯ mà nhấn Tab ra ngoài thì menu vẫn mở, che lên thẻ bên dưới | Đã sửa, có TC-45 |
| BUG-02 | Thấp | Bản sửa đầu tiên của BUG-01 làm menu vừa mở bị đóng ngay khi bấm từ menu này sang ⋯ của thẻ khác (gặp trên Safari, vì Safari không focus nút khi bấm). TC-18 bắt được | Đã sửa |
