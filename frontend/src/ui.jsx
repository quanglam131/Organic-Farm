import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { ORDER_STATUS } from './api.js';

export function Notice({ kind = 'info', children }) {
  if (!children) return null;
  return <div className={`notice ${kind}`} role={kind === 'error' ? 'alert' : 'status'}>{children}</div>;
}

export function Badge({ tone = 'gray', children }) {
  return <span className={`badge ${tone}`}>{children}</span>;
}

const STATUS_TONE = {
  pending: 'yellow',
  confirmed: 'blue',
  stock_issue: 'red',
  shipping: 'blue',
  delivered: 'green',
  cancelled: 'gray',
};

export function StatusBadge({ status }) {
  return <Badge tone={STATUS_TONE[status] ?? 'gray'}>{ORDER_STATUS[status] ?? status}</Badge>;
}

export function Spinner({ light = false }) {
  return <span className={`spinner ${light ? 'light' : ''}`} aria-hidden="true" />;
}

export function Loading({ text = 'Đang tải…' }) {
  return (
    <p className="muted loading">
      <Spinner /> {text}
    </p>
  );
}

/** A button that dims and shows a spinner while its action runs (Tuần 6: waiting state). */
export function Button({ busy = false, disabled = false, children, className = '', ...props }) {
  const solid = !/secondary|link/.test(className);
  return (
    <button className={className} disabled={busy || disabled} aria-busy={busy} {...props}>
      {busy && <Spinner light={solid} />}
      {children}
    </button>
  );
}

export function Empty({ children }) {
  return <p className="muted empty">{children}</p>;
}

export function Stat({ label, value, tone = '' }) {
  return (
    <div className={`card stat ${tone}`}>
      <span className="muted">{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

export function Modal({ title, children, onClose }) {
  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose?.();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="modal-backdrop" onMouseDown={(e) => e.target === e.currentTarget && onClose?.()}>
      <div className="modal card" role="dialog" aria-modal="true" aria-label={title}>
        <div className="row space">
          <h2>{title}</h2>
          {onClose && <button className="link" onClick={onClose} aria-label="Đóng">✕</button>}
        </div>
        {children}
      </div>
    </div>
  );
}

/* ---------- Toast notifications (corner of the screen) ---------- */

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const nextId = useRef(1);

  const push = useCallback((kind, text) => {
    const id = nextId.current++;
    setToasts((list) => [...list, { id, kind, text }]);
    setTimeout(() => setToasts((list) => list.filter((t) => t.id !== id)), kind === 'error' ? 6000 : 3500);
  }, []);

  const toast = useMemo(() => ({
    success: (text) => push('success', text),
    error: (text) => push('error', text),
    info: (text) => push('info', text),
  }), [push]);

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div className="toasts" aria-live="polite">
        {toasts.map((t) => (
          <div key={t.id} className={`toast ${t.kind}`} onClick={() => setToasts((l) => l.filter((x) => x.id !== t.id))}>
            {t.kind === 'success' ? '✓ ' : t.kind === 'error' ? '⚠ ' : ''}{t.text}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast() {
  return useContext(ToastContext);
}

/** Run an async action with a busy flag and a toast for the result. */
export function useAction() {
  const toast = useToast();
  const [busy, setBusy] = useState(false);

  const run = useCallback(async (fn, successText) => {
    setBusy(true);
    try {
      const result = await fn();
      if (successText) toast.success(typeof successText === 'function' ? successText(result) : successText);
      return result;
    } catch (e) {
      if (e?.status !== 401) toast.error(e.message);
      return undefined;
    } finally {
      setBusy(false);
    }
  }, [toast]);

  return [busy, run];
}
