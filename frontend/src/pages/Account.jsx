import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  api, COMPLAINT_REASONS, COMPLAINT_STATUS, dateTimeVN, dateVN, money, PAYMENT_LABEL, PAYMENT_STATUS,
  RESOLUTION_LABEL, SCHEDULE_STATUS, SUBSCRIPTION_STATUS, WEEKDAYS,
} from '../api.js';
import { useAuth } from '../auth.jsx';
import { compressImage, validateFile } from '../media.js';
import { Badge, Button, Empty, Loading, Notice, StatusBadge, useAction, useToast } from '../ui.jsx';
import { goToPayment } from './Shop.jsx';

export function OrdersPage() {
  const [orders, setOrders] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    api('/orders')
      .then((d) => { if (active) setOrders(d.data); })
      .catch((e) => { if (active) setError(e.message); });
    return () => { active = false; };
  }, []);

  if (error) return <Notice kind="error">{error}</Notice>;
  if (!orders) return <Loading />;
  if (orders.length === 0) return <Empty>Bạn chưa có đơn hàng nào. <Link to="/">Mua sắm ngay</Link></Empty>;

  return (
    <section>
      <h1>Đơn hàng của tôi</h1>
      <div className="table-wrap">
        <table className="table">
          <thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Tổng</th><th>Thanh toán</th><th>Trạng thái</th></tr></thead>
          <tbody>
            {orders.map((o) => (
              <tr key={o.id}>
                <td><Link to={`/orders/${o.code}`}>{o.code}</Link></td>
                <td>{dateVN(o.created_at)}</td>
                <td>{money(o.total)}</td>
                <td>{PAYMENT_LABEL[o.payment_method]}<div className="muted small">{PAYMENT_STATUS[o.payment_status]}</div></td>
                <td><StatusBadge status={o.status} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

const STEPS = [
  ['pending', 'Đặt hàng'],
  ['confirmed', 'Xác nhận & lấy hàng từ lô'],
  ['shipping', 'Đang giao'],
  ['delivered', 'Đã nhận'],
];

function OrderSteps({ status }) {
  if (status === 'cancelled') return <Notice kind="error">Đơn hàng đã bị hủy.</Notice>;
  const reached = STEPS.findIndex(([s]) => s === (status === 'stock_issue' ? 'pending' : status));
  return (
    <ol className="steps">
      {STEPS.map(([key, label], i) => (
        <li key={key} className={i <= reached ? 'done' : ''}>{label}</li>
      ))}
    </ol>
  );
}

export function OrderPage() {
  const { code } = useParams();
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const [order, setOrder] = useState(null);
  const [error, setError] = useState('');
  const [busy, run] = useAction();

  const load = () => {
    setError('');
    return api(`/orders/${code}`).then(setOrder).catch((e) => setError(e.message));
  };

  useEffect(() => {
    let active = true;
    setOrder(null);
    setError('');
    api(`/orders/${code}`)
      .then((data) => { if (active) setOrder(data); })
      .catch((e) => { if (active) setError(e.message); });
    return () => { active = false; };
  }, [code]);

  async function cancel() {
    if (!window.confirm('Bạn chắc chắn muốn hủy đơn này?')) return;
    const res = await run(() => api(`/orders/${code}/cancel`, { method: 'POST' }), 'Đã hủy đơn hàng.');
    if (res) load();
  }

  if (!order) return error ? <Notice kind="error">{error}</Notice> : <Loading />;

  const payment = params.get('payment');
  const canCancel = ['pending', 'stock_issue', 'confirmed'].includes(order.status);
  const unpaidOnline = order.payment_method === 'vnpay' && order.payment_status !== 'paid' && order.status === 'pending';
  const products = Object.values(order.items.reduce((acc, i) => {
    acc[i.product_id] ??= { product_id: i.product_id, name: i.product_name };
    return acc;
  }, {}));

  return (
    <section>
      <h1>Đơn {order.code}</h1>
      {payment === 'success' && <Notice kind="success">Thanh toán thành công. Nông trại đã lấy hàng cho đơn của bạn.</Notice>}
      {payment === 'failed' && <Notice kind="error">Thanh toán chưa thành công.</Notice>}
      {payment === 'invalid' && <Notice kind="error">Không xác thực được kết quả thanh toán.</Notice>}
      {order.status === 'pending' && order.payment_method === 'cod' && <Notice kind="info">Nhân viên sẽ gọi xác nhận đơn trước khi giao.</Notice>}
      {order.status === 'stock_issue' && <Notice kind="error">Nông trại đang thiếu hàng cho đơn này, nhân viên sẽ liên hệ bạn để đổi sản phẩm tương đương hoặc hoàn tiền.</Notice>}

      <OrderSteps status={order.status} />

      <p>
        <StatusBadge status={order.status} /> · {PAYMENT_LABEL[order.payment_method]} · {PAYMENT_STATUS[order.payment_status] ?? order.payment_status}
      </p>

      <div className="table-wrap">
        <table className="table">
          <thead><tr><th>Sản phẩm</th><th>Lô hàng</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th></tr></thead>
          <tbody>
            {order.items.map((i) => (
              <tr key={i.id}>
                <td>{i.product_name}</td>
                <td>
                  {i.batch ? <Link to={`/trace/${i.batch.trace_code}`}>{i.batch_code}</Link> : <span className="muted small">Chọn khi xác nhận</span>}
                  {i.batch && <div className="muted small">HSD {dateVN(i.batch.expires_on)}</div>}
                </td>
                <td>{money(i.unit_price)}</td>
                <td>{i.quantity}</td>
                <td>{money(i.line_total)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="two-col">
        <div className="card summary">
          <div className="line"><span>Tạm tính</span><span>{money(order.subtotal)}</span></div>
          <div className="line"><span>Phí giao hàng ({order.distance_km} km)</span><span>{money(order.shipping_fee)}</span></div>
          <div className="line"><span>Giảm từ điểm ({order.points_used} điểm)</span><span>- {money(order.points_discount)}</span></div>
          <div className="line total"><span>Tổng cộng</span><strong>{money(order.total)}</strong></div>
          {order.points_earned > 0 && <p className="muted small">Đơn này đã tích được {order.points_earned} điểm.</p>}
          <p className="small">Giao đến: {order.shipping_name} · {order.shipping_phone}<br />{order.shipping_address}</p>
          <div className="row">
            {unpaidOnline && <button onClick={() => goToPayment(`/payment/mock/${order.code}`, navigate)}>Thanh toán ngay</button>}
            {canCancel && <Button className="danger" busy={busy} onClick={cancel}>Hủy đơn</Button>}
          </div>
        </div>

        <div>
          {order.status === 'delivered' && (
            <>
              <ComplaintForm order={order} onSent={load} />
              <ReviewForms order={order} products={products} onSaved={load} />
            </>
          )}
          {order.complaints?.length > 0 && (
            <p className="small">Khiếu nại của đơn: {order.complaints.map((c) => COMPLAINT_STATUS[c.status]).join(', ')} · <Link to="/complaints">xem chi tiết</Link></p>
          )}
        </div>
      </div>
    </section>
  );
}

/**
 * Complaint form on a delivered order (Tuần 6): only within 24 h of delivery, a .jpg/.png photo
 * under 5 MB is required. The file is checked here before upload, then compressed.
 */
function ComplaintForm({ order, onSent }) {
  const toast = useToast();
  const [reason, setReason] = useState('damaged');
  const [content, setContent] = useState('');
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState('');
  const [fileError, setFileError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => () => preview && URL.revokeObjectURL(preview), [preview]);

  if (!order.can_complain) {
    return <p className="muted small">Đã quá 24 giờ kể từ khi nhận hàng ({dateTimeVN(order.complaint_deadline)}), đơn này không còn nhận khiếu nại.</p>;
  }

  function pick(e) {
    const chosen = e.target.files?.[0] ?? null;
    const problem = validateFile(chosen);
    setFileError(problem);
    if (problem) {
      e.target.value = '';
      setFile(null);
      setPreview('');
      return;
    }
    setFile(chosen);
    setPreview(URL.createObjectURL(chosen));
  }

  async function submit(e) {
    e.preventDefault();
    const problem = validateFile(file);
    if (problem) {
      setFileError(problem);
      return;
    }
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append('order_code', order.code);
      fd.append('reason', reason);
      fd.append('content', content);
      fd.append('image', await compressImage(file));
      await api('/complaints', { method: 'POST', form: fd });
      toast.success('Đã gửi khiếu nại. Nhân viên chăm sóc khách hàng sẽ phản hồi sớm.');
      setContent('');
      setFile(null);
      setPreview('');
      onSent();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="card" onSubmit={submit}>
      <h3>Gửi khiếu nại</h3>
      <p className="muted small">Hạn gửi: {dateTimeVN(order.complaint_deadline)}. Bắt buộc kèm ảnh .jpg hoặc .png, tối đa 5 MB.</p>
      <label>
        Lý do
        <select value={reason} onChange={(e) => setReason(e.target.value)}>
          {Object.entries(COMPLAINT_REASONS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </label>
      <label>Mô tả<textarea required rows="3" maxLength={2000} value={content} onChange={(e) => setContent(e.target.value)} /></label>
      <label>
        Ảnh minh chứng
        <input type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" capture="environment" onChange={pick} required />
      </label>
      {fileError && <Notice kind="error">{fileError}</Notice>}
      {preview && <img className="log-media" src={preview} alt="Ảnh minh chứng" />}
      <Button busy={busy}>Gửi khiếu nại</Button>
    </form>
  );
}

function ReviewForms({ order, products, onSaved }) {
  const [busy, run] = useAction();
  const reviewed = Object.fromEntries((order.my_reviews ?? []).map((r) => [r.product_id, r]));
  const [drafts, setDrafts] = useState({});

  async function save(productId) {
    const draft = drafts[productId] ?? { rating: 5, comment: '' };
    const res = await run(
      () => api(`/orders/${order.code}/reviews`, { method: 'POST', body: { product_id: productId, ...draft } }),
      'Cảm ơn bạn đã đánh giá!',
    );
    if (res) onSaved();
  }

  return (
    <div className="card">
      <h3>Đánh giá sản phẩm</h3>
      {products.map((p) => (
        <div key={p.product_id} className="review-form">
          <strong>{p.name}</strong>
          {reviewed[p.product_id] ? (
            <p className="small">Bạn đã đánh giá {'★'.repeat(reviewed[p.product_id].rating)} {reviewed[p.product_id].comment}</p>
          ) : (
            <div className="row">
              <select
                value={drafts[p.product_id]?.rating ?? 5}
                onChange={(e) => setDrafts({ ...drafts, [p.product_id]: { ...drafts[p.product_id], rating: Number(e.target.value) } })}
                aria-label="Số sao"
              >
                {[5, 4, 3, 2, 1].map((n) => <option key={n} value={n}>{'★'.repeat(n)}</option>)}
              </select>
              <input
                placeholder="Nhận xét (không bắt buộc)"
                value={drafts[p.product_id]?.comment ?? ''}
                onChange={(e) => setDrafts({ ...drafts, [p.product_id]: { rating: 5, ...drafts[p.product_id], comment: e.target.value } })}
              />
              <Button className="secondary" busy={busy} onClick={() => save(p.product_id)}>Gửi</Button>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}

export function PointsPage() {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    api('/points')
      .then((result) => { if (active) setData(result); })
      .catch((e) => { if (active) setError(e.message); });
    return () => { active = false; };
  }, []);

  if (error) return <Notice kind="error">{error}</Notice>;
  if (!data) return <Loading />;

  return (
    <section>
      <h1>Điểm tích lũy</h1>
      <p className="big">Số dư: <strong>{data.balance}</strong> điểm (tương đương {money(data.balance * data.value_vnd_per_point)})</p>
      <p className="muted">{data.earn_rule}. Mỗi điểm được dùng như 1.000 ₫ giảm giá, tối đa 50% giá trị đơn hàng.</p>
      {data.transactions.length === 0 ? (
        <Empty>Chưa có giao dịch điểm.</Empty>
      ) : (
        <div className="table-wrap">
          <table className="table">
            <thead><tr><th>Thời gian</th><th>Nội dung</th><th>Điểm</th></tr></thead>
            <tbody>
              {data.transactions.map((t) => (
                <tr key={t.id}>
                  <td>{dateVN(t.created_at)}</td>
                  <td>{t.reason}</td>
                  <td className={t.points > 0 ? 'green-text' : 'red-text'}>{t.points > 0 ? `+${t.points}` : t.points}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

/* ---------- Prepaid monthly packages ---------- */

export function SubscriptionsPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [plans, setPlans] = useState(null);
  const [subs, setSubs] = useState([]);
  const [planId, setPlanId] = useState(params.get('plan') ?? '');
  const [days, setDays] = useState([2, 5]);
  const [quote, setQuote] = useState(null);
  const [form, setForm] = useState({
    shipping_name: user?.name ?? '',
    shipping_phone: user?.phone ?? '',
    shipping_address: user?.address ?? '',
  });
  const [busy, run] = useAction();

  const load = async () => {
    setPlans(await api('/subscription-plans'));
    setSubs(await api('/subscriptions'));
  };

  useEffect(() => {
    load().catch(() => setPlans([]));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    let active = true;
    setQuote(null);

    if (!planId || days.length === 0) {
      return () => { active = false; };
    }

    api('/subscriptions/quote', { method: 'POST', body: { subscription_plan_id: Number(planId), delivery_days: days } })
      .then((result) => { if (active) setQuote(result); })
      .catch(() => { if (active) setQuote(null); });

    return () => { active = false; };
  }, [planId, days]);

  const toggleDay = (d) => setDays((list) => {
    if (list.includes(d)) return list.filter((x) => x !== d);
    return list.length >= 3 ? list : [...list, d].sort();
  });

  async function subscribe(e) {
    e.preventDefault();
    const res = await run(
      () => api('/subscriptions', { method: 'POST', body: { ...form, subscription_plan_id: Number(planId), delivery_days: days } }),
      'Đã tạo gói. Vui lòng thanh toán trước để kích hoạt.',
    );
    if (res?.payment_url) goToPayment(res.payment_url, navigate);
  }

  async function setStatus(id, status) {
    if (status === 'cancelled' && !window.confirm('Hủy gói? Các lần giao chưa tới sẽ bị hủy.')) return;
    const res = await run(() => api(`/subscriptions/${id}`, { method: 'PATCH', body: { status } }), 'Đã cập nhật gói.');
    if (res) load();
  }

  const selected = useMemo(() => plans?.find((p) => String(p.id) === String(planId)), [plans, planId]);

  if (!plans) return <Loading />;

  return (
    <section>
      <h1>Gói rau định kỳ hàng tháng</h1>
      <p className="muted">Trả trước một lần cho 4 tuần. Chọn 1–3 ngày giao trong tuần (ví dụ thứ 3 và thứ 6); nông trại tự tạo đơn và giao vào các ngày đó.</p>
      {params.get('payment') === 'success' && <Notice kind="success">Thanh toán thành công, gói đã được kích hoạt.</Notice>}

      <div className="grid">
        {plans.map((p) => (
          <div key={p.id} className={`card plan ${String(planId) === String(p.id) ? 'selected' : ''}`}>
            <h3>{p.name}</h3>
            <p className="muted small">{p.description}</p>
            <ul>{p.items.map((i) => <li key={i.product_id}>{i.name} × {i.quantity} {i.unit}</li>)}</ul>
            <p>
              <span className="muted line-through">{money(p.subtotal)}</span>{' '}
              <strong className="price">{money(p.total)}</strong> / lần giao
              {p.discount_percent > 0 && <Badge tone="green">-{p.discount_percent}%</Badge>}
            </p>
            <button className={String(planId) === String(p.id) ? '' : 'secondary'} onClick={() => setPlanId(String(p.id))}>Chọn gói này</button>
          </div>
        ))}
      </div>

      {selected && (
        <form className="card narrow" onSubmit={subscribe}>
          <h2>Đăng ký {selected.name}</h2>
          <fieldset className="weekdays">
            <legend>Ngày giao trong tuần (tối đa 3)</legend>
            {WEEKDAYS.map((w, i) => (
              <label key={w} className={`choice ${days.includes(i) ? 'selected' : ''}`}>
                <input type="checkbox" checked={days.includes(i)} onChange={() => toggleDay(i)} />{w}
              </label>
            ))}
          </fieldset>
          {quote && (
            <div className="quote">
              <p>{quote.dates.length} lần giao × {money(quote.per_delivery)} = <strong className="price">{money(quote.total)}</strong></p>
              <p className="muted small">Lịch giao: {quote.dates.map(dateVN).join(', ')}</p>
            </div>
          )}
          <label>Họ tên<input required value={form.shipping_name} onChange={(e) => setForm({ ...form, shipping_name: e.target.value })} /></label>
          <label>Số điện thoại<input required value={form.shipping_phone} onChange={(e) => setForm({ ...form, shipping_phone: e.target.value })} /></label>
          <label>Địa chỉ<input required value={form.shipping_address} onChange={(e) => setForm({ ...form, shipping_address: e.target.value })} /></label>
          <Button busy={busy} disabled={days.length === 0}>Thanh toán trước và đăng ký</Button>
        </form>
      )}

      <h2>Gói của tôi</h2>
      {subs.length === 0 ? (
        <Empty>Bạn chưa đăng ký gói nào.</Empty>
      ) : (
        subs.map((s) => (
          <div key={s.id} className="card">
            <div className="row space">
              <div>
                <strong>{s.plan?.name}</strong> <Badge tone={s.status === 'active' ? 'green' : s.status === 'pending_payment' ? 'yellow' : 'gray'}>{SUBSCRIPTION_STATUS[s.status]}</Badge>
                <div className="muted small">
                  {(s.delivery_days ?? [s.delivery_weekday]).map((d) => WEEKDAYS[d]).join(', ')} · {dateVN(s.starts_on)} → {dateVN(s.ends_on)} · {money(s.total_price)}
                </div>
              </div>
              <div className="row">
                {s.status === 'pending_payment' && <button onClick={() => goToPayment(`/payment/mock/SUB${s.id}`, navigate)}>Thanh toán</button>}
                {s.status === 'active' && <button className="secondary" onClick={() => setStatus(s.id, 'paused')}>Tạm dừng</button>}
                {s.status === 'paused' && <button className="secondary" onClick={() => setStatus(s.id, 'active')}>Tiếp tục</button>}
                {['active', 'paused', 'pending_payment'].includes(s.status) && <button className="link danger-text" onClick={() => setStatus(s.id, 'cancelled')}>Hủy gói</button>}
              </div>
            </div>
            <div className="schedule">
              {s.schedules?.map((d) => (
                <span key={d.id} className={`chip ${d.status}`} title={d.note ?? ''}>
                  {dateVN(d.delivery_date)} · {SCHEDULE_STATUS[d.status] ?? d.status}
                  {d.order && <> · <Link to={`/orders/${d.order.code}`}>{d.order.code}</Link></>}
                </span>
              ))}
            </div>
          </div>
        ))
      )}
    </section>
  );
}

export function ComplaintsPage() {
  const [items, setItems] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    api('/complaints')
      .then((result) => { if (active) setItems(result); })
      .catch((e) => {
        if (active) {
          setError(e.message);
          setItems([]);
        }
      });
    return () => { active = false; };
  }, []);

  return (
    <section>
      <h1>Khiếu nại của tôi</h1>
      <Notice kind="info">
        Để gửi khiếu nại, mở <Link to="/orders">đơn hàng đã giao</Link> và dùng mục &quot;Gửi khiếu nại&quot; trong vòng 24 giờ sau khi nhận hàng (kèm ảnh .jpg/.png).
      </Notice>
      {!items && !error && <Loading />}
      {error && <Notice kind="error">{error}</Notice>}
      {!error && items && items.length === 0 && <Empty>Bạn chưa gửi khiếu nại nào.</Empty>}
      {!error && items?.map((c) => (
        <div className="card" key={c.id}>
          <strong>{c.subject}</strong>{' '}
          <Badge tone={c.status === 'resolved' ? 'green' : c.status === 'rejected' ? 'red' : 'blue'}>{COMPLAINT_STATUS[c.status]}</Badge>
          {c.resolution && <Badge tone="green">{RESOLUTION_LABEL[c.resolution]}</Badge>}
          <div className="muted small">{dateTimeVN(c.created_at)}{c.order && <> · <Link to={`/orders/${c.order.code}`}>Đơn {c.order.code}</Link></>}</div>
          <p>{c.content}</p>
          {c.image_url && <img className="log-media" src={c.image_url} alt="Ảnh minh chứng" />}
          {c.admin_reply && <p className="reply"><strong>Phản hồi:</strong> {c.admin_reply}</p>}
        </div>
      ))}
    </section>
  );
}
