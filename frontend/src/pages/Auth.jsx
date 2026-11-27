import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { hasRole } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Button, Notice } from '../ui.jsx';

const DEMO_ACCOUNTS = [
  ['khach@farm.local', 'Khách hàng'],
  ['staff@farm.local', 'Nhân viên nông trại'],
  ['sales@farm.local', 'Bán hàng / CSKH'],
  ['admin@farm.local', 'Quản trị viên'],
];

export function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const user = await login(form.email, form.password);
      // Employees land in their back office; customers go back to where they were.
      navigate(hasRole(user, 'staff', 'sales') ? '/back-office' : (location.state?.from ?? '/'));
    } catch (err) {
      setError(err.message);
      setBusy(false);
    }
  }

  return (
    <form className="card narrow" onSubmit={submit}>
      <h1>Đăng nhập</h1>
      <label>Email<input type="email" required autoComplete="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
      <label>Mật khẩu<input type="password" required autoComplete="current-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label>
      <Notice kind="error">{error}</Notice>
      <Button busy={busy}>Đăng nhập</Button>
      <p className="muted">Chưa có tài khoản? <Link to="/register">Đăng ký</Link></p>

      <details className="demo-accounts">
        <summary>Tài khoản dùng thử (mật khẩu: password)</summary>
        {DEMO_ACCOUNTS.map(([email, role]) => (
          <button type="button" key={email} className="link" onClick={() => setForm({ email, password: 'password' })}>
            {role}: {email}
          </button>
        ))}
      </details>
    </form>
  );
}

export function RegisterPage() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', phone: '', address: '', password: '', password_confirmation: '' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    if (form.password !== form.password_confirmation) {
      setError('Mật khẩu nhập lại không khớp.');
      return;
    }
    setBusy(true);
    setError('');
    try {
      await register(form);
      navigate('/');
    } catch (err) {
      setError(err.message);
      setBusy(false);
    }
  }

  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  return (
    <form className="card narrow" onSubmit={submit}>
      <h1>Tạo tài khoản</h1>
      <label>Họ tên<input required autoComplete="name" value={form.name} onChange={set('name')} /></label>
      <label>Email<input type="email" required autoComplete="email" value={form.email} onChange={set('email')} /></label>
      <label>Số điện thoại<input inputMode="tel" autoComplete="tel" value={form.phone} onChange={set('phone')} /></label>
      <label>Địa chỉ giao hàng<input autoComplete="street-address" value={form.address} onChange={set('address')} /></label>
      <label>Mật khẩu (tối thiểu 8 ký tự)<input type="password" minLength={8} required autoComplete="new-password" value={form.password} onChange={set('password')} /></label>
      <label>Nhập lại mật khẩu<input type="password" minLength={8} required autoComplete="new-password" value={form.password_confirmation} onChange={set('password_confirmation')} /></label>
      <Notice kind="error">{error}</Notice>
      <Button busy={busy}>Đăng ký</Button>
      <p className="muted">Đã có tài khoản? <Link to="/login">Đăng nhập</Link></p>
    </form>
  );
}
