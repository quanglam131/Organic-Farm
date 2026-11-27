# Quy ước làm việc với Git (Tuần 4)

## Nhánh

| Nhánh | Vai trò | Ai được đẩy |
|---|---|---|
| `main` | Mã đang chạy production (Render tự deploy khi `main` đổi) | Chỉ merge qua Pull Request từ `develop` |
| `develop` | Môi trường thử nghiệm, gom các tính năng đã xong | Chỉ merge qua Pull Request từ `feature/*` |
| `feature/<ten-chuc-nang>` | Mỗi tính năng một nhánh, tách từ `develop` | Người làm tính năng |
| `fix/<ten-loi>` | Sửa lỗi, tách từ `develop` | Người sửa |
| `hotfix/<ten-loi>` | Sửa gấp lỗi production, tách từ `main`, merge về cả `main` và `develop` | Trưởng nhóm |

Không ai commit thẳng lên `main` hay `develop`.

### Quy trình một tính năng

```powershell
git switch develop
```

```powershell
git pull
```

```powershell
git switch -c feature/fefo-algorithm
```

Làm việc, commit nhiều lần theo chuẩn bên dưới, rồi đẩy nhánh lên và mở Pull Request vào `develop`:

```powershell
git push -u origin feature/fefo-algorithm
```

Pull Request phải qua CI (test backend, build frontend) và được 1 thành viên khác duyệt rồi mới merge.
Khi `develop` ổn định: mở Pull Request `develop → main` và gắn tag phiên bản (`v1.0.0`, `v1.1.0`…).

## Thông điệp commit (Conventional Commits)

```
<loại>(<phạm vi, tùy chọn>): <mô tả ngắn, viết thường, không chấm cuối>
```

| Loại | Khi nào | Ví dụ |
|---|---|---|
| `feat` | Thêm tính năng | `feat(fefo): xuất kho theo lô có hạn gần nhất` |
| `fix` | Sửa lỗi | `fix(checkout): sửa lỗi tính sai phí ship trên mobile` |
| `docs` | Tài liệu | `docs: cập nhật tài liệu API swagger` |
| `test` | Thêm / sửa test | `test(fefo): kiểm thử đơn lấy hàng từ 2 lô` |
| `refactor` | Sắp xếp lại code, không đổi hành vi | `refactor(order): tách tính tiền sang OrderService` |
| `chore` | Cấu hình, dọn dẹp | `chore: bỏ file build khỏi git` |
| `ci` / `build` | CI/CD, đóng gói | `ci: chạy test trên mỗi pull request` |

## Lịch sử hiện tại

```powershell
git log --oneline --graph --all
```

Mỗi tính năng là một nhánh `feature/*` được merge vào `develop` (commit merge `--no-ff` giữ lại dấu vết nhánh), rồi
`develop` được merge vào `main` với tag `v1.0.0`.

## Bảo vệ nhánh trên GitHub (Branch Protection)

Làm cho cả `main` và `develop`: GitHub → repository → **Settings → Branches → Add branch ruleset**
(hoặc **Add rule** ở giao diện cũ).

1. **Branch name pattern / Target branches:** `main` (lặp lại cho `develop`).
2. Bật **Require a pull request before merging**.
3. Trong đó bật **Require approvals**, số người duyệt: **1**.
4. Bật **Require status checks to pass**, chọn hai check `backend` và `frontend` của workflow CI.
5. Bật **Block force pushes** và không cho xóa nhánh.
6. Lưu. Chụp màn hình trang cấu hình làm minh chứng nộp Tuần 4.

Kiểm tra: thử `git push origin main` trực tiếp, GitHub phải từ chối.
