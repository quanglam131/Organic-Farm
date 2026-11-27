import { useEffect, useState } from 'react';
import { api, formData, money, ROLE_LABEL } from '../../api.js';
import { useAuth } from '../../auth.jsx';
import { compressFormImages, compressImage } from '../../media.js';
import { Badge, Button, Empty, Loading, Modal, Notice, Stat, useAction } from '../../ui.jsx';

/* ---------- Reports (admin only: Staff must never see revenue) ---------- */

export function DashboardTab() {
  const [summary, setSummary] = useState(null);
  const [revenue, setRevenue] = useState(null);
  const [waste, setWaste] = useState([]);
  const [error, setError] = useState('');

  const load = () => {
    setError('');
    Promise.all([
      api('/admin/dashboard'),
      api('/admin/reports/revenue?months=6'),
      api('/admin/reports/waste'),
    ])
      .then(([dashboardData, revenueData, wasteData]) => {
        setSummary(dashboardData);
        setRevenue(revenueData);
        setWaste(wasteData);
      })
      .catch((e) => setError(e.message || 'Không thể tải báo cáo.'));
  };

  useEffect(() => { load(); }, []);

  if (error) return <div><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Thử tải lại</Button></div>;
  if (!summary || !revenue) return <Loading />;

  const maxRevenue = Math.max(1, ...revenue.months.map((m) => m.revenue));

  return (
    <div>
      <div className="stats">
        <Stat label="Doanh thu hôm nay" value={money(summary.revenue_today)} />
        <Stat label="Doanh thu tháng này" value={money(summary.revenue_month)} />
        <Stat label="Đơn hôm nay" value={summary.orders_today} />
        <Stat label="Đơn thiếu hàng" value={summary.stock_issue_orders} tone={summary.stock_issue_orders ? 'warn' : ''} />
        <Stat label="Khiếu nại đang mở" value={summary.open_complaints} />
        <Stat label={`Lô hết hạn trong ${summary.expiry_alert_days} ngày`} value={summary.expiring_batches} tone={summary.expiring_batches ? 'warn' : ''} />
        <Stat label="Giá trị hàng sắp hết hạn" value={money(summary.expiring_value_vnd)} />
        <Stat label="Hao hụt tháng này" value={summary.wasted_this_month} />
      </div>

      <h2>Doanh thu 6 tháng gần nhất</h2>
      <div className="bars" role="img" aria-label="Biểu đồ doanh thu theo tháng">
        {revenue.months.map((m) => (
          <div key={m.month} className="bar-col">
            <span className="small">{m.revenue ? `${Math.round(m.revenue / 1000)}k` : '0'}</span>
            <div className="bar" style={{ height: `${(m.revenue / maxRevenue) * 100}%` }} title={money(m.revenue)} />
            <span className="small">{m.month.slice(5)}/{m.month.slice(2, 4)}</span>
          </div>
        ))}
      </div>

      <div className="two-col">
        <div>
          <h2>Sản phẩm bán chạy</h2>
          {revenue.top_products.length === 0 ? <Empty>Chưa có đơn đã thanh toán.</Empty> : (
            <table className="table">
              <thead><tr><th>Sản phẩm</th><th>SL</th><th>Doanh thu</th></tr></thead>
              <tbody>{revenue.top_products.map((p) => <tr key={p.name}><td>{p.name}</td><td>{p.quantity}</td><td>{money(p.revenue)}</td></tr>)}</tbody>
            </table>
          )}
        </div>
        <div>
          <h2>Hao hụt 30 ngày</h2>
          {waste.length === 0 ? <Empty>Chưa ghi nhận hao hụt.</Empty> : (
            <table className="table">
              <thead><tr><th>Sản phẩm</th><th>SL hủy</th><th>Giá trị</th></tr></thead>
              <tbody>{waste.map((w) => <tr key={w.name}><td>{w.name}</td><td>{w.quantity}</td><td>{money(w.value_vnd)}</td></tr>)}</tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}

/* ---------- Products and photos ---------- */

export function ProductsTab() {
  const [products, setProducts] = useState(null);
  const [categories, setCategories] = useState([]);
  const [error, setError] = useState('');
  const [imagesFor, setImagesFor] = useState(null);
  const [busy, run] = useAction();

  const load = () => {
    setError('');
    api('/admin/products?per_page=100')
      .then((d) => setProducts(d.data))
      .catch((e) => setError(e.message || 'Không thể tải danh sách sản phẩm.'));
  };

  useEffect(() => {
    load();
    api('/admin/categories')
      .then(setCategories)
      .catch((e) => setError(e.message || 'Không thể tải danh mục sản phẩm.'));
  }, []);

  async function create(e) {
    e.preventDefault();
    const formEl = e.currentTarget;
    const fd = await compressFormImages(formData(formEl), ['image']);
    const res = await run(() => api('/admin/products', { method: 'POST', form: fd }), 'Đã thêm sản phẩm.');
    if (res) {
      formEl.reset();
      load();
    }
  }

  async function toggle(p) {
    const res = await run(
      () => (p.is_active
        ? api(`/admin/products/${p.id}`, { method: 'DELETE' })
        : api(`/admin/products/${p.id}`, { method: 'PATCH', body: { is_active: true } })),
      p.is_active ? 'Đã ngừng bán.' : 'Đã mở bán lại.',
    );
    if (res) load();
  }

  async function changePrice(p) {
    const value = window.prompt(`Giá mới cho ${p.name} (VND)`, p.price);
    if (value === null || Number.isNaN(Number(value))) return;
    const res = await run(() => api(`/admin/products/${p.id}`, { method: 'PATCH', body: { price: Number(value) } }), 'Đã cập nhật giá.');
    if (res) load();
  }

  return (
    <div>
      {error && <><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại sản phẩm</Button></>}
      <form className="card" onSubmit={create}>
        <h2>Thêm sản phẩm</h2>
        <div className="row">
          <label className="grow">Tên sản phẩm<input name="name" required /></label>
          <label>
            Danh mục
            <select name="category_id">
              <option value="">— Chưa phân loại —</option>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </label>
          <label>Đơn vị<input name="unit" defaultValue="kg" required className="narrow-input" /></label>
          <label>Giá (VND)<input name="price" type="number" min="0" required /></label>
        </div>
        <label>Mô tả<textarea name="description" rows="2" /></label>
        <label>Ảnh chính (tự nén trước khi tải lên)<input type="file" name="image" accept="image/*" /></label>
        <Button busy={busy}>Thêm sản phẩm</Button>
      </form>

      <h2>Sản phẩm</h2>
      {!products ? <Loading /> : (
        <div className="table-wrap">
          <table className="table">
            <thead><tr><th /><th>Sản phẩm</th><th>Giá</th><th>Tồn</th><th>Trạng thái</th><th /></tr></thead>
            <tbody>
              {products.map((p) => (
                <tr key={p.id}>
                  <td>{p.image_url ? <img className="thumb-img" src={p.image_url} alt="" /> : '🥬'}</td>
                  <td>{p.name}<div className="muted small">{p.category?.name ?? '—'}</div></td>
                  <td>{money(p.price)} / {p.unit}</td>
                  <td>{p.stock ?? 0}</td>
                  <td>{p.is_active ? <Badge tone="green">Đang bán</Badge> : <Badge>Ngừng bán</Badge>}</td>
                  <td className="actions">
                    <button className="link" onClick={() => setImagesFor(p)}>Ảnh</button>
                    <button className="link" onClick={() => changePrice(p)}>Giá</button>
                    <button className={`link ${p.is_active ? 'danger-text' : ''}`} onClick={() => toggle(p)}>{p.is_active ? 'Ngừng bán' : 'Mở bán'}</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {imagesFor && <ImageManager product={imagesFor} onClose={() => { setImagesFor(null); load(); }} />}
    </div>
  );
}

/** Photos of one product: upload, add by URL, choose the main photo, reorder, delete. */
function ImageManager({ product, onClose }) {
  const [images, setImages] = useState(null);
  const [error, setError] = useState('');
  const [busy, run] = useAction();

  const load = () => {
    setError('');
    api(`/admin/products/${product.id}/images`)
      .then(setImages)
      .catch((e) => setError(e.message || 'Không thể tải ảnh sản phẩm.'));
  };

  useEffect(() => {
    load();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function upload(e) {
    e.preventDefault();
    const formEl = e.currentTarget;
    const input = new FormData(formEl);
    const fd = new FormData();
    const file = input.get('image');
    const url = String(input.get('url') ?? '').trim();
    if (file instanceof File && file.size > 0) fd.append('image', await compressImage(file));
    else if (url) fd.append('url', url);
    else return;
    if (input.get('alt')) fd.append('alt', input.get('alt'));
    const res = await run(() => api(`/admin/products/${product.id}/images`, { method: 'POST', form: fd }), 'Đã thêm ảnh.');
    if (res) {
      formEl.reset();
      load();
    }
  }

  const patch = async (img, body, text) => {
    const res = await run(() => api(`/admin/product-images/${img.id}`, { method: 'PATCH', body }), text);
    if (res) load();
  };

  async function remove(img) {
    if (!window.confirm('Xóa ảnh này?')) return;
    const res = await run(() => api(`/admin/product-images/${img.id}`, { method: 'DELETE' }), 'Đã xóa ảnh.');
    if (res) load();
  }

  return (
    <Modal title={`Ảnh – ${product.name}`} onClose={onClose}>
      {error && <><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại ảnh</Button></>}
      {!images ? <Loading /> : images.length === 0 ? <Empty>Chưa có ảnh.</Empty> : (
        <div className="image-grid">
          {images.map((img, i) => (
            <div key={img.id} className={`image-tile ${img.is_primary ? 'primary' : ''}`}>
              <img src={img.url} alt={img.alt ?? ''} />
              {img.is_primary ? <Badge tone="green">Ảnh chính</Badge> : <button className="link" onClick={() => patch(img, { is_primary: true }, 'Đã đặt làm ảnh chính.')}>Đặt làm ảnh chính</button>}
              <div className="row">
                <button className="link" disabled={i === 0} onClick={() => patch(img, { sort_order: Math.max(0, img.sort_order - 1) })} aria-label="Lên">◀</button>
                <button className="link" onClick={() => patch(img, { sort_order: img.sort_order + 1 })} aria-label="Xuống">▶</button>
                <button className="link danger-text" onClick={() => remove(img)}>Xóa</button>
              </div>
            </div>
          ))}
        </div>
      )}
      <form onSubmit={upload}>
        <label>Tải ảnh lên<input type="file" name="image" accept="image/*" /></label>
        <label>hoặc dán đường dẫn ảnh<input name="url" type="url" placeholder="https://…" /></label>
        <label>Mô tả ảnh<input name="alt" maxLength={150} /></label>
        <Button busy={busy}>Thêm ảnh</Button>
      </form>
    </Modal>
  );
}

/* ---------- Categories and growing areas ---------- */

export function CatalogTab() {
  const [categories, setCategories] = useState([]);
  const [plots, setPlots] = useState([]);
  const [error, setError] = useState('');
  const [busy, run] = useAction();

  const load = () => {
    setError('');
    Promise.all([api('/admin/categories'), api('/admin/farm-plots')])
      .then(([categoryData, plotData]) => {
        setCategories(categoryData);
        setPlots(plotData);
      })
      .catch((e) => setError(e.message || 'Không thể tải danh mục và khu canh tác.'));
  };

  useEffect(load, []);

  async function addCategory(e) {
    e.preventDefault();
    const formEl = e.currentTarget;
    const name = new FormData(formEl).get('name');
    const res = await run(() => api('/admin/categories', { method: 'POST', body: { name } }), 'Đã thêm danh mục.');
    if (res) { formEl.reset(); load(); }
  }

  async function removeCategory(c) {
    if (!window.confirm(`Xóa danh mục ${c.name}?`)) return;
    const res = await run(() => api(`/admin/categories/${c.id}`, { method: 'DELETE' }), 'Đã xóa danh mục.');
    if (res) load();
  }

  async function addPlot(e) {
    e.preventDefault();
    const formEl = e.currentTarget;
    const body = Object.fromEntries(new FormData(formEl));
    const res = await run(() => api('/admin/farm-plots', { method: 'POST', body }), 'Đã thêm khu canh tác.');
    if (res) { formEl.reset(); load(); }
  }

  return (
    <div>
      {error && <><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại danh mục và khu canh tác</Button></>}
      <div className="two-col">
      <div className="card">
        <h2>Danh mục</h2>
        <form className="row" onSubmit={addCategory}>
          <input name="name" placeholder="Ví dụ: Rau ăn lá" required />
          <Button busy={busy}>Thêm</Button>
        </form>
        <ul>
          {categories.map((c) => <li key={c.id} className="row space">{c.name}<button className="link danger-text" onClick={() => removeCategory(c)}>Xóa</button></li>)}
        </ul>
      </div>

      <div className="card">
        <h2>Khu canh tác của nông trại</h2>
        <form onSubmit={addPlot}>
          <div className="row">
            <label>Mã khu<input name="code" required placeholder="LD-03" /></label>
            <label className="grow">Tên khu<input name="name" required /></label>
          </div>
          <div className="row">
            <label className="grow">Vị trí<input name="location" /></label>
            <label>Diện tích (m²)<input name="area_m2" type="number" min="0" step="0.01" /></label>
          </div>
          <label>Chứng nhận<input name="certification" placeholder="VietGAP, hữu cơ…" /></label>
          <label>Mô tả<textarea name="description" rows="2" /></label>
          <Button busy={busy}>Thêm khu</Button>
        </form>
        <ul className="small">{plots.map((p) => <li key={p.id}>{p.code} · {p.name} ({p.logs_count} nhật ký)</li>)}</ul>
      </div>
      </div>
    </div>
  );
}

/* ---------- Subscription plans ---------- */

export function PlansTab() {
  const [plans, setPlans] = useState(null);
  const [products, setProducts] = useState(null);
  const [quantities, setQuantities] = useState({});
  const [error, setError] = useState('');
  const [busy, run] = useAction();

  const load = () => {
    setError('');
    Promise.all([
      api('/admin/subscription-plans'),
      api('/admin/products?per_page=100'),
    ])
      .then(([planData, productData]) => {
        setPlans(planData);
        setProducts(productData.data.filter((p) => p.is_active));
      })
      .catch((e) => setError(e.message || 'Không thể tải dữ liệu gói định kỳ.'));
  };

  useEffect(() => { load(); }, []);

  if (error) return <div><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại gói định kỳ</Button></div>;
  if (!plans || !products) return <Loading />;

  async function create(e) {
    e.preventDefault();
    const formEl = e.currentTarget;
    const fd = new FormData(formEl);
    const items = Object.entries(quantities)
      .filter(([, q]) => Number(q) > 0)
      .map(([productId, q]) => ({ product_id: Number(productId), quantity: Number(q) }));

    const res = await run(() => {
      if (items.length === 0) throw new Error('Chọn ít nhất một sản phẩm cho gói.');
      return api('/admin/subscription-plans', {
        method: 'POST',
        body: { name: fd.get('name'), description: fd.get('description') || null, discount_percent: Number(fd.get('discount_percent') || 0), items },
      });
    }, 'Đã tạo gói.');
    if (res) {
      formEl.reset();
      setQuantities({});
      load();
    }
  }

  async function remove(plan) {
    const res = await run(() => api(`/admin/subscription-plans/${plan.id}`, { method: 'DELETE' }), (r) => r.message);
    if (res) load();
  }

  return (
    <div className="two-col">
      <form className="card" onSubmit={create}>
        <h2>Tạo gói định kỳ</h2>
        <div className="row">
          <label className="grow">Tên gói<input name="name" required /></label>
          <label>Giảm giá (%)<input name="discount_percent" type="number" min="0" max="100" defaultValue="10" /></label>
        </div>
        <label>Mô tả<input name="description" /></label>
        <h3>Sản phẩm mỗi lần giao</h3>
        <div className="plan-products">
          {products.map((p) => (
            <label key={p.id} className="row space">
              <span>{p.name} <span className="muted small">{money(p.price)}/{p.unit}</span></span>
              <input type="number" min="0" className="narrow-input" value={quantities[p.id] ?? 0} onChange={(e) => setQuantities({ ...quantities, [p.id]: e.target.value })} />
            </label>
          ))}
        </div>
        <Button busy={busy}>Tạo gói</Button>
      </form>

      <div>
        <h2>Các gói</h2>
        {plans.length === 0 && <Empty>Chưa có gói nào.</Empty>}
        {plans.map((p) => (
          <div className="card" key={p.id}>
            <strong>{p.name}</strong> {p.is_active ? <Badge tone="green">Đang mở</Badge> : <Badge>Tạm ngừng</Badge>}
            <span className="muted small"> · giảm {p.discount_percent}%</span>
            <ul className="small">{p.items.map((i) => <li key={i.id}>{i.product?.name} × {i.quantity}</li>)}</ul>
            <button className="link danger-text" onClick={() => remove(p)}>Xóa / ngừng mở bán</button>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------- Staff accounts and customers ---------- */

export function UsersTab() {
  const { user: me } = useAuth();
  const [role, setRole] = useState('');
  const [q, setQ] = useState('');
  const [users, setUsers] = useState(null);
  const [error, setError] = useState('');
  const [showNew, setShowNew] = useState(false);
  const [busy, run] = useAction();

  const load = () => {
    setError('');
    const params = new URLSearchParams({ q, role });
    api(`/admin/users?${params}`)
      .then((d) => setUsers(d.data))
      .catch((e) => setError(e.message || 'Không thể tải danh sách tài khoản.'));
  };

  useEffect(() => {
    load();
  }, [role]); // eslint-disable-line react-hooks/exhaustive-deps

  async function update(u, body) {
    const res = await run(() => api(`/admin/users/${u.id}`, { method: 'PATCH', body }), 'Đã cập nhật tài khoản.');
    if (res) load();
  }

  async function create(e) {
    e.preventDefault();
    const body = Object.fromEntries(new FormData(e.currentTarget));
    const res = await run(() => api('/admin/users', { method: 'POST', body }), (u) => `Đã tạo tài khoản ${u.email}.`);
    if (res) {
      setShowNew(false);
      load();
    }
  }

  return (
    <div>
      <div className="toolbar">
        <select value={role} onChange={(e) => setRole(e.target.value)} aria-label="Vai trò">
          <option value="">Tất cả vai trò</option>
          {Object.entries(ROLE_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <form className="row grow" onSubmit={(e) => { e.preventDefault(); load(); }}>
          <input placeholder="Tìm theo tên hoặc email" value={q} onChange={(e) => setQ(e.target.value)} />
          <button className="secondary">Tìm</button>
        </form>
        <button onClick={() => setShowNew(true)}>+ Thêm nhân viên</button>
      </div>

      {error && <><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại tài khoản</Button></>}
      {!users && !error ? <Loading /> : users && (
        <div className="table-wrap">
          <table className="table">
            <thead><tr><th>Tên</th><th>Email</th><th>Vai trò</th><th>Điểm</th><th>Trạng thái</th></tr></thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td>{u.name}<div className="muted small">{u.phone}</div></td>
                  <td>{u.email}</td>
                  <td>
                    <select value={u.role} disabled={u.id === me.id} onChange={(e) => update(u, { role: e.target.value })}>
                      {Object.entries(ROLE_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                  </td>
                  <td>{u.points_balance}</td>
                  <td>
                    {u.id === me.id ? <span className="muted small">Bạn</span> : (
                      <button className={`link ${u.is_active ? 'danger-text' : ''}`} onClick={() => update(u, { is_active: !u.is_active })}>
                        {u.is_active ? 'Khóa' : 'Mở khóa'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showNew && (
        <Modal title="Thêm tài khoản nhân viên" onClose={() => setShowNew(false)}>
          <form onSubmit={create}>
            <label>Họ tên<input name="name" required /></label>
            <label>Email<input name="email" type="email" required /></label>
            <label>Số điện thoại<input name="phone" /></label>
            <label>
              Vai trò
              <select name="role" required defaultValue="staff">
                <option value="staff">{ROLE_LABEL.staff}</option>
                <option value="sales">{ROLE_LABEL.sales}</option>
                <option value="admin">{ROLE_LABEL.admin}</option>
              </select>
            </label>
            <label>Mật khẩu ban đầu (tối thiểu 8 ký tự)<input name="password" type="password" minLength={8} required autoComplete="new-password" /></label>
            <Button busy={busy}>Tạo tài khoản</Button>
          </form>
        </Modal>
      )}
    </div>
  );
}

/* ---------- Settings ---------- */

export function SettingsTab() {
  const [settings, setSettings] = useState(null);
  const [error, setError] = useState('');
  const [busy, run] = useAction();

  const load = () => {
    setError('');
    api('/admin/settings')
      .then(setSettings)
      .catch((e) => setError(e.message || 'Không thể tải cấu hình.'));
  };

  useEffect(() => { load(); }, []);

  async function save(e) {
    e.preventDefault();
    const res = await run(
      () => api('/admin/settings', {
        method: 'PUT',
        body: { expiry_alert_days: Number(settings.expiry_alert_days), flash_sale_discount_percent: Number(settings.flash_sale_discount_percent) },
      }),
      'Đã lưu cấu hình.',
    );
    if (res) setSettings(res);
  }

  if (error) return <div><Notice kind="error">{error}</Notice><Button className="secondary" onClick={load}>Tải lại cấu hình</Button></div>;
  if (!settings) return <Loading />;

  return (
    <form className="card narrow" onSubmit={save}>
      <h2>Cấu hình cảnh báo xả hàng</h2>
      <label>
        Báo đỏ cho nhân viên khi lô còn bao nhiêu ngày đến hạn
        <input type="number" min="1" max="14" value={settings.expiry_alert_days} onChange={(e) => setSettings({ ...settings, expiry_alert_days: e.target.value })} />
      </label>
      <label>
        Mức giảm giá đề xuất khi xả hàng (%)
        <input type="number" min="0" max="90" value={settings.flash_sale_discount_percent} onChange={(e) => setSettings({ ...settings, flash_sale_discount_percent: e.target.value })} />
      </label>
      <Button busy={busy}>Lưu</Button>
    </form>
  );
}
