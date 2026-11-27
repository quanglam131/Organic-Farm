# Nông trại Xanh Đà Lạt – Website TMĐT nông sản sạch (Đồ án 11)

Nền tảng thương mại điện tử khép kín cho **một nông trại** (Single Farm): bán trực tiếp từ vườn đến bàn ăn,
quản lý tồn kho theo **lô hàng và hạn sử dụng**, tự động xuất kho **FEFO**, truy xuất nguồn gốc bằng **mã QR**,
nhật ký canh tác, gói rau định kỳ trả trước, khiếu nại có ảnh minh chứng.

- **Backend:** Laravel 12 (PHP 8.2+), REST API, Sanctum token, MySQL (production) / SQLite (dev, test)
- **Frontend:** React 19 + Vite (giữ React theo lựa chọn của nhóm; tài liệu gốc gợi ý Blade + Tailwind)
- **Dịch vụ ngoài:** VNPay (có cổng giả lập), Cloudinary (tùy chọn), Google Maps / OpenStreetMap (tính phí ship)

Thông tin nông trại (tên, địa chỉ, tọa độ, câu chuyện) nằm trong `backend/config/farm.php` và đổi được qua `.env`
(`FARM_NAME`, `FARM_ADDRESS`, `FARM_LATITUDE`…). Mặc định là một nông trại giả định ở Đà Lạt.

## Cài đặt và chạy (máy local)

