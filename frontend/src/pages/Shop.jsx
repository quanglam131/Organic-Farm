import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { QRCodeSVG } from 'qrcode.react';
import { api, dateVN, FARM_TYPES, money, PAYMENT_LABEL } from '../api.js';
import { useAuth } from '../auth.jsx';
import { useFarm } from '../farm.jsx';
import { Badge, Button, Empty, Loading, Notice, Spinner, useAction, useToast } from '../ui.jsx';

/* ---------- Home: the farm + its products ---------- */

export function HomePage() {
  const farm = useFarm();
  const navigate = useNavigate();
  const [categories, setCategories] = useState([]);
  const [products, setProducts] = useState(null);
  const [meta, setMeta] = useState({ page: 1, last_page: 1 });
  const [filters, setFilters] = useState({ category: '', q: '', sort: '' });
  const [error, setError] = useState('');

  useEffect(() => {
    api('/categories').then(setCategories).catch(() => {});
  }, []);

  useEffect(() => {
    const params = new URLSearchParams(Object.entries(filters).filter(([, v]) => v));
    params.set('page', meta.page);
    api(`/products?${params}`)
      .then((d) => {
        setProducts(d.data);
        setMeta({ page: d.current_page, last_page: d.last_page });
      })
      .catch((e) => setError(e.message));
  }, [filters, meta.page]);

  const update = (key) => (e) => {
    setFilters({ ...filters, [key]: e.target.value });
    setMeta({ ...meta, page: 1 });
  };

  return (
    <section>
      <div className="hero">
        <div>
          <p className="eyebrow">{farm.certifications?.join(' · ') || 'Nông trại gia đình'}</p>
          <h1>{farm.name}</h1>
          <p className="hero-slogan">{farm.slogan}</p>
          <div className="row">
            <a href="#san-pham" className="button-link light">Mua rau hôm nay</a>
            <Link to="/subscriptions" className="button-link ghost">Đăng ký gói tháng</Link>
          </div>
        </div>
      </div>

      <div className="features">
        <div className="feature"><span>🔍</span><div><strong>Truy xuất từng lô</strong><p className="muted small">Quét mã QR để xem ngày gieo, bón phân, thu hoạch.</p></div></div>
        <div className="feature"><span>🚚</span><div><strong>Giao trong ngày</strong><p className="muted small">Thu hoạch buổi sáng, giao nội thành trong ngày.</p></div></div>
        <div className="feature"><span>📅</span><div><strong>Gói định kỳ</strong><p className="muted small">Trả trước 1 tháng, tự giao vào thứ bạn chọn.</p></div></div>
      </div>

      <form
        className="toolbar trace-lookup"
        onSubmit={(e) => {
          e.preventDefault();
          const code = String(new FormData(e.currentTarget).get('code') || '').trim();
          if (code) navigate(`/trace/${encodeURIComponent(code)}`);
        }}
      >
        <input name="code" placeholder="Nhập mã truy xuất trên bao bì (ví dụ TR449C187C50)" aria-label="Mã truy xuất" />
        <button className="secondary">Truy xuất nguồn gốc</button>
      </form>

      <h2 id="san-pham">Sản phẩm của nông trại</h2>
      <div className="toolbar">
        <input placeholder="Tìm sản phẩm…" value={filters.q} onChange={update('q')} aria-label="Tìm sản phẩm" />
        <select value={filters.category} onChange={update('category')} aria-label="Danh mục">
          <option value="">Tất cả danh mục</option>
          {categories.map((c) => <option key={c.id} value={c.slug}>{c.name}</option>)}
        </select>
        <select value={filters.sort} onChange={update('sort')} aria-label="Sắp xếp">
          <option value="">Mới nhất</option>
          <option value="price_asc">Giá tăng dần</option>
          <option value="price_desc">Giá giảm dần</option>
        </select>
      </div>

      <Notice kind="error">{error}</Notice>
      {!products && !error && <Loading />}
      {products && products.length === 0 && <Empty>Chưa có sản phẩm phù hợp.</Empty>}

      {products && products.length > 0 && (
        <div className="grid">
          {products.map((p) => <ProductCard key={p.id} product={p} />)}
        </div>
      )}

      {meta.last_page > 1 && (
        <div className="pager">
          <button className="secondary" disabled={meta.page <= 1} onClick={() => setMeta({ ...meta, page: meta.page - 1 })}>‹ Trước</button>
          <span>Trang {meta.page} / {meta.last_page}</span>
          <button className="secondary" disabled={meta.page >= meta.last_page} onClick={() => setMeta({ ...meta, page: meta.page + 1 })}>Sau ›</button>
        </div>
      )}
    </section>
  );
}

