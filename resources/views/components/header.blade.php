<header x-data="{ mobileMenuOpen: false }" class="sticky top-0 z-50 border-b border-gray-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ url('/') }}" class="flex items-center gap-2 text-xl font-bold text-emerald-700">
            <span>Organic Farm</span>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-medium text-gray-700 md:flex">
            <a href="{{ url('/') }}" class="hover:text-emerald-600">Trang chủ</a>
            <a href="#" class="hover:text-emerald-600">Nông sản hữu cơ</a>
            <a href="#" class="hover:text-emerald-600">Truy xuất nguồn gốc QR</a>
            <a href="#" class="hover:text-emerald-600">Giới thiệu</a>
        </nav>

        <div class="hidden items-center gap-3 md:flex">
            <x-button variant="outline">Đăng nhập</x-button>
            <x-button variant="primary">Giỏ hàng (0)</x-button>
        </div>

        <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 md:hidden" aria-label="Mở menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <div x-show="mobileMenuOpen" x-transition class="border-t border-gray-100 bg-white px-4 py-3 md:hidden">
        <nav class="flex flex-col gap-3 text-sm font-medium text-gray-700">
            <a href="{{ url('/') }}" class="hover:text-emerald-600">Trang chủ</a>
            <a href="#" class="hover:text-emerald-600">Nông sản hữu cơ</a>
            <a href="#" class="hover:text-emerald-600">Truy xuất nguồn gốc QR</a>
            <div class="flex gap-2 pt-2">
                <x-button variant="outline" class="w-full">Đăng nhập</x-button>
                <x-button variant="primary" class="w-full">Giỏ hàng</x-button>
            </div>
        </nav>
    </div>
</header>
