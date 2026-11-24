<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Organic Farm - Nông sản hữu cơ sạch')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="flex min-h-screen flex-col bg-white text-gray-800 antialiased">
    <x-header />

    <main class="flex-1">
        @yield('content')
    </main>

    <x-footer />

    @stack('scripts')
</body>
</html>
