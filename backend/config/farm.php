<?php

/*
 * The one farm this shop belongs to (Single Farm model, Tuần 1).
 * Every value can be overridden in .env, so the site can be re-branded without code changes.
 * The defaults describe a fictional farm used for the course project.
 */
return [
    'name' => env('FARM_NAME', 'Nông trại Xanh Đà Lạt'),
    'slogan' => env('FARM_SLOGAN', 'Rau sạch từ vườn đến bàn ăn trong 24 giờ'),
    'story' => env('FARM_STORY', 'Nông trại gia đình 3 thế hệ ở ngoại ô Đà Lạt, canh tác theo tiêu chuẩn VietGAP trên 2,5 ha. '
        .'Mỗi lô rau được ghi nhật ký từ lúc gieo hạt đến khi thu hoạch, và bạn có thể xem lại toàn bộ bằng mã QR trên bao bì.'),
    'address' => env('FARM_ADDRESS', 'Thôn Đa Thọ, xã Xuân Thọ, TP. Đà Lạt, Lâm Đồng'),
    'phone' => env('FARM_PHONE', '0263 3123 456'),
    'email' => env('FARM_EMAIL', 'lienhe@nongtraixanh.example'),
    'opening_hours' => env('FARM_OPENING_HOURS', 'Giao hàng 7:00 – 19:00 mỗi ngày'),
    'certifications' => ['VietGAP', 'Không thuốc trừ sâu hóa học'],

    // Farm location, the origin for distance-based shipping fees.
    'latitude' => (float) env('FARM_LATITUDE', 11.9465),
    'longitude' => (float) env('FARM_LONGITUDE', 108.5003),
];