function ProductCard({ product: p }) {
  const inStock = p.sellable_stock > 0;
  const addToCart = useAddToCart();

  return (
    <div className="card product">
      <Link to={`/products/${p.slug}`} className="product-link">
        {p.image_url ? <img src={p.image_url} alt={p.name} loading="lazy" /> : <div className="placeholder">🥬</div>}
        <h3>{p.name}</h3>
      </Link>
      <p className="price">{money(p.price)} <span className="muted small">/ {p.unit}</span></p>
      <div className="row space">
        <Badge tone={inStock ? 'green' : 'gray'}>{inStock ? `Còn ${p.sellable_stock} ${p.unit}` : 'Hết hàng'}</Badge>
        <Button className="small-button" busy={addToCart.busy} disabled={!inStock} onClick={() => addToCart.add(p, 1)}>
          + Giỏ
        </Button>
      </div>
    </div>
  );
}

/**
 * Add to cart in the background (AJAX): the button shows a spinner while the server checks
 * the batches' stock, then a toast confirms (Tuần 6).
 */
function useAddToCart() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [busy, run] = useAction();

  const add = async (product, quantity) => {
    if (!user) {
      navigate('/login', { state: { from: `/products/${product.slug}` } });
      return;
    }
    await run(
      () => api('/cart', { method: 'POST', body: { product_id: product.id, quantity } }),
      (item) => `Đã thêm ${quantity} ${product.unit} ${product.name} vào giỏ (đang có ${item.quantity}).`,
    );
  };

  return { busy, add };
}

/* ---------- Product detail: batches, QR, farming diary, reviews ---------- */

