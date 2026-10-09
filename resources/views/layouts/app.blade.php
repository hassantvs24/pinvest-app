<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — @yield('title', __('messages.dashboard'))</title>
    {{-- Tailwind CSS (CDN) + jQuery (CDN) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 pb-20 md:pb-6">

    {{-- ===================== Sticky top header ===================== --}}
    <header class="sticky top-0 z-40 bg-emerald-700 text-white shadow">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center justify-between h-14">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-lg">
                    📦 {{ config('app.name') }}
                </a>

                {{-- Desktop navigation --}}
                <nav class="hidden md:flex items-center gap-1 text-sm">
                    <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded {{ request()->routeIs('dashboard') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">🏠 {{ __('messages.home') }}</a>
                    @if(auth()->user()->isOwner())
                        <a href="{{ route('owner.entries.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.entries.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">📋 {{ __('messages.entries') }}</a>
                        <a href="{{ route('owner.reports.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.reports.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">📊 {{ __('messages.reports') }}</a>
                        <a href="{{ route('owner.masters.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.masters.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">⚙️ {{ __('messages.masters') }}</a>
                        <a href="{{ route('owner.partners.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('owner.partners.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">👥 {{ __('messages.partners') }}</a>
                    @else
                        <a href="{{ route('entries.index', ['type' => 'sales']) }}" class="px-3 py-2 rounded {{ request()->routeIs('entries.*') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">📋 {{ __('messages.my_entries') }}</a>
                    @endif
                    <a href="{{ route('profile') }}" class="px-3 py-2 rounded {{ request()->routeIs('profile') ? 'bg-emerald-900' : 'hover:bg-emerald-600' }}">👤 {{ __('messages.profile') }}</a>
                </nav>

                <div class="flex items-center gap-2">
                    <span class="hidden sm:inline text-sm">{{ auth()->user()->name }}</span>
                    <span class="text-xs px-2 py-1 rounded-full {{ auth()->user()->isOwner() ? 'bg-amber-400 text-amber-900' : 'bg-emerald-500' }}">
                        {{ auth()->user()->isOwner() ? __('messages.role_owner') : __('messages.role_partner') }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="{{ __('messages.logout') }}"
                                class="bg-red-600 hover:bg-red-700 text-white w-9 h-9 rounded-full flex items-center justify-center text-xl leading-none shadow">
                            ⏻
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- ===================== Flash messages ===================== --}}
    <div class="max-w-6xl mx-auto px-4 mt-3 space-y-2">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-800 rounded-lg px-4 py-3">✅ {{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg px-4 py-3">⏳ {{ session('warning') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 rounded-lg px-4 py-3">❌ {{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-800 rounded-lg px-4 py-3">
                ❌ {{ $errors->first() }}
            </div>
        @endif
    </div>

    {{-- ===================== Page content ===================== --}}
    <main class="max-w-6xl mx-auto px-4 mt-4">
        @yield('content')
    </main>

    {{-- ===================== Bottom navigation (mobile only) ===================== --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 shadow-lg">
        <div class="grid {{ auth()->user()->isOwner() ? 'grid-cols-5' : 'grid-cols-3' }} text-center text-xs">
            <a href="{{ route('dashboard') }}" class="py-2 {{ request()->routeIs('dashboard') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                <div class="text-xl">🏠</div>{{ __('messages.home') }}
            </a>
            @if(auth()->user()->isOwner())
                <a href="{{ route('owner.entries.index') }}" class="py-2 {{ request()->routeIs('owner.entries.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">📋</div>{{ __('messages.entries') }}
                </a>
                <a href="{{ route('owner.reports.index') }}" class="py-2 {{ request()->routeIs('owner.reports.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">📊</div>{{ __('messages.reports') }}
                </a>
                <a href="{{ route('owner.masters.index') }}" class="py-2 {{ request()->routeIs('owner.masters.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">⚙️</div>{{ __('messages.masters') }}
                </a>
                <a href="{{ route('owner.partners.index') }}" class="py-2 {{ request()->routeIs('owner.partners.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">👥</div>{{ __('messages.partners') }}
                </a>
            @else
                <a href="{{ route('entries.index', ['type' => 'sales']) }}" class="py-2 {{ request()->routeIs('entries.*') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">📋</div>{{ __('messages.my_entries') }}
                </a>
                <a href="{{ route('profile') }}" class="py-2 {{ request()->routeIs('profile') ? 'text-emerald-700 font-bold' : 'text-gray-500' }}">
                    <div class="text-xl">👤</div>{{ __('messages.profile') }}
                </a>
            @endif
        </div>
    </nav>

    {{-- ===================== Shared jQuery behaviors ===================== --}}
    <script>
        $(function () {
            // Live total = quantity x unit price
            function recalcTotal() {
                var qty = parseFloat($('#quantity').val()) || 0;
                var price = parseFloat($('#unit_price').val()) || 0;
                var total = qty * price;
                $('#total_display').val(total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                var rate = parseFloat($('#commission_rate').val()) || 0;
                var commission = total * rate / 100;
                $('#commission_preview').text(commission.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }
            $(document).on('input', '#quantity, #unit_price', recalcTotal);
            recalcTotal();

            // Sale entry: auto-fill unit price from the selected item's default price
            $(document).on('change', '#head_id[data-autofill-price="1"]', function () {
                var price = $(this).find('option:selected').data('price');
                if (price) {
                    $('#unit_price').val(price);
                    recalcTotal();
                }
            });

            // Confirm popup before submitting any entry form
            $(document).on('submit', 'form.js-confirm-submit', function () {
                return confirm(@json(__('messages.submit_confirm')));
            });

            // Confirm popup before delete / reject / update
            $(document).on('submit', 'form.js-confirm-delete', function () {
                return confirm(@json(__('messages.confirm_delete')));
            });
            $(document).on('submit', 'form.js-confirm-reject', function () {
                return confirm(@json(__('messages.confirm_reject')));
            });
            $(document).on('submit', 'form.js-confirm-update', function () {
                return confirm(@json(__('messages.confirm_update')));
            });

            // Toggle inline edit forms on entry cards
            $(document).on('click', '.js-edit-toggle', function () {
                $(this).closest('.entry-card').find('.js-edit-form').toggle();
            });
        });
    </script>
</body>
</html>
