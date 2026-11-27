import { useEffect, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { api, BATCH_STATUS, dateVN, FARM_TYPES, formData, today } from '../../api.js';
import { compressFormImages } from '../../media.js';
import { Badge, Button, Empty, Loading, Modal, useAction } from '../../ui.jsx';
import { Timeline } from '../Shop.jsx';

const daysLeft = (date) => Math.round((new Date(String(date).slice(0, 10)) - new Date(today())) / 86400000);

/* ---------- Batches and waste: the farm staff's mobile dashboard (Tuần 4 screen 2) ---------- */

export function BatchesTab() {
  const [status, setStatus] = useState('available');
  const [q, setQ] = useState('');
  const [batches, setBatches] = useState(null);
  const [wasteFor, setWasteFor] = useState(null);
  const [qrFor, setQrFor] = useState(null);
  const [showNew, setShowNew] = useState(false);

  const [expiring, run] = useAction();

  const load = () => {
    const params = new URLSearchParams({ status, q });
    api(`/admin/batches?${params}`).then((d) => setBatches(d.data)).catch(() => setBatches([]));
  };

  async function expireDue() {
    const res = await run(() => api('/admin/batches/expire-due', { method: 'POST' }), (r) => `Đã chuyển ${r.expired_batches} lô sang "Hết hạn".`);
    if (res) load();
  }

  useEffect(() => {
    load();
  }, [status]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div>
      <div className="toolbar">
        <form className="row grow" onSubmit={(e) => { e.preventDefault(); load(); }}>
          <input placeholder="Tìm theo tên sản phẩm" value={q} onChange={(e) => setQ(e.target.value)} />
          <button className="secondary">Tìm</button>
        </form>
        <select value={status} onChange={(e) => setStatus(e.target.value)} aria-label="Trạng thái lô">
          {Object.entries(BATCH_STATUS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <Button className="secondary" busy={expiring} onClick={expireDue} title="Chạy ngay tác vụ hằng đêm: lô đến hạn sẽ ngừng bán">Đánh dấu lô hết hạn</Button>
        <button onClick={() => setShowNew(true)}>+ Nhập lô mới</button>
      </div>

      {!batches && <Loading />}
      {batches && batches.length === 0 && <Empty>Không có lô nào.</Empty>}
      <div className="batch-cards">
        {batches?.map((b) => {
          const left = daysLeft(b.expires_on);
          return (
            <div key={b.id} className={`card batch-card ${left <= 2 && b.status === 'available' ? 'urgent' : ''}`}>
              <div className="row space">
                <strong>{b.product?.name}</strong>
                <Badge tone={left <= 2 ? 'red' : left <= 5 ? 'yellow' : 'green'}>
                  HSD {dateVN(b.expires_on)}
                  {b.status === 'available' && (left < 0 ? ` · quá hạn ${-left} ngày` : left === 0 ? ' · hết hạn hôm nay' : ` · còn ${left} ngày`)}
                </Badge>
              </div>
              <div className="muted small">{b.batch_code} · {b.farm_plot?.name ?? 'Chưa gắn khu'} · thu hoạch {dateVN(b.harvested_on)}</div>
              <div className="batch-numbers">
                <div><span>Tồn</span><strong>{b.quantity_on_hand}</strong></div>
                <div><span>Nhập</span><strong>{b.quantity_received}</strong></div>
                <div><span>Hao hụt</span><strong>{b.quantity_wasted ?? 0}</strong></div>
              </div>
              <div className="row">
                {b.quantity_on_hand > 0 && <button className="danger" onClick={() => setWasteFor(b)}>Xuất hủy</button>}
                <button className="secondary" onClick={() => setQrFor(b)}>Mã QR</button>
              </div>
            </div>
          );
        })}
      </div>

      {wasteFor && <WasteModal batch={wasteFor} onClose={() => setWasteFor(null)} onDone={() => { setWasteFor(null); load(); }} />}
      {qrFor && <QrModal batch={qrFor} onClose={() => setQrFor(null)} />}
      {showNew && <NewBatchModal onClose={() => setShowNew(false)} onDone={() => { setShowNew(false); load(); }} />}
    </div>
  );
}

const WASTE_REASONS = ['Dập nát khi kiểm kho', 'Héo, úa do lưu kho', 'Hư hỏng do thời tiết', 'Hết hạn sử dụng', 'Khác'];

/** Record waste for one batch, with a photo taken on the spot (Tuần 2 use case 5, Tuần 4). */
function WasteModal({ batch, onClose, onDone }) {
  const [busy, run] = useAction();
  const [reason, setReason] = useState(WASTE_REASONS[0]);

  async function submit(e) {
    e.preventDefault();
    const fd = formData(e.currentTarget);
    const note = fd.get('note');
    fd.set('reason', note ? `${reason}: ${note}` : reason);
    fd.delete('note');
    await compressFormImages(fd, ['photo']);
    const res = await run(
      () => api(`/admin/batches/${batch.id}/waste`, { method: 'POST', form: fd }),
      (b) => `Đã xuất hủy ${fd.get('quantity')} ${batch.product?.unit ?? ''} từ lô ${batch.batch_code}. Còn ${b.quantity_on_hand}.`,
    );
    if (res) onDone();
  }

  return (
    <Modal title={`Xuất hủy – ${batch.product?.name}`} onClose={onClose}>
      <form onSubmit={submit}>
        <p className="muted small">Lô {batch.batch_code} · đang tồn {batch.quantity_on_hand} {batch.product?.unit}</p>
        <label>Số lượng hủy<input name="quantity" type="number" min="1" max={batch.quantity_on_hand} required inputMode="numeric" /></label>
        <label>
          Lý do
          <select value={reason} onChange={(e) => setReason(e.target.value)}>
            {WASTE_REASONS.map((r) => <option key={r}>{r}</option>)}
          </select>
        </label>
        <label>Ghi chú thêm<input name="note" maxLength={150} /></label>
        <label>Ảnh hiện trạng (chụp bằng điện thoại)<input type="file" name="photo" accept="image/*" capture="environment" /></label>
        <Button className="danger" busy={busy}>Xác nhận xuất hủy</Button>
      </form>
    </Modal>
  );
}

/** Sharp vector QR code pointing to the public trace page, ready to print on the packaging label. */
function QrModal({ batch, onClose }) {
  const url = `${window.location.origin}/trace/${batch.trace_code}`;
  return (
    <Modal title="Mã QR truy xuất" onClose={onClose}>
      <div className="qr-label printable">
        <QRCodeSVG value={url} size={220} level="M" includeMargin />
        <strong>{batch.product?.name}</strong>
        <span>Lô {batch.batch_code} · HSD {dateVN(batch.expires_on)}</span>
        <span className="small">{batch.trace_code}</span>
      </div>
      <p className="muted small break">{url}</p>
      <div className="row">
        <button onClick={() => window.print()}>In tem QR</button>
        <a className="button-link outline" href={url} target="_blank" rel="noreferrer">Mở trang truy xuất</a>
      </div>
    </Modal>
  );
}

function NewBatchModal({ onClose, onDone }) {
  const [products, setProducts] = useState([]);
  const [plots, setPlots] = useState([]);
  const [busy, run] = useAction();
  const [productId, setProductId] = useState('');
  const [fefo, setFefo] = useState(null);

  useEffect(() => {
    api('/admin/products?per_page=100').then((d) => setProducts(d.data)).catch(() => {});
    api('/admin/farm-plots').then(setPlots).catch(() => {});
  }, []);

  async function submit(e) {
    e.preventDefault();
    const fd = Object.fromEntries(new FormData(e.currentTarget));
    const res = await run(
      () => api(`/admin/products/${productId}/batches`, {
        method: 'POST',
        body: { ...fd, farm_plot_id: fd.farm_plot_id || null, quantity_received: Number(fd.quantity_received) },
      }),
      (b) => `Đã nhập lô ${b.batch_code}, mã truy xuất ${b.trace_code}.`,
    );
    if (res) onDone();
  }

  async function preview(qty) {
    if (!productId || !qty) return;
    try {
      setFefo(await api(`/admin/products/${productId}/fefo-preview?quantity=${qty}`));
    } catch (e) {
      setFefo({ error: e.message });
    }
  }

  return (
    <Modal title="Nhập lô thu hoạch mới" onClose={onClose}>
      <form onSubmit={submit}>
        <label>
          Sản phẩm
          <select required value={productId} onChange={(e) => { setProductId(e.target.value); setFefo(null); }}>
            <option value="">— Chọn —</option>
            {products.map((p) => <option key={p.id} value={p.id}>{p.name} ({p.stock ?? 0} {p.unit})</option>)}
          </select>
        </label>
        <label>
          Khu canh tác
          <select name="farm_plot_id">
            <option value="">— Chọn khu —</option>
            {plots.map((p) => <option key={p.id} value={p.id}>{p.code} · {p.name}</option>)}
          </select>
        </label>
        <label>Số lượng nhập<input name="quantity_received" type="number" min="1" required inputMode="numeric" /></label>
        <div className="row">
          <label>Ngày thu hoạch<input name="harvested_on" type="date" required defaultValue={today()} /></label>
          <label>Hạn sử dụng<input name="expires_on" type="date" required /></label>
        </div>
        <Button busy={busy} disabled={!productId}>Nhập lô &amp; tạo mã QR</Button>
      </form>

      {productId && (
        <details className="fefo">
          <summary>Thử thuật toán FEFO cho sản phẩm này</summary>
          <div className="row">
            <input type="number" min="1" placeholder="Số lượng" onChange={(e) => preview(e.target.value)} />
          </div>
          {fefo?.error && <p className="red-text small">{fefo.error}</p>}
          {fefo?.plan && (
            <ul className="small">{fefo.plan.map((l) => <li key={l.batch_id}>Lô {l.batch_code} (HSD {dateVN(l.expires_on)}): lấy {l.quantity}</li>)}</ul>
          )}
        </details>
      )}
    </Modal>
  );
}

/* ---------- Farming diary (nhật ký canh tác) ---------- */

export function DiaryTab() {
  const [plots, setPlots] = useState([]);
  const [batches, setBatches] = useState([]);
  const [logs, setLogs] = useState(null);
  const [target, setTarget] = useState('batch');
  const [busy, run] = useAction();

  const loadLogs = () => api('/admin/farm-logs').then((d) => setLogs(d.data)).catch(() => setLogs([]));

  useEffect(() => {
    api('/admin/farm-plots').then(setPlots).catch(() => {});
    api('/admin/batches?status=available').then((d) => setBatches(d.data)).catch(() => {});
    loadLogs();
  }, []);

  async function submit(e) {
    e.preventDefault();
    const formEl = e.currentTarget;
    const fd = formData(formEl);
    if (target === 'batch') fd.delete('farm_plot_id');
    else fd.delete('product_batch_id');
    await compressFormImages(fd, ['media']);
    const res = await run(() => api('/admin/farm-logs', { method: 'POST', form: fd }), 'Đã ghi nhật ký. Khách quét QR của lô sẽ thấy ngay.');
    if (res) {
      formEl.reset();
      loadLogs();
    }
  }

  async function remove(id) {
    if (!window.confirm('Xóa nhật ký này?')) return;
    const res = await run(() => api(`/admin/farm-logs/${id}`, { method: 'DELETE' }), 'Đã xóa.');
    if (res) loadLogs();
  }

  return (
    <div className="two-col">
      <form className="card" onSubmit={submit}>
        <h2>Ghi nhật ký canh tác</h2>
        <div className="choice-group">
          <label className={`choice ${target === 'batch' ? 'selected' : ''}`}>
            <input type="radio" checked={target === 'batch'} onChange={() => setTarget('batch')} />Cho một lô hàng
          </label>
          <label className={`choice ${target === 'plot' ? 'selected' : ''}`}>
            <input type="radio" checked={target === 'plot'} onChange={() => setTarget('plot')} />Cho cả khu trồng
          </label>
        </div>
        {target === 'batch' ? (
          <label>
            Lô hàng
            <select name="product_batch_id" required>
              <option value="">— Chọn lô —</option>
              {batches.map((b) => <option key={b.id} value={b.id}>{b.product?.name} · {b.batch_code}</option>)}
            </select>
          </label>
        ) : (
          <label>
            Khu canh tác
            <select name="farm_plot_id" required>
              <option value="">— Chọn khu —</option>
              {plots.map((p) => <option key={p.id} value={p.id}>{p.code} · {p.name}</option>)}
            </select>
          </label>
        )}
        <div className="row">
          <label>
            Công việc
            <select name="type" required>
              {Object.entries(FARM_TYPES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </label>
          <label>Ngày<input name="logged_on" type="date" required defaultValue={today()} max={today()} /></label>
        </div>
        <label>Mô tả<textarea name="description" rows="3" required placeholder="Ví dụ: Bón phân hữu cơ vi sinh 2 kg/luống" /></label>
        <label>Ảnh hoặc video minh chứng<input type="file" name="media" accept="image/*,video/mp4" capture="environment" /></label>
        <Button busy={busy}>Lưu nhật ký</Button>
      </form>

      <div>
        <h2>Nhật ký gần đây</h2>
        {!logs ? <Loading /> : (
          <>
            <Timeline logs={logs} />
            {logs.length > 0 && (
              <details>
                <summary className="small">Xóa nhật ký ghi nhầm</summary>
                {logs.map((l) => (
                  <div key={l.id} className="row space small">
                    <span>{dateVN(l.logged_on)} · {FARM_TYPES[l.type]} · {l.description.slice(0, 40)}</span>
                    <button className="link danger-text" onClick={() => remove(l.id)}>Xóa</button>
                  </div>
                ))}
              </details>
            )}
          </>
        )}
      </div>
    </div>
  );
}
