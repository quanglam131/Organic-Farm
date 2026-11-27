# Triển khai lên Internet (Tuần 7)

Ứng dụng được đóng gói thành **một container Docker**: Laravel phục vụ cả API (`/api`) lẫn giao diện React đã build
(`/`), nên chỉ cần một dịch vụ web và một cơ sở dữ liệu MySQL online.

```
Trình duyệt ──► Render (Docker: Apache + PHP 8.3)
                 ├─ /            React (public/app)
                 ├─ /api/*       Laravel API
                 ├─ queue:work   email hóa đơn, tạo đơn gói định kỳ
                 └─ schedule     hết hạn lô 00:05, tạo đơn gói 06:00
                       │
                       ├──► MySQL online (Aiven / Railway / PlanetScale / VPS)
                       ├──► Cloudinary (ảnh, video)
                       └──► VNPay, Google Maps / OpenStreetMap
```

## 1. Cơ sở dữ liệu online

Tạo một MySQL 8 miễn phí (ví dụ Aiven for MySQL hoặc Railway). Ghi lại host, port, database, user, password.
Nếu nhà cung cấp bắt buộc TLS, tải file CA và khai báo `MYSQL_ATTR_SSL_CA`.

## 2. Cloudinary

Tạo tài khoản Cloudinary, lấy `Cloud name`, `API key`, `API secret`. Ảnh tải lên được tự thu nhỏ (tối đa 1600 px) và nén
(`q_auto`) trước khi lưu. Bắt buộc dùng trên Render vì ổ đĩa container bị xóa sau mỗi lần deploy.

## 3. Render

1. Đưa mã nguồn lên GitHub (file `.env` đã nằm trong `.gitignore`, không bao giờ được push).
2. Render → **New → Blueprint** → chọn repository. Render đọc `render.yaml` và tạo dịch vụ `nong-trai-xanh`.
3. Điền các biến được đánh dấu `sync: false` trong dashboard (Environment):
   - `APP_KEY`: chạy `php artisan key:generate --show` ở máy local rồi dán vào.
   - `APP_URL`, `FRONTEND_URL`: địa chỉ Render, ví dụ `https://nong-trai-xanh.onrender.com`.
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
   - `CLOUDINARY_*`, `VNPAY_*` (và `VNPAY_RETURN_URL = APP_URL/api/payments/vnpay/return`), `GOOGLE_MAPS_API_KEY` nếu có.
4. Deploy. Container tự chạy `migrate --force`, cache cấu hình, khởi động queue worker và scheduler.
5. Lần đầu, mở **Shell** của dịch vụ và chạy `php artisan db:seed --force` để có tài khoản và dữ liệu mẫu
   (đổi mật khẩu các tài khoản demo ngay sau đó).

**Minh chứng cần chụp màn hình:** trang Environment của Render (che giá trị bí mật), trang web chạy trên domain Render,
và `/up` trả về trạng thái OK.

## 4. CI/CD với GitHub Actions

`.github/workflows/ci.yml` chạy trên mỗi push và pull request vào `main`/`develop`:

1. **backend:** cài Composer, chạy `php artisan test`.
2. **frontend:** `npm ci` và `npm run build`.
3. **deploy** (chỉ khi push vào `main` và hai bước trên thành công): gọi Render Deploy Hook.
   Lấy hook trong Render → Settings → Deploy Hook, lưu vào GitHub → Settings → Secrets → `RENDER_DEPLOY_HOOK`.

Kết hợp với Branch Protection (Tuần 4): bật "Require status checks to pass" cho `main` để không merge được khi test lỗi.

## 5. Chạy thử container ở máy local

```powershell
docker build -t farm-shop .
```

```powershell
docker run -p 8080:80 --env-file backend/.env.production farm-shop
```

`backend/.env.production.example` liệt kê mọi biến cần có.

## 6. Kiểm tra bảo mật trước khi lên production

- `APP_DEBUG=false`, `PAYMENT_MOCK_ENABLED=false`.
- Không có khóa API nào trong mã nguồn: tất cả nằm trong biến môi trường.
- Tổng tiền luôn tính lại ở server từ giá trong CSDL; giá, tổng tiền gửi từ trình duyệt bị bỏ qua (có test).
- Phân quyền: khách không gọi được API `/admin/*`; nhân viên vườn không xem được đơn hàng và doanh thu; Sales không tạo được lô (có test).
- Đăng nhập giới hạn 10 lần/phút; token hết hạn sau `SANCTUM_EXPIRATION` phút; mật khẩu băm Bcrypt.
