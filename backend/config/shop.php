<?php

// Business rules for the farm shop. All money values are whole VND.
return [

    'shipping' => [
        // Orders at or above this subtotal ship free.
        'free_threshold' => (int) env('SHOP_FREE_SHIPPING_THRESHOLD', 300000),
        'base_fee' => (int) env('SHOP_SHIPPING_BASE_FEE', 15000),
        // Distance included in the base fee, and the fee per additional km.
        // NOTE: distance is entered/estimated by the customer. Google Maps / Ahamove
        // integration (Could-have in the MoSCoW matrix) is not implemented.
        'included_km' => 3,
        'per_km_fee' => (int) env('SHOP_SHIPPING_PER_KM_FEE', 5000),
        'max_km' => 200,
    ],

    'points' => [
        // 1 point is earned for every 10,000 VND of a paid order.
        'earn_per_vnd' => 10000,
        // 1 point is worth 1,000 VND when redeemed.
        'value_vnd' => 1000,
        // Points can cover at most this share of the subtotal.
        'max_discount_percent' => 50,
    ],

    // Days before expiry at which a batch is highlighted for flash sale / waste prevention.
    'expiry_alert_days' => 3,
];
