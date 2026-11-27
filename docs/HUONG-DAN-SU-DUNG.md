# Hướng dẫn sử dụng (Phụ lục báo cáo – Tuần 7)

Ba hướng dẫn riêng cho ba nhóm người dùng nội bộ. Đăng nhập tại trang **Đăng nhập**; nhân viên được đưa thẳng tới
trang **Quản lý**. Biểu tượng số màu đỏ cạnh chữ "Quản lý" là số cảnh báo đang chờ xử lý.

---

## 1. Nhân viên nông trại (Farm Staff): cập nhật lô hàng, in mã QR, xuất hủy

Thiết kế cho điện thoại, dùng ngay tại vườn hoặc kho.

### Nhập lô thu hoạch mới
1. **Quản lý → Lô hàng & hao hụt → + Nhập lô mới**.
2. Chọn sản phẩm, khu canh tác, nhập số lượng, ngày thu hoạch và **hạn sử dụng**.
3. Bấm **Nhập lô & tạo mã QR**. Hệ thống sinh mã lô (LO-…) và mã truy xuất (TR…).
4. Có thể mở mục **Thử thuật toán FEFO** để xem hệ thống sẽ lấy hàng từ lô nào cho một số lượng bất kỳ.

### In tem QR cho lô hàng
1. Ở thẻ của lô, bấm **Mã QR**.
2. Bấm **In tem QR**: chỉ phần tem (mã QR, tên sản phẩm, mã lô, hạn dùng) được in.
3. Khách quét mã sẽ mở trang truy xuất: khu trồng, ngày thu hoạch, hạn dùng, toàn bộ nhật ký canh tác của lô.

### Ghi nhật ký canh tác
1. **Quản lý → Nhật ký canh tác**.
2. Chọn **Cho một lô hàng** (nhật ký hiện trên mã QR của lô đó) hoặc **Cho cả khu trồng**.
3. Chọn công việc (gieo trồng, tưới, bón phân, phòng trừ sâu bệnh, thu hoạch), ngày, mô tả.
4. Chụp ảnh hoặc quay video minh chứng (ảnh được tự nén trước khi tải lên). Bấm **Lưu nhật ký**.

### Xuất hủy / ghi nhận hao hụt
1. Ở thẻ của lô có hàng dập, nát, héo: bấm **Xuất hủy**.
2. Nhập số lượng, chọn lý do, chụp ảnh hiện trạng. Bấm **Xác nhận xuất hủy**.
3. Tồn kho của **đúng lô đó** giảm ngay; cột "Hao hụt" tăng tương ứng.

### Theo dõi hạn sử dụng
- Thẻ viền đỏ: lô còn ≤ 2 ngày, cần bán trước hoặc xả hàng.
- Bấm **Đánh dấu lô hết hạn** để ngừng bán các lô đã tới hạn (hệ thống cũng tự chạy lúc 00:05 mỗi đêm).
- Tab **Cảnh báo** liệt kê các lô sắp hết hạn kèm giá xả đề xuất.

> Nhân viên nông trại không có quyền xem đơn hàng, khách hàng hay doanh thu.

---

## 2. Nhân viên bán hàng / CSKH (Sales): xử lý đơn hỏa tốc và khiếu nại

### Xử lý đơn trong ngày
1. **Quản lý → Đơn hàng**, tích **Chỉ đơn hôm nay**, chọn trạng thái **Chờ xác nhận**.
2. Gọi xác nhận với khách (số điện thoại bấm gọi được trên điện thoại).
3. Bấm **Xác nhận & trừ kho (FEFO)**: hệ thống tự lấy hàng từ lô gần hết hạn nhất; dòng sản phẩm hiện mã lô đã lấy.
4. Khi giao: **Giao hàng** → khi khách nhận: **Đã giao** (đơn COD được ghi nhận đã thanh toán và tích điểm cho khách).
5. Đơn thanh toán VNPay được xác nhận tự động khi thanh toán thành công.

### Đơn thiếu hàng (viền đỏ)
- Khi các lô không đủ hàng, đơn chuyển **Thiếu hàng** và hiện trong tab **Cảnh báo**.
- Gọi khách để đổi sản phẩm tương đương hoặc hủy. Sau khi nhân viên vườn nhập lô mới, bấm **Thử xác nhận lại**.
- Đơn đã thanh toán online bị hủy sẽ có trạng thái thanh toán **Chờ hoàn tiền**.

### Lịch giao gói định kỳ
- **Quản lý → Lịch giao gói**: các lượt giao 7 ngày tới, theo từng ngày.
- Lượt giao **Thiếu hàng**: gọi khách đổi sản phẩm, rồi bấm **Thử lại** khi đã có hàng.

### Khiếu nại
1. **Quản lý → Khiếu nại** (mặc định lọc khiếu nại mới).
2. Xem ảnh khách gửi và danh sách **lô hàng** của đơn để truy ngược nguồn gốc.
3. Bấm **Nhận xử lý**, sau đó chọn **Hoàn tiền** hoặc **Giao bù**, hoặc **Từ chối** (bắt buộc ghi lý do).
4. Khách chỉ gửi được khiếu nại trong 24 giờ sau khi nhận hàng, và phải kèm ảnh .jpg/.png ≤ 5 MB.

> Sales không có quyền tạo lô hàng hay thay đổi tồn kho.

---

## 3. Quản trị viên (Super Admin): quản lý chung

Quản trị viên thấy mọi tab của Staff và Sales, cộng thêm:

- **Báo cáo:** doanh thu hôm nay / tháng / 6 tháng, sản phẩm bán chạy, đơn thiếu hàng, giá trị hàng sắp hết hạn, hao hụt 30 ngày.
- **Sản phẩm & ảnh:** thêm sản phẩm, đổi giá, ngừng / mở bán; nút **Ảnh** để tải nhiều ảnh, chọn ảnh chính, sắp xếp, xóa.
- **Danh mục & khu trồng:** thêm danh mục, thêm khu canh tác (mã, diện tích, chứng nhận).
- **Gói định kỳ:** tạo gói (sản phẩm và số lượng mỗi lần giao, % giảm giá), ngừng mở bán gói.
- **Nhân sự & khách:** **+ Thêm nhân viên** (vai trò nhân viên vườn, bán hàng hoặc quản trị), đổi vai trò, khóa tài khoản
  (tài khoản bị khóa bị đăng xuất ngay).
- **Cấu hình:** số ngày trước hạn để báo đỏ cho nhân viên, % giảm giá đề xuất khi xả hàng.

Thông tin nông trại hiển thị trên web (tên, địa chỉ, điện thoại, tọa độ tính phí ship) được đặt trong biến môi trường
`FARM_*` của máy chủ.
