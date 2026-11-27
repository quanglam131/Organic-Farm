import { useEffect, useState } from 'react';
import {
  api, COMPLAINT_REASONS, COMPLAINT_STATUS, dateTimeVN, dateVN, money, ORDER_STATUS, PAYMENT_LABEL, PAYMENT_STATUS,
  RESOLUTION_LABEL, SCHEDULE_STATUS,
} from '../../api.js';
import { Badge, Button, Empty, Loading, Notice, StatusBadge, useAction } from '../../ui.jsx';

/* ---------- Orders: confirm (takes stock with FEFO), ship, deliver, cancel ---------- */

const NEXT = {
  pending: [['confirmed', 'Xác nhận & trừ kho (FEFO)'], ['cancelled', 'Hủy']],
  stock_issue: [['confirmed', 'Thử xác nhận lại'], ['cancelled', 'Hủy đơn']],
  confirmed: [['shipping', 'Giao hàng'], ['cancelled', 'Hủy']],
  shipping: [['delivered', 'Đã giao']],
};

export function OrdersTab() {
  const [status, setStatus] = useState('');
  const [todayOnly, setTodayOnly] = useState(false);
  const [q, setQ] = useState('');
  const [orders, setOrders] = useState(null);
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState(null);
  const [, run] = useAction();

  const load = () => {
    setError('');
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (todayOnly) params.set('today', '1');
    if (q) params.set('q', q);
    api(`/admin/orders?${params}`)
      .then((d) => setOrders(d.data))
      .catch((e) => setError(e.message || 'Không thể tải danh sách đơn hàng.'));
  };

  useEffect(() => {
    load();
  }, [status, todayOnly]); // eslint-disable-line react-hooks/exhaustive-deps

  async function move(order, next) {
    if (next === 'cancelled' && !window.confirm(`Hủy đơn ${order.code}?`)) return;
    setBusyId(order.id);
    await run(
      () => api(`/admin/orders/${order.id}/status`, { method: 'PATCH', body: { status: next } }),
      `Đơn ${order.code}: ${ORDER_STATUS[next]}.`,
    );
    setBusyId(null);
    load();
  }

  return (
    <div>
      <div className="toolbar">
        <select value={status} onChange={(e) => setStatus(e.target.value)} aria-label="Trạng thái">
          <option value="">Tất cả trạng thái</option>
          {Object.entries(ORDER_STATUS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <label className="inline"><input type="checkbox" checked={todayOnly} onChange={(e) => setTodayOnly(e.target.checked)} /> Chỉ đơn hôm nay</label>
        <form className="row grow" onSubmit={(e) => { e.preventDefault(); load(); }}>
          <input placeholder="Mã đơn, tên hoặc SĐT khách" value={q} onChange={(e) => setQ(e.target.value)} />
          <button className="secondary">Tìm</button>
        </form>
      </div>

      {error && <><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại đơn hàng</Button></>}
      {!orders && !error && <Loading />}
      {orders && orders.length === 0 && <Empty>Không có đơn nào.</Empty>}
      <div className="order-cards">
        {orders?.map((o) => (
          <div key={o.id} className={`card order-card ${o.status === 'stock_issue' ? 'urgent' : ''}`}>
            <div className="row space">
              <div><strong>{o.code}</strong> <StatusBadge status={o.status} /></div>
              <strong>{money(o.total)}</strong>
            </div>
            <div className="small">
              {o.shipping_name} · <a href={`tel:${o.shipping_phone}`}>{o.shipping_phone}</a> · {o.distance_km} km
              <div className="muted">{o.shipping_address}</div>
              <div className="muted">
                {dateTimeVN(o.created_at)} · {PAYMENT_LABEL[o.payment_method]} · {PAYMENT_STATUS[o.payment_status] ?? o.payment_status}
                {o.delivery_date && ` · giao ngày ${dateVN(o.delivery_date)}`}
              </div>
            </div>
            {o.issue_note && <p className="red-text small">⚠ {o.issue_note}</p>}
            <ul className="small items">
              {o.items.map((i) => (
                <li key={i.id}>{i.product_name} × {i.quantity}{i.batch_code ? <span className="muted"> · lô {i.batch_code}</span> : ''}</li>
              ))}
            </ul>
            {o.note && <p className="muted small">Ghi chú: {o.note}</p>}
            <div className="row">
              {(NEXT[o.status] ?? []).map(([next, label]) => (
                <Button key={next} busy={busyId === o.id && next !== 'cancelled'} disabled={busyId === o.id} className={next === 'cancelled' ? 'link danger-text' : next === 'confirmed' ? '' : 'secondary'} onClick={() => move(o, next)}>
                  {label}
                </Button>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------- Complaints: accept with refund / redelivery, or reject ---------- */

export function ComplaintsTab() {
  const [status, setStatus] = useState('open');
  const [items, setItems] = useState(null);
  const [error, setError] = useState('');

  const load = () => {
    setError('');
    api(`/admin/complaints${status ? `?status=${status}` : ''}`)
      .then((d) => setItems(d.data))
      .catch((e) => setError(e.message || 'Không thể tải danh sách khiếu nại.'));
  };

  useEffect(() => {
    load();
  }, [status]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div>
      <div className="toolbar">
        <select value={status} onChange={(e) => setStatus(e.target.value)} aria-label="Trạng thái">
          <option value="">Tất cả</option>
          {Object.entries(COMPLAINT_STATUS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </div>
      {error && <><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại khiếu nại</Button></>}
      {!items && !error && <Loading />}
      {items && items.length === 0 && <Empty>Không có khiếu nại.</Empty>}
      {items?.map((c) => <ComplaintCard key={c.id} complaint={c} onSaved={load} />)}
    </div>
  );
}

function ComplaintCard({ complaint: c, onSaved }) {
  const [reply, setReply] = useState(c.admin_reply ?? '');
  const [busy, run] = useAction();
  const done = ['resolved', 'rejected'].includes(c.status);

  async function save(body, text) {
    const res = await run(() => api(`/admin/complaints/${c.id}`, { method: 'PATCH', body: { admin_reply: reply || null, ...body } }), text);
    if (res) onSaved();
  }

  return (
    <div className="card complaint">
      <div className="row space">
        <div>
          <strong>{COMPLAINT_REASONS[c.reason] ?? c.subject}</strong>{' '}
          <Badge tone={c.status === 'resolved' ? 'green' : c.status === 'rejected' ? 'red' : 'yellow'}>{COMPLAINT_STATUS[c.status]}</Badge>
          {c.resolution && <Badge tone="green">{RESOLUTION_LABEL[c.resolution]}</Badge>}
        </div>
        <span className="muted small">{dateTimeVN(c.created_at)}</span>
      </div>
      <div className="small">
        {c.user?.name} · {c.user?.phone && <a href={`tel:${c.user.phone}`}>{c.user.phone}</a>} · đơn {c.order?.code} (giao {dateTimeVN(c.order?.delivered_at)})
      </div>
      <p>{c.content}</p>
      <div className="complaint-body">
        {c.image_url && <a href={c.image_url} target="_blank" rel="noreferrer"><img className="log-media" src={c.image_url} alt="Ảnh khách gửi" /></a>}
        {c.order?.items?.length > 0 && (
          <div className="small">
            <strong>Truy ngược lô hàng:</strong>
            <ul>{c.order.items.map((i) => <li key={i.id}>{i.product_name} × {i.quantity} · lô {i.batch_code ?? '—'}</li>)}</ul>
          </div>
        )}
      </div>
      {done ? (
        c.admin_reply && <p className="reply"><strong>Phản hồi:</strong> {c.admin_reply} {c.handler && <span className="muted small">– {c.handler.name}</span>}</p>
      ) : (
        <>
          <textarea rows="2" placeholder="Phản hồi cho khách (bắt buộc khi từ chối)" value={reply} onChange={(e) => setReply(e.target.value)} />
          <div className="row">
            {c.status === 'open' && <Button className="secondary" busy={busy} onClick={() => save({ status: 'in_progress' }, 'Đã nhận xử lý.')}>Nhận xử lý</Button>}
            <Button busy={busy} onClick={() => save({ status: 'resolved', resolution: 'refund' }, 'Đã chấp nhận: hoàn tiền.')}>Hoàn tiền</Button>
            <Button busy={busy} onClick={() => save({ status: 'resolved', resolution: 'redelivery' }, 'Đã chấp nhận: giao bù.')}>Giao bù</Button>
            <Button className="link danger-text" busy={busy} onClick={() => save({ status: 'rejected' }, 'Đã từ chối khiếu nại.')}>Từ chối</Button>
          </div>
        </>
      )}
    </div>
  );
}

/* ---------- Subscription deliveries for the coming week ---------- */

export function DeliveriesTab() {
  const [rows, setRows] = useState(null);
  const [error, setError] = useState('');
  const [busy, run] = useAction();

  const load = () => {
    setError('');
    api('/admin/delivery-schedules')
      .then(setRows)
      .catch((e) => setError(e.message || 'Không thể tải lịch giao hàng.'));
  };

  useEffect(() => {
    load();
  }, []);

  async function retry(row) {
    const res = await run(() => api(`/admin/delivery-schedules/${row.id}/retry`, { method: 'POST' }), 'Đã tạo đơn giao.');
    if (res) load();
  }

  if (error) return <div><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại lịch giao</Button></div>;
  if (!rows) return <Loading />;

  const byDate = rows.reduce((acc, r) => {
    (acc[r.delivery_date] ??= []).push(r);
    return acc;
  }, {});

  return (
    <div>
      <p className="muted small">Lịch giao của các gói định kỳ trong 7 ngày tới. Đơn được tạo tự động trước ngày giao 1 ngày.</p>
      {rows.length === 0 && <Empty>Không có lịch giao nào trong 7 ngày tới.</Empty>}
      {Object.entries(byDate).map(([date, list]) => (
        <div key={date} className="card">
          <h3>{dateVN(date)} · {list.length} lượt giao</h3>
          {list.map((r) => (
            <div key={r.id} className="row space alert-row">
              <div className="small">
                <strong>{r.subscription?.shipping_name}</strong> · {r.subscription?.plan?.name} · <a href={`tel:${r.subscription?.shipping_phone}`}>{r.subscription?.shipping_phone}</a>
                <div className="muted">{r.subscription?.shipping_address}</div>
                {r.note && <div className="red-text">{r.note}</div>}
              </div>
              <div className="row">
                <Badge tone={r.status === 'short' ? 'red' : r.status === 'ordered' ? 'green' : 'gray'}>{SCHEDULE_STATUS[r.status] ?? r.status}</Badge>
                {r.order && <span className="small">{r.order.code}</span>}
                {r.status === 'short' && <Button className="secondary" busy={busy} onClick={() => retry(r)}>Thử lại</Button>}
              </div>
            </div>
          ))}
        </div>
      ))}
    </div>
  );
}
