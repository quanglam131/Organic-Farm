<?php

return [

    // Frontend origin, used to redirect the browser after payment.
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // Mock gateway: lets the whole checkout run without a merchant account
    // (Tuần 1 risk plan: fall back to dummy data when a third-party API fails).
    // Must be false in production.
    'mock_enabled' => (bool) env('PAYMENT_MOCK_ENABLED', true),

    // VNPay sandbox: https://sandbox.vnpayment.vn (request a TMN code + hash secret).
    'vnpay' => [
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'return_url' => env('VNPAY_RETURN_URL', env('APP_URL', 'http://localhost:8000').'/api/payments/vnpay/return'),
    ],
];
