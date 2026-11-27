import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { QRCodeSVG } from 'qrcode.react';
import { api, dateVN } from '../api.js';
import { Badge, Loading, Notice } from '../ui.jsx';
import { Timeline } from './Shop.jsx';

/** Public traceability page opened by scanning a batch's QR code. */
export function TracePage() {
  const { code } = useParams();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    setData(null);
    setError('');

    api(`/trace/${encodeURIComponent(code)}`)
      .then((result) => { if (active) setData(result); })
      .catch((e) => { if (active) setError(e.message); });

    return () => { active = false; };
  }, [code]);

  if (error) return <Notice kind="error">Không tìm thấy lô hàng với mã &quot;{code}&quot;. Kiểm tra lại mã trên bao bì.</Notice>;
  if (!data) return <Loading />;

  return (
    <section className="trace">
      <div className="card">
        <p className="eyebrow dark">Truy xuất nguồn gốc · {data.farm?.name}</p>
        <h1>{data.product.name}</h1>
        <dl className="facts">
          <dt>Mã lô</dt><dd>{data.batch_code}</dd>
          <dt>Mã truy xuất</dt><dd>{data.trace_code}</dd>
          <dt>Khu canh tác</dt><dd>{data.farm_plot?.name ?? '—'}{data.farm_plot?.location ? ` · ${data.farm_plot.location}` : ''}</dd>
          <dt>Chứng nhận</dt><dd>{data.farm_plot?.certification ?? data.farm?.certifications?.join(', ') ?? 'Đang cập nhật'}</dd>
          <dt>Ngày thu hoạch</dt><dd>{dateVN(data.harvested_on)}</dd>
          <dt>Hạn sử dụng</dt>
          <dd>{dateVN(data.expires_on)} {data.is_expired ? <Badge tone="red">Đã hết hạn</Badge> : <Badge tone="green">Còn hạn</Badge>}</dd>
          <dt>Sản lượng lô</dt><dd>{data.quantity_received} {data.product.unit}</dd>
          <dt>Địa chỉ nông trại</dt><dd>{data.farm?.address}</dd>
        </dl>
        <Link to={`/products/${data.product.slug}`}>Xem sản phẩm và đặt mua</Link>
      </div>

      <div className="card qr">
        <QRCodeSVG value={window.location.href} size={180} level="M" includeMargin />
        <p className="muted small">Mã QR của lô {data.batch_code}</p>
      </div>

      <h2>Nhật ký canh tác của lô hàng</h2>
      <Timeline logs={data.logs} />
    </section>
  );
}
