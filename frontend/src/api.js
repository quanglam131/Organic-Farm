// Thin wrapper around fetch for the Laravel API.
export const API = '/api';

export function getToken() {
  return localStorage.getItem('farm_token');
}

export function setToken(token) {
  if (token) localStorage.setItem('farm_token', token);
  else localStorage.removeItem('farm_token');
}

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

/**
 * Call the API. Pass `body` for JSON or `form` (FormData) for uploads.
 *
 * When a signed-in request comes back 401 the session has expired (the token's lifetime is
 * limited on the server). Instead of a raw error the app shows a "log in again" popup:
 * this module fires a `session-expired` event that the auth provider listens to (Tuần 6).
 */
export async function api(path, { method = 'GET', body, form } = {}) {
  const headers = { Accept: 'application/json' };
  const token = getToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  let payload;
  if (form) {
    payload = form;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(API + path, { method, headers, body: payload });
  } catch {
    throw new ApiError('Không kết nối được máy chủ. Kiểm tra mạng và thử lại.', 0);
  }

  const text = await res.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = null;
  }

  if (res.status === 401 && token && !path.startsWith('/auth/')) {
    window.dispatchEvent(new CustomEvent('session-expired'));
  }

  if (!res.ok) {
    const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
    throw new ApiError(firstError || data?.message || `Lỗi ${res.status}`, res.status, data?.errors || {});
  }

  return data;
}

/** Build FormData from a form element, dropping empty file inputs. */
export function formData(formEl) {
  const fd = new FormData(formEl);
  for (const [key, value] of [...fd.entries()]) {
    if (value instanceof File && value.size === 0) fd.delete(key);
  }
  return fd;
}

export const money = (n) => `${new Intl.NumberFormat('vi-VN').format(Number(n) || 0)} ₫`;

/** Shows a YYYY-MM-DD (or ISO) value as DD/MM/YYYY without timezone shifts. */
export const dateVN = (value) => {
  if (!value) return '—';
  const [y, m, d] = String(value).slice(0, 10).split('-');
  return `${d}/${m}/${y}`;
};

export const dateTimeVN = (value) => (value ? new Date(value).toLocaleString('vi-VN') : '—');

export const hasRole = (user, ...roles) => !!user && (user.role === 'admin' || roles.includes(user.role));

export const ROLE_LABEL = { customer: 'Khách hàng', staff: 'Nhân viên nông trại', sales: 'Bán hàng / CSKH', admin: 'Quản trị viên' };

export const ORDER_STATUS = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  stock_issue: 'Thiếu hàng',
  shipping: 'Đang giao',
  delivered: 'Đã giao',
  cancelled: 'Đã hủy',
};

export const PAYMENT_LABEL = { cod: 'Thanh toán khi nhận hàng', vnpay: 'VNPay', subscription: 'Gói định kỳ (đã trả trước)' };

export const PAYMENT_STATUS = { unpaid: 'Chưa thanh toán', paid: 'Đã thanh toán', failed: 'Thanh toán lỗi', refund_pending: 'Chờ hoàn tiền' };

export const COMPLAINT_STATUS = { open: 'Mới', in_progress: 'Đang xử lý', resolved: 'Đã giải quyết', rejected: 'Từ chối' };

export const COMPLAINT_REASONS = {
  damaged: 'Dập nát',
  spoiled: 'Hư hỏng, héo úa',
  wrong_item: 'Giao sai sản phẩm',
  missing: 'Thiếu số lượng',
  other: 'Lý do khác',
};

export const RESOLUTION_LABEL = { refund: 'Hoàn tiền', redelivery: 'Giao bù' };

export const SUBSCRIPTION_STATUS = {
  pending_payment: 'Chờ thanh toán',
  active: 'Đang hoạt động',
  paused: 'Tạm dừng',
  cancelled: 'Đã hủy',
  completed: 'Đã hoàn thành',
};

export const SCHEDULE_STATUS = {
  scheduled: 'Đã lên lịch',
  processing: 'Đang tạo đơn',
  ordered: 'Đã tạo đơn',
  short: 'Thiếu hàng',
  skipped: 'Bỏ qua (tạm dừng)',
  cancelled: 'Đã hủy',
};

export const BATCH_STATUS = { available: 'Đang bán', depleted: 'Đã bán hết', expired: 'Hết hạn', written_off: 'Đã xuất hủy' };

export const FARM_TYPES = {
  planting: 'Gieo trồng',
  watering: 'Tưới nước',
  fertilizing: 'Bón phân',
  pest_control: 'Phòng trừ sâu bệnh',
  harvesting: 'Thu hoạch',
  other: 'Khác',
};

export const WEEKDAYS = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

export const today = () => new Date().toISOString().slice(0, 10);
