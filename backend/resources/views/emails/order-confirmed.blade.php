<!DOCTYPE html>
<html lang="vi">
<body style="font-family: Arial, sans-serif; color: #1d2a1f;">
    <h2 style="color: #2e7d32;">{{ config('farm.name') }}</h2>
    <p>Chào {{ $order->shipping_name }},</p>
    <p>Đơn hàng <strong>{{ $order->code }}</strong> đã được xác nhận và đang được chuẩn bị.</p>

    <table cellpadding="6" style="border-collapse: collapse; width: 100%; max-width: 560px;">
        <tr style="background: #e8f5e9;">
            <th align="left">Sản phẩm</th><th align="left">Lô</th><th align="right">SL</th><th align="right">Thành tiền</th>
        </tr>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->batch_code }}</td>
                <td align="right">{{ $item->quantity }}</td>
                <td align="right">{{ number_format($item->line_total, 0, ',', '.') }} đ</td>
            </tr>
        @endforeach
        <tr><td colspan="3">Phí giao hàng</td><td align="right">{{ number_format($order->shipping_fee, 0, ',', '.') }} đ</td></tr>
        @if ($order->points_discount > 0)
            <tr><td colspan="3">Giảm bằng điểm</td><td align="right">-{{ number_format($order->points_discount, 0, ',', '.') }} đ</td></tr>
        @endif
        <tr><td colspan="3"><strong>Tổng cộng</strong></td><td align="right"><strong>{{ number_format($order->total, 0, ',', '.') }} đ</strong></td></tr>
    </table>

    <p>Giao đến: {{ $order->shipping_address }}</p>
    <p>Mỗi sản phẩm có mã QR trên bao bì để bạn xem nhật ký canh tác của lô hàng.</p>
    <p style="color: #6b7a6d;">{{ config('farm.address') }} · {{ config('farm.phone') }}</p>
</body>
</html>
