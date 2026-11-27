import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, getToken, setToken } from './api.js';
import { Button, Modal, Notice, useToast } from './ui.jsx';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [ready, setReady] = useState(false);
  const [expired, setExpired] = useState(false);

  useEffect(() => {
    if (!getToken()) {
      setReady(true);
      return;
    }
    api('/me')
      .then(setUser)
      .catch(() => setToken(null))
      .finally(() => setReady(true));
  }, []);

  // The API layer fires this when a signed-in request gets 401 (token expired).
  useEffect(() => {
    const onExpired = () => setExpired(true);
    window.addEventListener('session-expired', onExpired);
    return () => window.removeEventListener('session-expired', onExpired);
  }, []);

  const saveSession = useCallback((data) => {
    setToken(data.token);
    setUser(data.user);
  }, []);

  const login = useCallback(
    async (email, password) => {
      const data = await api('/auth/login', { method: 'POST', body: { email, password } });
      saveSession(data);
      return data.user;
    },
    [saveSession],
  );

  const register = useCallback(
    async (form) => {
      const data = await api('/auth/register', { method: 'POST', body: form });
      saveSession(data);
      return data.user;
    },
    [saveSession],
  );

  const logout = useCallback(async () => {
    try {
      await api('/auth/logout', { method: 'POST' });
    } catch {
      // Token may already be invalid; clear it locally anyway.
    }
    setToken(null);
    setUser(null);
  }, []);

  const refresh = useCallback(async () => setUser(await api('/me')), []);

  const value = useMemo(
    () => ({ user, ready, login, register, logout, refresh }),
    [user, ready, login, register, logout, refresh],
  );

  return (
    <AuthContext.Provider value={value}>
      {children}
      {expired && (
        <SessionExpiredModal
          email={user?.email ?? ''}
          onLogin={async (email, password) => {
            await login(email, password);
            setExpired(false);
          }}
          onClose={() => {
            setExpired(false);
            setToken(null);
            setUser(null);
          }}
        />
      )}
    </AuthContext.Provider>
  );
}

/**
 * Shown instead of a raw error when the session times out (Tuần 6 requirement): the user signs in
 * again in place, keeps the page and what they typed, and repeats the action.
 */
function SessionExpiredModal({ email: initialEmail, onLogin, onClose }) {
  const toast = useToast();
  const [email, setEmail] = useState(initialEmail);
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await onLogin(email, password);
      toast.success('Đã đăng nhập lại. Bạn hãy thực hiện lại thao tác vừa rồi.');
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal title="Phiên đăng nhập đã hết hạn" onClose={onClose}>
      <p className="muted">Vì lý do bảo mật, phiên làm việc đã hết hạn. Đăng nhập lại để tiếp tục, dữ liệu bạn đang nhập vẫn được giữ nguyên.</p>
      <form onSubmit={submit}>
        <label>Email<input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} /></label>
        <label>Mật khẩu<input type="password" required autoFocus value={password} onChange={(e) => setPassword(e.target.value)} /></label>
        <Notice kind="error">{error}</Notice>
        <div className="row">
          <Button busy={busy}>Đăng nhập lại</Button>
          <button type="button" className="secondary" onClick={onClose}>Đăng xuất</button>
        </div>
      </form>
    </Modal>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
