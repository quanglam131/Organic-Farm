import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, dateVN, hasRole, money, ROLE_LABEL } from '../../api.js';
import { useAuth } from '../../auth.jsx';
import { Badge, Button, Empty, Loading, useAction } from '../../ui.jsx';
import { BatchesTab, DiaryTab } from './FarmTabs.jsx';
import { ComplaintsTab, DeliveriesTab, OrdersTab } from './SalesTabs.jsx';
import { CatalogTab, DashboardTab, PlansTab, ProductsTab, SettingsTab, UsersTab } from './AdminTabs.jsx';

/**
 * Back office. Each role sees only its own tabs (Tuần 2):
 *  staff  - alerts, batches & waste, farming diary          (no orders, customers or revenue)
 *  sales  - alerts, orders, complaints, subscription deliveries (no stock changes)
 *  admin  - everything, plus reports, products, staff accounts and settings
 * The server enforces the same rules with role middleware; hiding tabs is only for clarity.
 */
const TABS = [
  ['alerts', 'Cảnh báo', ['staff', 'sales']],
  ['batches', 'Lô hàng & hao hụt', ['staff']],
  ['diary', 'Nhật ký canh tác', ['staff']],
  ['orders', 'Đơn hàng', ['sales']],
  ['complaints', 'Khiếu nại', ['sales']],
  ['deliveries', 'Lịch giao gói', ['sales']],
  ['dashboard', 'Báo cáo', ['admin']],
  ['products', 'Sản phẩm & ảnh', ['admin']],
  ['catalog', 'Danh mục & khu trồng', ['admin']],
  ['plans', 'Gói định kỳ', ['admin']],
  ['users', 'Nhân sự & khách', ['admin']],
  ['settings', 'Cấu hình', ['admin']],
];

export function BackOfficePage() {
  const { user } = useAuth();
  const [params, setParams] = useSearchParams();
  const tabs = TABS.filter(([, , roles]) => hasRole(user, ...roles) && (roles[0] !== 'admin' || user.role === 'admin'));
  const tab = tabs.some(([key]) => key === params.get('tab')) ? params.get('tab') : tabs[0][0];

  return (
    <section>
      <div className="row space">
        <h1>Quản lý nông trại</h1>
        <Badge tone="blue">{ROLE_LABEL[user.role]}</Badge>
      </div>
      <nav className="tabs" aria-label="Chức năng">
        {tabs.map(([key, label]) => (
          <button key={key} className={tab === key ? '' : 'secondary'} onClick={() => setParams({ tab: key })}>{label}</button>
        ))}
      </nav>
      {tab === 'alerts' && <AlertsTab goTo={(key) => setParams({ tab: key })} />}
      {tab === 'batches' && <BatchesTab />}
      {tab === 'diary' && <DiaryTab />}
      {tab === 'orders' && <OrdersTab />}
      {tab === 'complaints' && <ComplaintsTab />}
      {tab === 'deliveries' && <DeliveriesTab />}
      {tab === 'dashboard' && <DashboardTab />}
      {tab === 'products' && <ProductsTab />}
      {tab === 'catalog' && <CatalogTab />}
      {tab === 'plans' && <PlansTab />}
      {tab === 'users' && <UsersTab />}
      {tab === 'settings' && <SettingsTab />}
    </section>
  );
}

/** Red alerts: near-expiry batches (flash sale), stock shortages, open complaints (Tuần 2 use case 5). */
function AlertsTab({ goTo }) {
  const { user } = useAuth();
  const [alerts, setAlerts] = useState(null);
  const [busy, run] = useAction();
  const isSales = hasRole(user, 'sales');

  const load = () => api('/admin/alerts').then(setAlerts).catch(() => setAlerts({ expiring_batches: [] }));

  useEffect(() => {
    load();
  }, []);

  async function retry(schedule) {
    const res = await run(() => api(`/admin/delivery-schedules/${schedule.id}/retry`, { method: 'POST' }), 'Đã tạo lại đơn giao cho khách.');
    if (res) load();
  }

  if (!alerts) return <Loading />;

  return (
    <div className="alerts">
      <div className={`alert-box ${alerts.expiring_batches.length ? 'red' : ''}`}>
        <h2>🔴 Lô sắp hết hạn (trong {alerts.expiry_alert_days} ngày)</h2>
        {alerts.expiring_batches.length === 0 ? (
          <Empty>Không có lô nào sắp hết hạn.</Empty>
        ) : (
          <>
            <p className="small">Ưu tiên bán trước{alerts.flash_sale_discount_percent > 0 ? `, đề xuất xả hàng giảm ${alerts.flash_sale_discount_percent}%` : ''}.</p>
            <div className="table-wrap">
              <table className="table">
                <thead><tr><th>Sản phẩm</th><th>Lô</th><th>Còn</th><th>Hết hạn</th><th>Giá xả đề xuất</th></tr></thead>
                <tbody>
                  {alerts.expiring_batches.map((b) => (
                    <tr key={b.id}>
                      <td>{b.product?.name}<div className="muted small">{b.farm_plot}</div></td>
                      <td>{b.batch_code}</td>
                      <td>{b.quantity_on_hand} {b.product?.unit}</td>
                      <td><Badge tone={b.days_left <= 1 ? 'red' : 'yellow'}>{dateVN(b.expires_on)} · còn {b.days_left} ngày</Badge></td>
                      <td>{money(Math.round((b.product?.price ?? 0) * (100 - alerts.flash_sale_discount_percent) / 100))}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>

      {isSales && (
        <>
          <div className={`alert-box ${alerts.stock_issue_orders?.length ? 'red' : ''}`}>
            <h2>Đơn thiếu hàng cần liên hệ khách</h2>
            {alerts.stock_issue_orders?.length ? (
              alerts.stock_issue_orders.map((o) => (
                <div key={o.id} className="row space alert-row">
                  <div><strong>{o.code}</strong> · {o.shipping_name} · <a href={`tel:${o.shipping_phone}`}>{o.shipping_phone}</a><div className="muted small">{o.issue_note}</div></div>
                  <button className="secondary" onClick={() => goTo('orders')}>Xử lý</button>
                </div>
              ))
            ) : <Empty>Không có.</Empty>}
          </div>

          <div className={`alert-box ${alerts.short_deliveries?.length ? 'red' : ''}`}>
            <h2>Lịch giao gói định kỳ bị thiếu hàng</h2>
            {alerts.short_deliveries?.length ? (
              alerts.short_deliveries.map((d) => (
                <div key={d.id} className="row space alert-row">
                  <div>
                    <strong>{dateVN(d.delivery_date)}</strong> · {d.subscription?.plan?.name} · {d.subscription?.shipping_name} ·{' '}
                    <a href={`tel:${d.subscription?.shipping_phone}`}>{d.subscription?.shipping_phone}</a>
                    <div className="muted small">{d.note} – liên hệ khách để đổi sản phẩm tương đương.</div>
                  </div>
                  <Button className="secondary" busy={busy} onClick={() => retry(d)}>Thử tạo lại đơn</Button>
                </div>
              ))
            ) : <Empty>Không có.</Empty>}
          </div>

          <div className="alert-box">
            <h2>Khiếu nại đang mở: {alerts.open_complaints ?? 0}</h2>
            {alerts.open_complaints > 0 && <button className="secondary" onClick={() => goTo('complaints')}>Xem khiếu nại</button>}
          </div>
        </>
      )}

      <p className="muted small">Khách hàng xem trang <Link to="/">cửa hàng</Link>.</p>
    </div>
  );
}
