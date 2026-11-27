import { Link } from 'react-router-dom';
import { useFarm } from '../farm.jsx';
import { Loading } from '../ui.jsx';
import { Timeline } from './Shop.jsx';

/** The farm's own page: story, growing areas, certifications, latest diary entries. */
export function AboutPage() {
  const farm = useFarm();
  if (!farm.loaded) return <Loading />;

  return (
    <section className="about">
      <div className="hero small-hero">
        <div>
          <p className="eyebrow">Về chúng tôi</p>
          <h1>{farm.name}</h1>
          <p className="hero-slogan">{farm.slogan}</p>
        </div>
      </div>

      <div className="two-col">
        <div>
          <h2>Câu chuyện nông trại</h2>
          <p>{farm.story}</p>
          <p>
            Thay vì đi qua nhiều khâu trung gian, rau được thu hoạch buổi sáng và giao thẳng đến bạn. Mỗi lô hàng có hạn sử dụng riêng,
            hệ thống luôn xuất lô gần hết hạn trước để rau đến tay bạn vẫn tươi, và nông trại giảm được hàng hư hỏng phải bỏ đi.
          </p>
          <p><Link to="/subscriptions">Đăng ký gói rau hằng tuần</Link> · <Link to="/">Xem sản phẩm</Link></p>
        </div>
        <div className="card">
          <h3>Thông tin liên hệ</h3>
          <dl className="facts">
            <dt>Địa chỉ</dt><dd>{farm.address}</dd>
            <dt>Điện thoại</dt><dd>{farm.phone}</dd>
            <dt>Email</dt><dd>{farm.email}</dd>
            <dt>Giao hàng</dt><dd>{farm.opening_hours}</dd>
            <dt>Chứng nhận</dt><dd>{farm.certifications?.join(', ')}</dd>
            <dt>Sản phẩm</dt><dd>{farm.stats?.products} loại đang bán</dd>
          </dl>
        </div>
      </div>

      <h2>Các khu canh tác</h2>
      <div className="grid">
        {farm.plots.map((p) => (
          <div className="card" key={p.id}>
            <strong>{p.name}</strong>
            <div className="muted small">{p.code} · {p.location}{p.area_m2 ? ` · ${Number(p.area_m2).toLocaleString('vi-VN')} m²` : ''}</div>
            {p.certification && <p className="small">Chứng nhận: {p.certification}</p>}
            {p.description && <p className="small">{p.description}</p>}
          </div>
        ))}
      </div>

      <h2>Nhật ký gần đây</h2>
      <Timeline logs={farm.latest_logs ?? []} />
    </section>
  );
}
