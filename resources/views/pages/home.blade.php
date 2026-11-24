@extends('layouts.app')

@section('title', 'Trang chủ - Organic Farm')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 lg:px-8">
    <span class="inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
        100% Nông sản đạt chuẩn hữu cơ
    </span>
    <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-5xl">
        Tươi sạch từ nông trại đến bàn ăn
    </h1>
    <p class="mx-auto mt-4 max-w-2xl text-base text-gray-600">
        Hệ thống quản lý lô hàng theo nguyên tắc FEFO và kiểm tra nguồn gốc từng sản phẩm bằng mã QR.
    </p>
    <div class="mt-8 flex justify-center gap-4">
        <x-button variant="primary">Khám phá nông sản</x-button>
        <x-button variant="outline">Quét mã QR truy xuất</x-button>
    </div>
</section>
@endsection