function ProductGallery({ images = [], fallback, name }) {
  const list = images.length > 0 ? images : (fallback ? [{ id: 0, url: fallback, alt: name }] : []);
  const [index, setIndex] = useState(0);

  useEffect(() => setIndex(0), [images]);

  if (list.length === 0) return <div className="placeholder big">🥬</div>;
  const current = list[Math.min(index, list.length - 1)];

  return (
    <div className="gallery">
      <img className="hero-img" src={current.url} alt={current.alt || name} />
      {list.length > 1 && (
        <div className="thumbs">
          {list.map((img, i) => (
            <button key={img.id} className={`thumb ${i === index ? 'active' : ''}`} onClick={() => setIndex(i)} aria-label={`Ảnh ${i + 1}`}>
              <img src={img.url} alt="" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

export function ProductPage() {
  const { slug } = useParams();
  const [product, setProduct] = useState(null);
  const [qty, setQty] = useState(1);
  const [error, setError] = useState('');
  const addToCart = useAddToCart();

  useEffect(() => {
    setProduct(null);
    api(`/products/${slug}`).then(setProduct).catch((e) => setError(e.message));
  }, [slug]);

  if (!product) return error ? <Notice kind="error">{error}</Notice> : <Loading />;

  const current = product.batches[0];
  const traceUrl = current ? `${window.location.origin}/trace/${current.trace_code}` : '';

  return (
    <section>
      <div className="detail">
        <ProductGallery images={product.images} fallback={product.image_url} name={product.name} />

        <div>
          <p className="muted small">{product.category?.name}</p>
          <h1>{product.name}</h1>
          {product.rating?.count > 0 && (
            <p className="rating">{'★'.repeat(Math.round(product.rating.average))}{'☆'.repeat(5 - Math.round(product.rating.average))} <span className="muted small">{product.rating.average}/5 · {product.rating.count} đánh giá</span></p>
          )}
          <p className="price big">{money(product.price)} / {product.unit}</p>
          {product.description && <p>{product.description}</p>}

          {current && (
            <div className="batch-highlight">
              <div><span className="muted small">Lô đang bán</span><strong>{current.batch_code}</strong></div>
              <div><span className="muted small">Thu hoạch</span><strong>{dateVN(current.harvested_on)}</strong></div>
              <div><span className="muted small">Hạn sử dụng</span><strong>{dateVN(current.expires_on)}</strong></div>
              <div><span className="muted small">Khu canh tác</span><strong>{current.farm_plot?.name ?? '—'}</strong></div>
            </div>
          )}

          <p>Còn bán: <strong>{product.sellable_stock} {product.unit}</strong></p>

          <div className="buy-box">
            <div className="qty">
              <button className="secondary" onClick={() => setQty(Math.max(1, qty - 1))} aria-label="Giảm">−</button>
              <input type="number" min="1" max={product.sellable_stock || 1} value={qty} onChange={(e) => setQty(Math.max(1, Number(e.target.value) || 1))} aria-label="Số lượng" />
              <button className="secondary" onClick={() => setQty(Math.min(product.sellable_stock || 1, qty + 1))} aria-label="Tăng">+</button>
            </div>
            <Button busy={addToCart.busy} disabled={!product.sellable_stock} onClick={() => addToCart.add(product, qty)}>
              Mua lẻ – thêm vào giỏ
            </Button>
          </div>

          {product.plans?.length > 0 && (
            <div className="card plan-offer">
              <strong>Hoặc mua theo gói định kỳ</strong>
              <p className="muted small">Sản phẩm này có trong:</p>
              {product.plans.map((plan) => (
                <Link key={plan.id} to={`/subscriptions?plan=${plan.id}`} className="button-link outline">
                  {plan.name}{plan.discount_percent > 0 ? ` (−${plan.discount_percent}%)` : ''}
                </Link>
              ))}
            </div>
          )}
        </div>
      </div>

      <div className="two-col">
        <div>
          <h2>Lô hàng và nguồn gốc</h2>
          {product.batches.length === 0 ? (
            <Empty>Hiện chưa có lô hàng còn bán.</Empty>
          ) : (
            <>
              <p className="muted small">Bạn sẽ nhận hàng từ lô có hạn sử dụng gần nhất (nguyên tắc FEFO), luôn còn hạn khi giao.</p>
              <div className="table-wrap">
                <table className="table">
                  <thead><tr><th>Lô</th><th>Thu hoạch</th><th>Hạn dùng</th><th>Còn</th><th /></tr></thead>
                  <tbody>
                    {product.batches.map((b) => (
                      <tr key={b.id}>
                        <td>{b.batch_code}<div className="muted small">{b.farm_plot?.name}</div></td>
                        <td>{dateVN(b.harvested_on)}</td>
                        <td>{dateVN(b.expires_on)}</td>
                        <td>{b.quantity_on_hand} {product.unit}</td>
                        <td><Link to={`/trace/${b.trace_code}`}>Truy xuất</Link></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </div>

        {current && (
          <div className="card qr">
            <h3>Mã QR truy xuất</h3>
            <QRCodeSVG value={traceUrl} size={200} level="M" includeMargin />
            <p className="muted small">Quét bằng điện thoại để mở trang truy xuất lô {current.batch_code}.</p>
            <Link to={`/trace/${current.trace_code}`} className="small">{current.trace_code}</Link>
          </div>
        )}
      </div>

      <h2>Nhật ký canh tác</h2>
      <Timeline logs={product.timeline ?? []} />

      <h2>Đánh giá của khách hàng</h2>
      {product.reviews?.length ? (
        <div className="reviews">
          {product.reviews.map((r) => (
            <div className="card review" key={r.id}>
              <strong>{r.user?.name}</strong> <span className="rating">{'★'.repeat(r.rating)}{'☆'.repeat(5 - r.rating)}</span>
              <span className="muted small"> · {dateVN(r.created_at)}</span>
              {r.comment && <p>{r.comment}</p>}
            </div>
          ))}
        </div>
      ) : (
        <Empty>Chưa có đánh giá. Khách đã nhận hàng có thể đánh giá trong trang đơn hàng.</Empty>
      )}
    </section>
  );
}

/** Farming diary as a vertical timeline with photos and videos. */
export function Timeline({ logs }) {
  if (logs.length === 0) return <Empty>Chưa có nhật ký canh tác.</Empty>;

  return (
    <ol className="timeline">
      {logs.map((l) => (
        <li key={l.id} className={`type-${l.type}`}>
          <div>
            <strong>{dateVN(l.logged_on)}</strong> · <Badge tone="green">{FARM_TYPES[l.type] ?? l.type}</Badge>
            {l.batch?.batch_code && <span className="muted small"> · lô {l.batch.batch_code}</span>}
            {l.author?.name && <span className="muted small"> · {l.author.name}</span>}
          </div>
          <p>{l.description}</p>
          {l.media_url && (l.media_type === 'video'
            ? <video className="log-media" src={l.media_url} controls preload="metadata" />
            : <img className="log-media" src={l.media_url} alt={FARM_TYPES[l.type]} loading="lazy" />)}
        </li>
      ))}
    </ol>
  );
}

/* ---------- Cart (updates in the background) ---------- */

export function CartPage() {
  const [cart, setCart] = useState(null);
  const [rowBusy, setRowBusy] = useState(null);
  const [error, setError] = useState('');
  const toast = useToast();
  const navigate = useNavigate();

  const load = () => api('/cart').then(setCart).catch((e) => setError(e.message));

  useEffect(() => {
    load();
  }, []);

  async function changeQuantity(item, quantity) {
    if (quantity < 1 || quantity === item.quantity) return;
    setRowBusy(item.product_id);
    try {
      await api(`/cart/${item.product_id}`, { method: 'PATCH', body: { quantity } });
      await load();
    } catch (e) {
      toast.error(e.message);
    } finally {
      setRowBusy(null);
    }
  }

  async function remove(item) {
    setRowBusy(item.product_id);
    try {
      await api(`/cart/${item.product_id}`, { method: 'DELETE' });
      await load();
      toast.info(`Đã bỏ ${item.name} khỏi giỏ.`);
    } finally {
      setRowBusy(null);
    }
  }

  if (!cart) return error ? <Notice kind="error">{error}</Notice> : <Loading />;
  if (cart.items.length === 0) {
    return <Empty>Giỏ hàng trống. <Link to="/">Tiếp tục mua sắm</Link></Empty>;
  }

  return (
    <section>
      <h1>Giỏ hàng</h1>
      <div className="cart-list">
        {cart.items.map((item) => (
          <div className={`card cart-item ${rowBusy === item.product_id ? 'busy' : ''}`} key={item.product_id}>
            {item.image_url ? <img src={item.image_url} alt="" /> : <div className="placeholder small-ph">🥬</div>}
            <div className="cart-info">
              <strong>{item.name}</strong>{!item.is_active && <Badge tone="red">Ngừng bán</Badge>}
              <div className="muted small">{money(item.price)} / {item.unit} · còn {item.available_stock} {item.unit}</div>
              <div className="qty">
                <button className="secondary" disabled={rowBusy !== null || item.quantity <= 1} onClick={() => changeQuantity(item, item.quantity - 1)} aria-label="Giảm">−</button>
                <span className="qty-value">{rowBusy === item.product_id ? <Spinner /> : item.quantity}</span>
                <button className="secondary" disabled={rowBusy !== null || item.quantity >= item.available_stock} onClick={() => changeQuantity(item, item.quantity + 1)} aria-label="Tăng">+</button>
              </div>
            </div>
            <div className="cart-total">
              <strong>{money(item.line_total)}</strong>
              <button className="link danger-text" disabled={rowBusy !== null} onClick={() => remove(item)}>Xóa</button>
            </div>
          </div>
        ))}
      </div>
      <div className="row space checkout-bar">
        <p className="total">Tạm tính: <strong>{money(cart.subtotal)}</strong></p>
        <button onClick={() => navigate('/checkout')}>Tiến hành đặt hàng</button>
      </div>
    </section>
  );
}

/* ---------- Checkout: shipping fee from the address, payment ---------- */

export function CheckoutPage() {
  const { user, refresh } = useAuth();
  const farm = useFarm();
  const navigate = useNavigate();
  const toast = useToast();
  const [cart, setCart] = useState(null);
  const [form, setForm] = useState({
    shipping_name: user?.name ?? '',
    shipping_phone: user?.phone ?? '',
    shipping_address: user?.address ?? '',
    payment_method: 'cod',
    points_to_use: 0,
    distance_km: 5,
    note: '',
  });
  const [quote, setQuote] = useState(null);
  const [quoting, setQuoting] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const timer = useRef();

  useEffect(() => {
    api('/cart').then(setCart).catch((e) => setError(e.message));
    refresh().catch(() => {});
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Ask the server for the distance from the farm to the address (debounced while typing).
  useEffect(() => {
    if (!cart) return undefined;
    clearTimeout(timer.current);
    setQuoting(true);
    timer.current = setTimeout(() => {
      const params = new URLSearchParams({ subtotal: cart.subtotal, address: form.shipping_address, distance_km: form.distance_km || 0 });
      api(`/shipping/estimate?${params}`)
        .then(setQuote)
        .catch(() => setQuote(null))
        .finally(() => setQuoting(false));
    }, 700);
    return () => clearTimeout(timer.current);
  }, [cart, form.shipping_address, form.distance_km]);

  if (!cart) return error ? <Notice kind="error">{error}</Notice> : <Loading />;
  if (cart.items.length === 0) return <Empty>Giỏ hàng trống. <Link to="/">Chọn sản phẩm</Link></Empty>;

  const shippingFee = quote?.shipping_fee ?? 0;
  const maxPointsDiscount = Math.floor((cart.subtotal * 50) / 100);
  const pointsDiscount = Math.min(Number(form.points_to_use) * 1000, maxPointsDiscount);
  const estimatedTotal = cart.subtotal + shippingFee - pointsDiscount;
  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await api('/checkout', {
        method: 'POST',
        body: { ...form, points_to_use: Number(form.points_to_use) || 0, distance_km: Number(form.distance_km) || 0 },
      });
      if (res.payment_url) {
        toast.info('Đã tạo đơn, đang chuyển đến trang thanh toán…');
        goToPayment(res.payment_url, navigate);
        return;
      }
      toast.success(res.message);
      navigate(`/orders/${res.order.code}`);
    } catch (err) {
      setError(err.message);
      setBusy(false);
    }
  }

  return (
    <section className="two-col">
      <form className="card" onSubmit={submit}>
        <h2>Thông tin giao hàng</h2>
        <label>Họ tên<input required value={form.shipping_name} onChange={set('shipping_name')} autoComplete="name" /></label>
        <label>Số điện thoại<input required value={form.shipping_phone} onChange={set('shipping_phone')} placeholder="0901234567" inputMode="tel" autoComplete="tel" /></label>
        <label>
          Địa chỉ nhận hàng
          <input required value={form.shipping_address} onChange={set('shipping_address')} placeholder="Số nhà, đường, phường, thành phố" autoComplete="street-address" />
        </label>
        <p className="muted small">
          {quoting && <><Spinner /> Đang tính khoảng cách từ nông trại…</>}
          {!quoting && quote?.source && quote.source !== 'manual' && <>📍 Cách nông trại khoảng <strong>{quote.distance_km} km</strong> (theo {quote.source === 'google' ? 'Google Maps' : 'OpenStreetMap'}).</>}
          {!quoting && quote?.source === 'manual' && <>Chưa xác định được địa chỉ trên bản đồ, hãy nhập khoảng cách ước tính bên dưới.</>}
        </p>
        {quote?.source === 'manual' && (
          <label>Khoảng cách ước tính (km)<input type="number" min="0" max="200" value={form.distance_km} onChange={set('distance_km')} /></label>
        )}
        <label>Ghi chú<textarea rows="2" value={form.note} onChange={set('note')} /></label>

        <h2>Thanh toán</h2>
        <div className="choice-group">
          {['cod', 'vnpay'].map((m) => (
            <label key={m} className={`choice ${form.payment_method === m ? 'selected' : ''}`}>
              <input type="radio" name="payment_method" value={m} checked={form.payment_method === m} onChange={set('payment_method')} />
              {PAYMENT_LABEL[m]}
            </label>
          ))}
        </div>
        <label>
          Dùng điểm (có {user?.points_balance ?? 0} điểm, 1 điểm = 1.000 ₫)
          <input type="number" min="0" max={user?.points_balance ?? 0} value={form.points_to_use} onChange={set('points_to_use')} />
        </label>

        <Notice kind="error">{error}</Notice>
        <Button busy={busy}>{busy ? 'Đang kiểm tra tồn kho…' : 'Đặt hàng'}</Button>
      </form>

      <aside className="card summary">
        <h2>Tóm tắt đơn hàng</h2>
        {cart.items.map((i) => (
          <div className="line" key={i.product_id}><span>{i.name} × {i.quantity}</span><span>{money(i.line_total)}</span></div>
        ))}
        <div className="line"><span>Tạm tính</span><span>{money(cart.subtotal)}</span></div>
        <div className="line"><span>Phí giao hàng {quote ? `(${quote.distance_km} km)` : ''}</span><span>{quoting ? <Spinner /> : shippingFee === 0 ? 'Miễn phí' : money(shippingFee)}</span></div>
        <div className="line"><span>Giảm từ điểm</span><span>- {money(pointsDiscount)}</span></div>
        <div className="line total"><span>Tổng cộng (ước tính)</span><strong>{money(estimatedTotal)}</strong></div>
        <p className="muted small">
          Giao từ {farm.name}{farm.address ? ` (${farm.address})` : ''}. 15.000 ₫ cho 3 km đầu, thêm 5.000 ₫/km; đơn từ 300.000 ₫ miễn phí.
          Giá và tồn kho được kiểm tra lại khi đặt hàng.
        </p>
      </aside>
    </section>
  );
}

/** Open a payment URL: the mock gateway stays inside the app (same session), VNPay is an external page. */
export function goToPayment(url, navigate) {
  const target = new URL(url, window.location.origin);
  if (target.pathname.startsWith('/payment/mock/')) {
    navigate(target.pathname);
  } else {
    window.location.href = url;
  }
}

/** Simulated payment page used when VNPay credentials are not configured (orders and subscription prepayments). */
export function MockPaymentPage() {
  const { code } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const [info, setInfo] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api(`/payments/mock/${code}`).then(setInfo).catch((e) => setError(e.message));
  }, [code]);

  const target = info?.type === 'subscription' ? '/subscriptions' : `/orders/${code}`;

  async function confirm() {
    setBusy(true);
    try {
      await api(`/payments/mock/${code}/complete`, { method: 'POST' });
      toast.success('Thanh toán thành công!');
      navigate(`${target}?payment=success`);
    } catch (e) {
      setError(e.message);
      setBusy(false);
    }
  }

  if (!info) return error ? <Notice kind="error">{error}</Notice> : <Loading />;

  return (
    <section className="card narrow">
      <h1>Cổng thanh toán giả lập</h1>
      <p>
        {info.type === 'subscription' ? 'Thanh toán trước gói định kỳ' : 'Thanh toán đơn hàng'} <strong>{code}</strong>:{' '}
        <strong className="price">{money(info.amount)}</strong>
      </p>
      <p className="muted small">
        Chế độ thử nghiệm (PAYMENT_MOCK_ENABLED). Khi có tài khoản VNPay, điền VNPAY_TMN_CODE và VNPAY_HASH_SECRET trong .env để dùng cổng thật.
      </p>
      {info.paid ? (
        <Notice kind="success">Khoản này đã được thanh toán. <Link to={target}>Xem chi tiết</Link></Notice>
      ) : (
        <>
          <Notice kind="error">{error}</Notice>
          <div className="row">
            <Button busy={busy} onClick={confirm}>Xác nhận thanh toán</Button>
            <button className="secondary" onClick={() => navigate(target)}>Thanh toán sau</button>
          </div>
        </>
      )}
    </section>
  );
}