Yêu cầu: PHP 8.2+ (pdo_sqlite), Composer, Node.js 20+.

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=ProduceCatalogSeeder
php artisan db:seed --class=FefoTestSeeder
php artisan storage:link
php artisan serve
```

Cửa sổ thứ hai:

```powershell
cd frontend
npm install
npm run dev
```

Mở http://localhost:5173. Tài liệu API (Swagger UI): http://localhost:8000/api-docs/

Cửa sổ thứ ba (hàng đợi: email hóa đơn, tạo đơn gói định kỳ) và tác vụ định kỳ:

```powershell
cd backend
php artisan queue:work
```

```powershell
cd backend
php artisan schedule:work
```

Ảnh sản phẩm: đặt file `<slug>.jpg` vào `backend/storage/app/public/anh-san-pham` rồi chạy
`php artisan db:seed --class=ProductPhotoSeeder`.

### Tài khoản dùng thử (mật khẩu: `password`)

| Vai trò | Email | Được làm |
|---|---|---|
| Khách hàng | khach@farm.local | Mua lẻ, gói định kỳ, quét QR, đánh giá, khiếu nại |
| Nhân viên nông trại | staff@farm.local | Lô hàng, nhập lô, in QR, xuất hủy, nhật ký canh tác. **Không** xem đơn, khách, doanh thu |
| Bán hàng / CSKH | sales@farm.local | Xác nhận đơn, giao hàng, khiếu nại, lịch giao gói. **Không** tạo lô, không đổi tồn kho |
| Quản trị viên | admin@farm.local | Tất cả + báo cáo, sản phẩm, nhân sự, cấu hình cảnh báo |

### Kiểm thử

```powershell
cd backend
php artisan test
```

46 test tự động (FEFO, phân quyền 4 vai trò, luồng đơn, VNPay IPN, gói định kỳ, khiếu nại 24 giờ, phí ship…).
Kịch bản Postman: `backend/docs/postman/farm-ecommerce.postman_collection.json`.

## Luồng nghiệp vụ chính

1. **Đặt hàng:** khách đặt → backend đọc lại giá và tồn kho từ CSDL (không tin giá từ trình duyệt) → đơn `Chờ xác nhận`, **chưa trừ kho**.
2. **Xác nhận = trừ kho FEFO:** Sales bấm xác nhận (COD) hoặc VNPay gửi IPN thanh toán thành công → hệ thống lấy lô có hạn
   gần nhất (còn hạn), thiếu thì lấy tiếp lô sau. Chi tiết đơn ghi rõ từng lô (3 kg lô A + 7 kg lô B = 2 dòng), nên khiếu nại
   truy ngược được về lô. Không đủ hàng → đơn `Thiếu hàng`, báo đỏ cho Sales, không trừ gì.
3. **Gói định kỳ:** khách chọn gói và 1–3 ngày giao/tuần (vd. thứ 3 và thứ 6), trả trước 28 ngày. Mỗi ngày giao là một dòng
   `delivery_schedules`; trước 1 ngày, một job trong hàng đợi tạo và xác nhận đơn. Thiếu hàng → lịch giao `Thiếu hàng`, Sales gọi khách đổi sản phẩm.
4. **Khiếu nại:** chỉ cho đơn đã giao, trong **24 giờ**, bắt buộc ảnh .jpg/.png ≤ 5 MB (kiểm tra ở trình duyệt và server).
   Sales chấp nhận (hoàn tiền / giao bù) hoặc từ chối.
5. **Cảnh báo xả hàng:** lô còn ≤ N ngày (Admin cấu hình, mặc định 2) hiện cảnh báo đỏ kèm giá xả đề xuất.
6. **Hết phiên đăng nhập:** token hết hạn sau `SANCTUM_EXPIRATION` phút → popup "đăng nhập lại" thay vì lỗi thô (tương đương xử lý 419 của Blade).

## Đối chiếu yêu cầu các tuần

| Tuần | Yêu cầu | Trong mã nguồn |
|---|---|---|
| 1 | Single Farm, MoSCoW, rủi ro | `config/farm.php`; cổng thanh toán giả lập khi API bên thứ 3 lỗi (`PAYMENT_MOCK_ENABLED`) |
| 2 | 4 tác nhân, 5 use case khó, phi chức năng | Middleware `role:` (`routes/api.php`), Bcrypt, giao diện mobile-first |
| 3 | ERD: Batches (hao hụt, QR), Order_Details → batch_id, Farming_Logs → batch, Subscriptions + Delivery_Schedules, index | `database/migrations/2026_11_27_*`, index `expires_on`, `orders(status, created_at)` |
| 4 | Màn hình chi tiết sản phẩm + màn hình lô hàng/hao hụt trên di động | `pages/Shop.jsx` (ProductPage), `pages/backoffice/FarmTabs.jsx` |
| 5 | Migrations, seeders/factories, FEFO service, QR service, RESTful, lỗi JSON chung | `InventoryService`, `BatchController::qr`, `ApiErrorResponse`, Swagger `/api-docs`, Postman |
| 6 | AJAX + spinner + toast, xử lý hết phiên, phí ship theo bản đồ, VNPay IPN → FEFO, khiếu nại validate file | `ui.jsx`, `auth.jsx`, `ShippingService`, `PaymentController`, `Account.jsx` |
| 7 | Không tin giá từ client, .env an toàn, phân quyền endpoint, nén ảnh, Queue, triển khai, CI | `OrderService::place`, `.gitignore`, `media.js` + Cloudinary, `app/Jobs`, `Dockerfile`, `render.yaml`, `.github/workflows/ci.yml` |

Hướng dẫn triển khai: [docs/DEPLOY.md](docs/DEPLOY.md) · Hướng dẫn sử dụng cho 3 nhóm người dùng: [docs/HUONG-DAN-SU-DUNG.md](docs/HUONG-DAN-SU-DUNG.md) ·
Quy ước nhánh và commit: [docs/GIT-FLOW.md](docs/GIT-FLOW.md)

## Giới hạn hiện tại

- Phí ship khi không có Google Maps key dùng OpenStreetMap: khoảng cách đường chim bay × 1,3 (ước tính). Không tra được địa chỉ thì dùng số km khách nhập.
- VNPay mới kiểm tra chữ ký theo tài liệu, chưa chạy với tài khoản merchant thật. Hoàn tiền thực hiện thủ công (đơn được đánh dấu `Chờ hoàn tiền`).
- Gói định kỳ chỉ trả trước online (VNPay hoặc cổng giả lập).
- Ảnh lưu ở ổ đĩa local khi chưa cấu hình Cloudinary; trên Render ổ đĩa bị xóa mỗi lần deploy nên production cần Cloudinary.
- Xóa ảnh không xóa file trên Cloudinary.
