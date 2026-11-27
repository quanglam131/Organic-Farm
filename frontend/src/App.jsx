import { useEffect, useState } from 'react';
import { BrowserRouter, Link, NavLink, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { api, hasRole, ROLE_LABEL } from './api.js';
import { AuthProvider, useAuth } from './auth.jsx';
import { FarmProvider, useFarm } from './farm.jsx';
import { Loading, ToastProvider } from './ui.jsx';
import { CartPage, CheckoutPage, HomePage, MockPaymentPage, ProductPage } from './pages/Shop.jsx';
import { ComplaintsPage, OrderPage, OrdersPage, PointsPage, SubscriptionsPage } from './pages/Account.jsx';
import { LoginPage, RegisterPage } from './pages/Auth.jsx';
import { TracePage } from './pages/Trace.jsx';
import { AboutPage } from './pages/About.jsx';
import { BackOfficePage } from './pages/backoffice/BackOffice.jsx';

function RequireAuth({ children, roles }) {
  const { user, ready } = useAuth();
  const location = useLocation();
  if (!ready) return <Loading />;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  if (roles && !hasRole(user, ...roles)) return <Navigate to="/" replace />;
  return children;
}

/** Red badge with the number of alerts, for employees (Tuần 2: red notification for Sales). */
function AlertCount() {
  const { user } = useAuth();
  const [count, setCount] = useState(0);
  const location = useLocation();

  useEffect(() => {
    if (!hasRole(user, 'staff', 'sales')) return;
    api('/admin/alerts').then((a) => setCount(a.total)).catch(() => {});
  }, [user, location.pathname]);

  return count > 0 ? <span className="alert-dot" title={`${count} cảnh báo`}>{count}</span> : null;
}

function Header() {
  const { user, logout } = useAuth();
  const farm = useFarm();
  const navigate = useNavigate();
  const location = useLocation();
  const [open, setOpen] = useState(false);
  const employee = hasRole(user, 'staff', 'sales');

  useEffect(() => setOpen(false), [location.pathname]);

  async function handleLogout() {
    await logout();
    navigate('/');
  }

  return (
    <header className="topbar">
      <Link to="/" className="brand">
        <span className="brand-mark">🌿</span>
        <span>{farm.name}</span>
      </Link>
      <button className="menu-toggle secondary" onClick={() => setOpen(!open)} aria-expanded={open} aria-label="Mở menu">☰</button>

      <div className={`menu ${open ? 'open' : ''}`}>
        <nav>
          <NavLink to="/" end>Sản phẩm</NavLink>
          <NavLink to="/about">Về nông trại</NavLink>
          {user && !employee && (
            <>
              <NavLink to="/cart">Giỏ hàng</NavLink>
              <NavLink to="/orders">Đơn hàng</NavLink>
              <NavLink to="/subscriptions">Gói định kỳ</NavLink>
              <NavLink to="/points">Điểm</NavLink>
              <NavLink to="/complaints">Khiếu nại</NavLink>
            </>
          )}
          {employee && (
            <NavLink to="/back-office" className="nav-office">Quản lý <AlertCount /></NavLink>
          )}
        </nav>
        <div className="account">
          {user ? (
            <>
              <span title={ROLE_LABEL[user.role]}>{user.name}</span>
              <button className="link" onClick={handleLogout}>Đăng xuất</button>
            </>
          ) : (
            <>
              <NavLink to="/login">Đăng nhập</NavLink>
              <NavLink to="/register">Đăng ký</NavLink>
            </>
          )}
        </div>
      </div>
    </header>
  );
}

function Footer() {
  const farm = useFarm();
  return (
    <footer className="footer">
      <div className="container footer-grid">
        <div>
          <strong>{farm.name}</strong>
          <p className="muted">{farm.slogan}</p>
          {farm.certifications?.length > 0 && <p className="small">Chứng nhận: {farm.certifications.join(' · ')}</p>}
        </div>
        <div className="small">
          {farm.address && <p>📍 {farm.address}</p>}
          {farm.phone && <p>📞 {farm.phone}</p>}
          {farm.email && <p>✉️ {farm.email}</p>}
          {farm.opening_hours && <p>🕖 {farm.opening_hours}</p>}
        </div>
        <div className="small">
          <p><Link to="/about">Câu chuyện nông trại</Link></p>
          <p><Link to="/subscriptions">Gói rau định kỳ</Link></p>
          <p className="muted">Mỗi lô hàng có mã QR truy xuất nguồn gốc.</p>
        </div>
      </div>
    </footer>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <ToastProvider>
        <AuthProvider>
          <FarmProvider>
            <Header />
            <main className="container">
              <Routes>
                <Route path="/" element={<HomePage />} />
                <Route path="/about" element={<AboutPage />} />
                <Route path="/products/:slug" element={<ProductPage />} />
                <Route path="/trace/:code" element={<TracePage />} />
                <Route path="/login" element={<LoginPage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/cart" element={<RequireAuth><CartPage /></RequireAuth>} />
                <Route path="/checkout" element={<RequireAuth><CheckoutPage /></RequireAuth>} />
                <Route path="/payment/mock/:code" element={<RequireAuth><MockPaymentPage /></RequireAuth>} />
                <Route path="/orders" element={<RequireAuth><OrdersPage /></RequireAuth>} />
                <Route path="/orders/:code" element={<RequireAuth><OrderPage /></RequireAuth>} />
                <Route path="/points" element={<RequireAuth><PointsPage /></RequireAuth>} />
                <Route path="/subscriptions" element={<RequireAuth><SubscriptionsPage /></RequireAuth>} />
                <Route path="/complaints" element={<RequireAuth><ComplaintsPage /></RequireAuth>} />
                <Route path="/back-office" element={<RequireAuth roles={['staff', 'sales']}><BackOfficePage /></RequireAuth>} />
                <Route path="/admin" element={<Navigate to="/back-office" replace />} />
                <Route path="*" element={<p>Không tìm thấy trang.</p>} />
              </Routes>
            </main>
            <Footer />
          </FarmProvider>
        </AuthProvider>
      </ToastProvider>
    </BrowserRouter>
  );
}
